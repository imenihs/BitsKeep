<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\ComponentDatasheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComponentHelperChatGptJobTest extends TestCase
{
    use RefreshDatabase;
    /**
     * 目的: 「chatgpt job creates temp pdf and returns signed download url」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_chatgpt_job_creates_temp_pdf_and_returns_signed_download_url(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'role' => 'editor',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post('/api/component-helper/chatgpt-jobs', [
            'datasheets' => [
                UploadedFile::fake()->create('logic-device.pdf', 256, 'application/pdf'),
            ],
            'datasheet_labels' => ['英語版'],
            'target_index' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.datasheets.0.display_name', '英語版');

        $payload = $response->json('data');
        $this->assertNotEmpty($payload['prompt_text']);
        $this->assertStringContainsString('添付した電子部品のデータシート PDF を読み取り', $payload['prompt_text']);
        $this->assertNotEmpty($payload['temp_upload_token']);
        $this->assertNotEmpty($payload['target_datasheet']['signed_download_url']);

        $downloadResponse = $this->get($payload['target_datasheet']['signed_download_url']);
        $downloadResponse->assertOk();
    }
    /**
     * 目的: 「component store can claim temp datasheet tokens and finalize pdf」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_component_store_can_claim_temp_datasheet_tokens_and_finalize_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'role' => 'editor',
            'is_active' => true,
        ]);

        $jobResponse = $this->actingAs($user)->post('/api/component-helper/chatgpt-jobs', [
            'datasheets' => [
                UploadedFile::fake()->create('amp-datasheet.pdf', 128, 'application/pdf'),
            ],
            'datasheet_labels' => ['一次取得PDF'],
            'target_index' => 0,
        ])->assertOk();

        $token = $jobResponse->json('data.temp_upload_token');

        $storeResponse = $this->actingAs($user)->post('/api/components', [
            'part_number' => 'TEST-AMP-001',
            'common_name' => 'テスト部品',
            'temp_datasheet_tokens' => [$token],
            'temp_datasheet_labels' => ['正式版PDF'],
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.part_number', 'TEST-AMP-001');

        $component = Component::firstOrFail();
        $datasheet = ComponentDatasheet::firstOrFail();

        $this->assertSame($component->id, $datasheet->component_id);
        $this->assertSame('正式版PDF', $datasheet->note);
        $this->assertNotNull($component->datasheet_path);
        Storage::disk('local')->assertMissing("component-helper-temp/{$token}.pdf");
        Storage::disk('local')->assertMissing("component-helper-temp/{$token}.json");
    }
}

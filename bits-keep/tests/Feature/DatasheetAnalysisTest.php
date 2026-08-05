<?php

namespace Tests\Feature;

use App\Exceptions\DatasheetAnalysisException;
use App\Jobs\AnalyzeDatasheetJob;
use App\Models\DatasheetAnalysis;
use App\Models\SpecGroup;
use App\Models\User;
use App\Services\Datasheet\DatasheetAnalysisOutcome;
use App\Services\Datasheet\DatasheetAnalyzer;
use App\Services\Datasheet\DatasheetAnalyzerRegistry;
use App\Services\Datasheet\PdfExtraction;
use App\Services\TempDatasheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DatasheetAnalysisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 目的: 解析登録がジョブを積み、結果を即返さないことを検証する。
     * 機能: 登録APIの応答と、キューへの登録、解析記録の初期状態を確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase とキューのフェイクで実行されること。
     * 副作用: テストDBと一時ファイル保管を利用する。
     */
    public function test_store_queues_analysis_without_returning_result(): void
    {
        Queue::fake();
        $user = $this->editor();
        $this->useFakeAnalyzer($this->succeedingAnalyzer());

        $token = $this->createTempDatasheet();

        $response = $this->actingAs($user)->postJson('/api/datasheet-analyses', [
            'temp_token' => $token,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.state', DatasheetAnalysis::STATE_QUEUED)
            ->assertJsonPath('data.finished', false)
            // 登録時点では解析していない。結果を返すと同期実行に戻ってしまう
            ->assertJsonPath('data.result', null);

        Queue::assertPushed(AnalyzeDatasheetJob::class);
        $this->assertDatabaseHas('datasheet_analyses', [
            'temp_token' => $token,
            'state' => DatasheetAnalysis::STATE_QUEUED,
            'user_id' => $user->id,
        ]);
    }

    /**
     * 目的: 期限切れや存在しない一時PDFではジョブを積まないことを検証する。
     * 機能: 無効なトークンでの登録が入力エラーになり、キューへ積まれないことを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase とキューのフェイクで実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_store_rejects_missing_temp_datasheet(): void
    {
        Queue::fake();
        $user = $this->editor();
        $this->useFakeAnalyzer($this->succeedingAnalyzer());

        $response = $this->actingAs($user)->postJson('/api/datasheet-analyses', [
            'temp_token' => 'does-not-exist',
        ]);

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    }

    /**
     * 目的: 使えないエンジンではジョブを積まずに理由を返すことを検証する。
     * 機能: 未ログイン状態のエンジンで登録した場合の応答を確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase とキューのフェイクで実行されること。
     * 副作用: テストDBと一時ファイル保管を利用する。
     */
    public function test_store_rejects_when_engine_is_unavailable(): void
    {
        Queue::fake();
        $user = $this->editor();
        $this->useFakeAnalyzer($this->unavailableAnalyzer());

        $token = $this->createTempDatasheet();

        $response = $this->actingAs($user)->postJson('/api/datasheet-analyses', [
            'temp_token' => $token,
        ]);

        // 待たせた末に失敗を見せないため、登録時点で弾く
        $response->assertStatus(403)
            ->assertJsonPath('success', false);
        Queue::assertNothingPushed();
    }

    /**
     * 目的: ジョブ実行で解析結果が保存され、状態取得APIから読めることを検証する。
     * 機能: ジョブを同期実行し、スペック詳細照合と分類推薦を通した結果が返ることを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBと一時ファイル保管を利用する。
     */
    public function test_job_stores_result_and_show_returns_it(): void
    {
        $user = $this->editor();
        $this->useFakeAnalyzer($this->succeedingAnalyzer());

        // 分類候補の突き合わせ先を用意し、後段の推薦まで通ることを見る
        SpecGroup::create(['name' => 'オペアンプ', 'sort_order' => 1]);

        $token = $this->createTempDatasheet();

        $analysis = DatasheetAnalysis::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'temp_token' => $token,
            'engine' => 'fake',
            'state' => DatasheetAnalysis::STATE_QUEUED,
        ]);

        (new AnalyzeDatasheetJob($analysis->id))->handle(
            app(DatasheetAnalyzerRegistry::class),
            app(TempDatasheetService::class),
            app(\App\Services\SpecTypeMatchingService::class),
            app(\App\Services\Datasheet\DatasheetRecommendationDecorator::class),
        );

        $response = $this->actingAs($user)->getJson('/api/datasheet-analyses/'.$analysis->public_id);

        $response->assertOk()
            ->assertJsonPath('data.state', DatasheetAnalysis::STATE_SUCCEEDED)
            ->assertJsonPath('data.finished', true)
            ->assertJsonPath('data.result.part_number', 'LM358N')
            ->assertJsonPath('data.result.specs.0.name_ja', '電源電圧')
            // 入力の渡し方は所要時間と精度の切り分けに使うため記録する
            ->assertJsonPath('data.input_mode', PdfExtraction::MODE_TEXT);

        // 後段の推薦がエンジンによらず付くこと
        $this->assertNotNull($response->json('data.result.category_candidates'));
        $this->assertSame('オペアンプ', $response->json('data.result.category_candidates.0.name'));
        $this->assertTrue($response->json('data.result.category_candidates.0.matched'));
    }

    /**
     * 目的: 解析失敗が種別付きで記録され、画面へ再実行可否が伝わることを検証する。
     * 機能: 未ログイン失敗を起こし、失敗種別と再実行可否を確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBと一時ファイル保管を利用する。
     */
    public function test_job_records_failure_kind_for_authentication_failure(): void
    {
        $user = $this->editor();
        $this->useFakeAnalyzer($this->failingAnalyzer(DatasheetAnalysisException::notAuthenticated()));

        $token = $this->createTempDatasheet();

        $analysis = DatasheetAnalysis::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'temp_token' => $token,
            'engine' => 'fake',
            'state' => DatasheetAnalysis::STATE_QUEUED,
        ]);

        (new AnalyzeDatasheetJob($analysis->id))->handle(
            app(DatasheetAnalyzerRegistry::class),
            app(TempDatasheetService::class),
            app(\App\Services\SpecTypeMatchingService::class),
            app(\App\Services\Datasheet\DatasheetRecommendationDecorator::class),
        );

        $response = $this->actingAs($user)->getJson('/api/datasheet-analyses/'.$analysis->public_id);

        $response->assertOk()
            ->assertJsonPath('data.state', DatasheetAnalysis::STATE_FAILED)
            ->assertJsonPath('data.failure_kind', DatasheetAnalysis::FAILURE_NOT_AUTHENTICATED)
            // 未ログインは再実行しても同じ結果になるため、再実行を勧めない
            ->assertJsonPath('data.retryable', false);
    }

    /**
     * 目的: 他利用者の解析を参照できないことを検証する。
     * 機能: 別利用者の解析IDでの状態取得が見つからない扱いになることを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_show_hides_other_users_analysis(): void
    {
        $owner = $this->editor();
        $other = $this->editor();

        $analysis = DatasheetAnalysis::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $owner->id,
            'temp_token' => 'token-owned-by-other',
            'engine' => 'fake',
            'state' => DatasheetAnalysis::STATE_QUEUED,
        ]);

        $this->actingAs($other)
            ->getJson('/api/datasheet-analyses/'.$analysis->public_id)
            ->assertStatus(404);
    }

    /**
     * 目的: 解析の中止が失敗ではなく中止として記録されることを検証する。
     * 機能: 破棄APIが状態を中止へ倒し、失敗理由を残さず、再実行を勧められることを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_destroy_records_cancellation_not_failure(): void
    {
        $user = $this->editor();

        $analysis = DatasheetAnalysis::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'temp_token' => 'token-to-cancel',
            'engine' => 'fake',
            'state' => DatasheetAnalysis::STATE_RUNNING,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/datasheet-analyses/'.$analysis->public_id)
            ->assertOk()
            // 利用者の操作どおりに終わった状態であり、失敗として扱わない
            ->assertJsonPath('data.state', DatasheetAnalysis::STATE_CANCELED)
            ->assertJsonPath('data.finished', true)
            // 失敗理由を出すと、自分の操作を不具合と誤解させる
            ->assertJsonPath('data.failure_kind', null)
            ->assertJsonPath('data.failure_message', null)
            // 同じPDFでそのままやり直せる
            ->assertJsonPath('data.retryable', true);
    }

    /**
     * 目的: 中止済みの解析をワーカーが上書きしないことを検証する。
     * 機能: 中止後にジョブの失敗処理が走っても状態が変わらないことを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_canceled_analysis_is_not_overwritten_by_worker(): void
    {
        $user = $this->editor();

        $analysis = DatasheetAnalysis::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'temp_token' => 'token-canceled',
            'engine' => 'fake',
            'state' => DatasheetAnalysis::STATE_CANCELED,
            'finished_at' => now(),
        ]);

        // 中止の直後にワーカー側の時間超過処理が走っても、中止のまま残ること
        (new \App\Jobs\AnalyzeDatasheetJob($analysis->id))->failed(null);

        $this->assertSame(DatasheetAnalysis::STATE_CANCELED, $analysis->fresh()->state);
        $this->assertNull($analysis->fresh()->failure_kind);
    }

    /**
     * 目的: 解析方式の設定APIが選択肢と有効な方式を返すことを検証する。
     * 機能: 取得と変更の双方を確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBの設定値を更新する。
     */
    public function test_datasheet_engine_setting_can_be_read_and_updated(): void
    {
        $user = $this->editor();

        $this->actingAs($user)
            ->getJson('/api/settings/integrations/datasheet-engine')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active', 'codex');

        $this->actingAs($user)
            ->putJson('/api/settings/integrations/datasheet-engine', ['engine' => 'gemini'])
            ->assertOk()
            ->assertJsonPath('data.active', 'gemini');

        // 未登録の識別子は保存させない
        $this->actingAs($user)
            ->putJson('/api/settings/integrations/datasheet-engine', ['engine' => 'unknown-engine'])
            ->assertStatus(422);
    }

    /**
     * 目的: 部品登録画面の解析入口が1本に保たれていることを検証する。
     * 機能: 主導線のボタンが出ており、解析エンジン名を冠した旧ボタン名が残っていないことを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: フロントエンドのビルド済みアセットが存在すること。
     * 副作用: テストDBとHTTPセッションを利用する。
     */
    public function test_component_create_screen_exposes_single_analysis_entrypoint(): void
    {
        $user = $this->editor();

        $response = $this->actingAs($user)->get('/components/create');

        $response->assertOk()
            // 主導線はエンジン名を出さない1本のボタン
            ->assertSee('データシートから自動入力', false)
            ->assertSee('startServerAnalysis', false)
            // 解析エンジン名を冠した主導線ボタンは廃止済み
            ->assertDontSee('Geminiで自動入力', false)
            ->assertDontSee('ChatGPTで自動入力', false);
    }

    /**
     * 目的: 連携設定に解析方式の選択があることを検証する。
     * 機能: 解析方式セクションが描画されることを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: フロントエンドのビルド済みアセットが存在すること。
     * 副作用: テストDBとHTTPセッションを利用する。
     */
    public function test_integration_settings_screen_exposes_engine_selection(): void
    {
        $user = $this->editor();

        $this->actingAs($user)->get('/settings/integrations')
            ->assertOk()
            ->assertSee('解析の実行方式', false)
            ->assertSee('saveDatasheetEngine', false);
    }

    /**
     * 目的: 編集権限を持つ利用者を作る。
     * 機能: テスト用の編集者アカウントを作成する。
     * 入力: なし。
     * 出力: 作成した利用者。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBへ利用者を作成する。
     */
    private function editor(): User
    {
        return User::factory()->create([
            'role' => 'editor',
            'is_active' => true,
        ]);
    }

    /**
     * 目的: 解析対象の一時PDFを1件用意する。
     * 機能: 一時PDF保管サービスへPDFを預け、そのトークンを返す。
     * 入力: なし。
     * 出力: 一時PDFトークン。
     * 動作条件: local ディスクが書き込み可能であること。
     * 副作用: 一時PDFを保存する。
     */
    private function createTempDatasheet(): string
    {
        $entries = app(TempDatasheetService::class)->createMany(
            [UploadedFile::fake()->create('op-amp.pdf', 128, 'application/pdf')],
            ['英語版']
        );

        return $entries[0]['token'];
    }

    /**
     * 目的: 差し替えたエンジン1件だけを持つレジストリをコンテナへ登録する。
     * 機能: 実際の Codex 実行や外部API呼び出しを行わずに解析経路を検証できるようにする。
     * 入力: $analyzer は差し替えるエンジン。
     * 出力: なし。
     * 動作条件: なし。
     * 副作用: コンテナのバインドを差し替える。
     */
    private function useFakeAnalyzer(DatasheetAnalyzer $analyzer): void
    {
        $this->app->instance(
            DatasheetAnalyzerRegistry::class,
            new DatasheetAnalyzerRegistry([$analyzer], app(\App\Services\AppSettingService::class))
        );
    }

    /**
     * 目的: 解析に成功するエンジンを作る。
     * 機能: 固定の解析結果を返すエンジンを返す。
     * 入力: なし。
     * 出力: エンジン実装。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function succeedingAnalyzer(): DatasheetAnalyzer
    {
        return new class implements DatasheetAnalyzer
        {
            public function key(): string
            {
                return 'fake';
            }

            public function label(): string
            {
                return 'テスト用解析';
            }

            public function checkAvailability(): array
            {
                return ['available' => true, 'message' => null];
            }

            public function analyze(string $pdfPath, ?callable $onPhase = null): DatasheetAnalysisOutcome
            {
                if ($onPhase !== null) {
                    $onPhase('preparing');
                    $onPhase('running');
                }

                return new DatasheetAnalysisOutcome(
                    [
                        'part_number' => 'LM358N',
                        'manufacturer' => 'Texas Instruments',
                        'common_name' => 'デュアルオペアンプ',
                        'component_types' => ['オペアンプ'],
                        'package_names' => ['DIP-8'],
                        'description' => '汎用デュアルオペアンプ。',
                        'specs' => [
                            [
                                'name' => 'Supply Voltage',
                                'name_ja' => '電源電圧',
                                'name_en' => 'Supply Voltage',
                                'symbol' => 'V_CC',
                                'value_profile' => 'range',
                                'value' => '',
                                'value_typ' => '',
                                'value_min' => '3',
                                'value_max' => '32',
                                'unit' => 'V',
                            ],
                        ],
                    ],
                    PdfExtraction::MODE_TEXT,
                    null,
                );
            }
        };
    }

    /**
     * 目的: 利用できないエンジンを作る。
     * 機能: 利用可否確認で false を返すエンジンを返す。
     * 入力: なし。
     * 出力: エンジン実装。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function unavailableAnalyzer(): DatasheetAnalyzer
    {
        return new class implements DatasheetAnalyzer
        {
            public function key(): string
            {
                return 'fake';
            }

            public function label(): string
            {
                return 'テスト用解析';
            }

            public function checkAvailability(): array
            {
                return ['available' => false, 'message' => 'ログインしていません。'];
            }

            public function analyze(string $pdfPath, ?callable $onPhase = null): DatasheetAnalysisOutcome
            {
                throw DatasheetAnalysisException::notAuthenticated();
            }
        };
    }

    /**
     * 目的: 指定した失敗を起こすエンジンを作る。
     * 機能: analyze() で渡された例外を投げるエンジンを返す。
     * 入力: $exception は投げる例外。
     * 出力: エンジン実装。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function failingAnalyzer(DatasheetAnalysisException $exception): DatasheetAnalyzer
    {
        return new class($exception) implements DatasheetAnalyzer
        {
            public function __construct(private DatasheetAnalysisException $exception) {}

            public function key(): string
            {
                return 'fake';
            }

            public function label(): string
            {
                return 'テスト用解析';
            }

            public function checkAvailability(): array
            {
                return ['available' => true, 'message' => null];
            }

            public function analyze(string $pdfPath, ?callable $onPhase = null): DatasheetAnalysisOutcome
            {
                throw $this->exception;
            }
        };
    }
}

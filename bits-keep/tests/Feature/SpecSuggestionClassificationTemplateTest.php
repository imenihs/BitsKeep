<?php

namespace Tests\Feature;

use App\Models\SpecGroup;
use App\Models\SpecTemplate;
use App\Models\SpecType;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SpecSuggestionClassificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    /**
     * 目的: setupの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($this->user);
    }
    /**
     * 目的: 「spec suggestions return category recommended spec groups and template candidates」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_spec_suggestions_return_category_recommended_spec_groups_and_template_candidates(): void
    {
        $collectorCurrent = $this->createSpecType('コレクタ電流', 'IC', 'A', 10);
        $resistance = $this->createSpecType('抵抗値', 'R', 'Ω', 20);

        $bjtGroup = $this->createSpecGroup('BJT', 10, $collectorCurrent);
        $resistorGroup = $this->createSpecGroup('抵抗器', 20, $resistance);
        $bjtTemplate = $this->createTemplate($bjtGroup, 'BJT 基本入力', $collectorCurrent);
        $resistorTemplate = $this->createTemplate($resistorGroup, '抵抗 基本入力', $resistance);

        $response = $this
            ->getJson("/api/spec-suggestions?category_ids[]={$bjtGroup->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame([$bjtGroup->id], $response->json('data.recommended_group_ids'));
        $this->assertSame([$bjtTemplate->id], $response->json('data.recommended_template_ids'));

        $groupsById = collect($response->json('data.groups'))->keyBy('id');
        $this->assertTrue($groupsById[$bjtGroup->id]['is_suggested']);
        $this->assertFalse($groupsById->has($resistorGroup->id));

        $templatesById = collect($response->json('data.templates'))->keyBy('id');
        $this->assertTrue($templatesById[$bjtTemplate->id]['is_suggested']);
        $this->assertFalse($templatesById->has($resistorTemplate->id));
    }
    /**
     * 目的: 「common spec details are returned as candidates without common spec group」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_common_spec_details_are_returned_as_candidates_without_common_spec_group(): void
    {
        $commonGroup = SpecGroup::create([
            'name' => '共通',
            'description' => '共通スペック詳細は分類として返さない',
            'sort_order' => 5,
        ]);
        $commonSpec = SpecType::create([
            'name' => '動作温度',
            'name_ja' => '動作温度',
            'symbol' => 'Topr',
            'spec_scope' => SpecType::SCOPE_COMMON,
            'owner_spec_group_id' => null,
            'base_unit' => 'degC',
            'sort_order' => 5,
        ]);
        $gainBandwidth = $this->createSpecType('ゲイン帯域幅', 'GBW', 'Hz', 10);
        $opampGroup = $this->createSpecGroup('オペアンプ', 10, $gainBandwidth);

        $commonGroup->specTypes()->attach($commonSpec->id, ['sort_order' => 1, 'is_recommended' => true]);
        $opampGroup->specTypes()->attach($commonSpec->id, ['sort_order' => 20, 'is_recommended' => true]);

        $response = $this
            ->getJson("/api/spec-suggestions?category_ids[]={$opampGroup->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $groupNames = collect($response->json('data.groups'))->pluck('name');
        $this->assertFalse($groupNames->contains('共通'));
        $this->assertSame([$opampGroup->id], $response->json('data.recommended_group_ids'));

        $specTypeIds = collect($response->json('data.spec_types'))->pluck('id');
        $this->assertTrue($specTypeIds->contains($commonSpec->id));
        $this->assertTrue($specTypeIds->contains($gainBandwidth->id));
    }
    /**
     * 目的: 「datasheet analysis returns spec group and template recommendations from category candidates」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_datasheet_analysis_returns_spec_group_and_template_recommendations_from_category_candidates(): void
    {
        $collectorCurrent = $this->createSpecType('コレクタ電流', 'IC', 'A', 10);
        $bjtGroup = $this->createSpecGroup('トランジスタ', 10, $collectorCurrent);
        $bjtTemplate = $this->createTemplate($bjtGroup, 'BJT データシート確認', $collectorCurrent);

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('uploadFile')->once()->andReturn('gemini://files/test-transistor');
            $mock->shouldReceive('analyzeDatasheet')->once()->andReturn([
                'part_number' => '2SC1815',
                'manufacturer' => 'Toshiba',
                'common_name' => 'NPN transistor',
                'component_types' => ['トランジスタ'],
                'package_names' => ['TO-92'],
                'description' => '小信号NPNトランジスタ',
                'specs' => [
                    [
                        'name' => 'コレクタ電流',
                        'name_ja' => 'コレクタ電流',
                        'symbol' => 'IC',
                        'value_profile' => 'max_only',
                        'value' => '150',
                        'value_max' => '150',
                        'unit' => 'mA',
                    ],
                ],
            ]);
        });

        $response = $this
            ->post('/api/component-helper/analyze-datasheet', [
                'pdf' => UploadedFile::fake()->create('transistor.pdf', 32, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.component_types.0', 'トランジスタ')
            ->assertJsonPath('data.specs.0.spec_type_id', $collectorCurrent->id);

        $this->assertSame($bjtGroup->id, $response->json('data.category_candidates.0.category_id'));
        $this->assertSame($bjtGroup->id, $response->json('data.recommended_spec_groups.0.id'));
        $this->assertSame([$bjtGroup->id], $response->json('data.recommended_group_ids'));
        $this->assertSame($bjtTemplate->id, $response->json('data.template_candidates.0.id'));
        $this->assertSame([$bjtTemplate->id], $response->json('data.recommended_template_ids'));
    }
    /**
     * 目的: createスペックtypeの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: $name, $symbol, $baseUnit, $sortOrder。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    private function createSpecType(string $name, string $symbol, string $baseUnit, int $sortOrder): SpecType
    {
        return SpecType::create([
            'name' => $name,
            'name_ja' => $name,
            'symbol' => $symbol,
            'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
            'base_unit' => $baseUnit,
            'sort_order' => $sortOrder,
        ]);
    }
    /**
     * 目的: createスペックgroupの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: $name, $sortOrder, $specType。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    private function createSpecGroup(string $name, int $sortOrder, SpecType $specType): SpecGroup
    {
        $group = SpecGroup::create([
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);

        $group->specTypes()->attach($specType->id, [
            'sort_order' => $sortOrder,
            'is_recommended' => true,
            'default_unit' => $specType->base_unit,
        ]);

        $specType->update(['owner_spec_group_id' => $group->id]);

        return $group;
    }
    /**
     * 目的: createテンプレートの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: $group, $name, $specType。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    private function createTemplate(SpecGroup $group, string $name, SpecType $specType): SpecTemplate
    {
        $template = SpecTemplate::create([
            'spec_group_id' => $group->id,
            'name' => $name,
            'sort_order' => $group->sort_order,
        ]);

        $template->items()->create([
            'spec_type_id' => $specType->id,
            'sort_order' => 10,
            'default_profile' => 'typ',
            'default_unit' => $specType->base_unit,
            'is_required' => true,
        ]);

        return $template;
    }
}

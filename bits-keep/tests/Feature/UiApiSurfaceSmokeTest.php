<?php

namespace Tests\Feature;

use App\Models\AltiumLibrary;
use App\Models\AnalysisSession;
use App\Models\AuditLog;
use App\Models\Component;
use App\Models\ComponentSeries;
use App\Models\ComponentSupplier;
use App\Models\Location;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\Project;
use App\Models\ProjectSyncRun;
use App\Models\SpecGroup;
use App\Models\SpecType;
use App\Models\StockOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UiApiSurfaceSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_ui_backing_api_endpoints_return_renderable_data_shapes(): void
    {
        $fixture = $this->createUiFixture();
        $component = $fixture['component'];
        $comparisonComponent = $fixture['comparisonComponent'];
        $project = $fixture['project'];
        $componentSeries = $fixture['componentSeries'];
        $analysisSession = $fixture['analysisSession'];

        foreach ([
            '/api/spec-groups?include_archived=1',
            '/api/package-groups?include_archived=1',
            '/api/packages?include_archived=1',
            '/api/spec-types?include_archived=1',
            '/api/suppliers?include_archived=1',
            '/api/locations?include_archived=1',
            '/api/components?per_page=10',
            "/api/components/{$component->id}",
            '/api/component-series',
            "/api/component-series/{$componentSeries->id}",
            "/api/components/{$component->id}/similar",
            '/api/stock-alerts',
            '/api/projects',
            '/api/projects/options',
            '/api/project-businesses',
            '/api/projects/sync/status',
            '/api/projects/sync-runs',
            "/api/projects/{$project->id}",
            "/api/projects/{$project->id}/components",
            "/api/projects/{$project->id}/cost",
            '/api/analysis-sessions',
            "/api/analysis-sessions/{$analysisSession->id}",
            '/api/users',
            '/api/audit-logs',
            '/api/altium/libraries',
            "/api/components/{$component->id}/altium-link",
            '/api/preferences/home_quick_actions',
            "/api/stock-orders/component/{$component->id}/pending",
        ] as $uri) {
            $this->getJson($uri)
                ->assertOk()
                ->assertJsonMissing(['success' => false]);
        }

        $this->getJson('/api/stock-orders?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.component_id', $component->id)
            ->assertJsonPath('data.0.supplier_id', $fixture['supplier']->id);

        $componentListResponse = $this->getJson('/api/components?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.part_number', $component->part_number)
            ->assertJsonPath('data.data.0.package_name', $fixture['package']->name)
            ->assertJsonPath('data.data.0.needs_reorder', true);
        $componentListRow = $componentListResponse->json('data.data.0');
        $this->assertSame(1, $componentListRow['component_suppliers_count']);
        $this->assertSame(1, $componentListRow['inventory_blocks_count']);
        $this->assertSame($fixture['supplier']->name, $componentListRow['cheapest_supplier_name']);
        $this->assertArrayNotHasKey('component_suppliers', $componentListRow);
        $this->assertArrayNotHasKey('inventory_blocks', $componentListRow);

        $this->getJson("/api/components/{$component->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.specs.0.display_name', '抵抗値')
            ->assertJsonPath('data.specs.0.spec_type.name_ja', '抵抗値')
            ->assertJsonPath('data.component_suppliers.0.supplier.name', $fixture['supplier']->name)
            ->assertJsonPath('data.inventory_blocks.0.location.code', $fixture['location']->code);

        $this->getJson('/api/components/compare?ids[]='.$component->id.'&ids[]='.$comparisonComponent->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.components')
            ->assertJsonPath('data.spec_types.0.display_name', '抵抗値');

        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'tolerance_pct' => 5,
            'part_type' => 'R',
            'series' => 'E12',
            'min_elements' => 1,
            'max_elements' => 2,
            'circuit_types' => ['series', 'parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['result' => ['candidates']]]);
    }

    public function test_spec_type_prefixes_preserve_blank_prefix(): void
    {
        $createResponse = $this->postJson('/api/spec-types', [
            'name' => 'Blank Prefix Voltage',
            'name_ja' => '無印接頭辞電圧',
            'value_type' => 'numeric',
            'unit' => 'V',
            'suggest_prefixes' => ['G', '', 'm'],
            'display_prefixes' => ['M', '', 'u'],
            'spec_scope' => 'common',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.suggest_prefixes.0', 'G')
            ->assertJsonPath('data.suggest_prefixes.1', '')
            ->assertJsonPath('data.display_prefixes.1', '');

        $specTypeId = $createResponse->json('data.id');
        $specType = SpecType::findOrFail($specTypeId);
        $this->assertSame(['G', '', 'm'], $specType->suggest_prefixes);
        $this->assertSame(['M', '', 'u'], $specType->display_prefixes);

        $this->getJson("/api/spec-types/{$specTypeId}")
            ->assertOk()
            ->assertJsonPath('data.suggest_prefixes.1', '')
            ->assertJsonPath('data.display_prefixes.1', '');

        $this->putJson("/api/spec-types/{$specTypeId}", [
            'name' => 'Blank Prefix Voltage',
            'name_ja' => '無印接頭辞電圧',
            'value_type' => 'numeric',
            'unit' => 'V',
            'suggest_prefixes' => ['k', ''],
            'display_prefixes' => ['k', ''],
            'spec_scope' => 'common',
        ])
            ->assertOk()
            ->assertJsonPath('data.suggest_prefixes.1', '')
            ->assertJsonPath('data.display_prefixes.1', '');

        $specType->refresh();
        $this->assertSame(['k', ''], $specType->suggest_prefixes);
        $this->assertSame(['k', ''], $specType->display_prefixes);
    }

    public function test_group_local_spec_type_create_stores_master_equivalent_fields(): void
    {
        $group = SpecGroup::create([
            'name' => 'トランジスタ',
            'sort_order' => 10,
        ]);

        $response = $this->postJson('/api/spec-types', [
            'name' => 'コレクタ電流',
            'name_ja' => 'コレクタ電流',
            'name_en' => 'Collector Current',
            'symbol' => 'I_C',
            'description' => '部品詳細のスペック編集から追加したスペック詳細',
            'value_type' => 'numeric',
            'unit' => 'A',
            'suggest_prefixes' => ['k', '', 'm', 'u'],
            'display_prefixes' => ['', 'm', 'u'],
            'spec_scope' => 'group_local',
            'owner_spec_group_id' => $group->id,
            'aliases' => [
                ['alias' => 'IC'],
                ['alias' => 'Collector Current'],
            ],
            'sort_order' => 40,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name_ja', 'コレクタ電流')
            ->assertJsonPath('data.name_en', 'Collector Current')
            ->assertJsonPath('data.symbol', 'I_C')
            ->assertJsonPath('data.description', '部品詳細のスペック編集から追加したスペック詳細')
            ->assertJsonPath('data.base_unit', 'A')
            ->assertJsonPath('data.suggest_prefixes', ['k', '', 'm', 'u'])
            ->assertJsonPath('data.display_prefixes', ['', 'm', 'u'])
            ->assertJsonPath('data.owner_spec_group_id', $group->id);

        $specType = SpecType::with(['aliases', 'units', 'specGroups'])->findOrFail($response->json('data.id'));
        $this->assertSame('group_local', $specType->spec_scope);
        $this->assertSame($group->id, $specType->owner_spec_group_id);
        $this->assertSame(['k', '', 'm', 'u'], $specType->suggest_prefixes);
        $this->assertSame(['', 'm', 'u'], $specType->display_prefixes);
        $this->assertSame('A', $specType->units->first()?->unit);
        $this->assertSame(['IC', 'Collector Current'], $specType->aliases->pluck('alias')->all());
        $this->assertTrue($specType->specGroups->contains('id', $group->id));
    }

    public function test_byte_bit_spec_type_prefixes_enforce_decimal_or_iec_group(): void
    {
        $iecResponse = $this->postJson('/api/spec-types', [
            'name' => 'Memory size',
            'name_ja' => 'メモリ容量',
            'value_type' => 'numeric',
            'unit' => 'B',
            'suggest_prefixes' => ['Mi', 'Ki', ''],
            'display_prefixes' => ['Mi', 'Ki', ''],
            'spec_scope' => 'common',
        ]);

        $iecResponse
            ->assertCreated()
            ->assertJsonPath('data.suggest_prefixes', ['Mi', 'Ki', ''])
            ->assertJsonPath('data.display_prefixes', ['Mi', 'Ki', '']);

        $mixResponse = $this->postJson('/api/spec-types', [
            'name' => 'Mixed memory size',
            'name_ja' => '混在メモリ容量',
            'value_type' => 'numeric',
            'unit' => 'B',
            'suggest_prefixes' => ['M', 'Mi', ''],
            'spec_scope' => 'common',
        ]);

        $mixResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('suggest_prefixes');

        $fractionalResponse = $this->postJson('/api/spec-types', [
            'name' => 'Fractional memory size',
            'name_ja' => '小数メモリ容量',
            'value_type' => 'numeric',
            'unit' => 'bit',
            'display_prefixes' => ['m', ''],
            'spec_scope' => 'common',
        ]);

        $fractionalResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('display_prefixes');

        $nonByteIecResponse = $this->postJson('/api/spec-types', [
            'name' => 'IEC Voltage',
            'name_ja' => 'IEC電圧',
            'value_type' => 'numeric',
            'unit' => 'V',
            'suggest_prefixes' => ['Ki', ''],
            'spec_scope' => 'common',
        ]);

        $nonByteIecResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('suggest_prefixes');
    }

    public function test_spec_type_allows_same_display_name_with_different_symbols(): void
    {
        $first = $this->postJson('/api/spec-types', [
            'name' => '電源電圧',
            'name_ja' => '電源電圧',
            'symbol' => 'VDD',
            'value_type' => 'numeric',
            'unit' => 'V',
            'spec_scope' => 'common',
        ]);
        $second = $this->postJson('/api/spec-types', [
            'name' => '電源電圧',
            'name_ja' => '電源電圧',
            'symbol' => 'VCC',
            'value_type' => 'numeric',
            'unit' => 'V',
            'spec_scope' => 'common',
        ]);

        $first->assertCreated()->assertJsonPath('data.name_ja', '電源電圧')->assertJsonPath('data.symbol', 'VDD');
        $second->assertCreated()->assertJsonPath('data.name_ja', '電源電圧')->assertJsonPath('data.symbol', 'VCC');

        $this->assertDatabaseCount('spec_types', 2);
        $this->assertSame(
            ['VDD', 'VCC'],
            SpecType::query()->where('name_ja', '電源電圧')->orderBy('id')->pluck('symbol')->all()
        );
    }

    public function test_spec_types_preserve_tolerance_kind_settings_and_kind_filters(): void
    {
        $normalResponse = $this->postJson('/api/spec-types', [
            'name' => '定格容量',
            'name_ja' => '定格容量',
            'value_type' => 'numeric',
            'unit' => 'uF',
            'spec_scope' => 'common',
        ]);

        $normalResponse
            ->assertCreated()
            ->assertJsonPath('data.spec_kind', 'normal')
            ->assertJsonPath('data.base_unit', 'uF');

        $toleranceSettings = [
            'default_mode' => 'symmetric',
            'default_unit' => '%',
            'allowed_units' => ['%', 'ppm'],
            'grade_options' => [
                ['label' => 'F', 'value' => 1, 'unit' => '%'],
                ['label' => 'G', 'value' => 2, 'unit' => '%'],
                ['label' => 'J', 'value' => 5, 'unit' => '%'],
                ['label' => 'K', 'value' => 10, 'unit' => '%'],
                ['label' => 'M', 'value' => 20, 'unit' => '%'],
            ],
        ];

        $toleranceResponse = $this->postJson('/api/spec-types', [
            'name' => '容量許容差',
            'name_ja' => '容量許容差',
            'value_type' => 'numeric',
            'unit' => '%',
            'spec_scope' => 'common',
            'spec_kind' => 'tolerance',
            'tolerance_settings' => $toleranceSettings,
        ]);

        $toleranceResponse
            ->assertCreated()
            ->assertJsonPath('data.name', '容量許容差')
            ->assertJsonPath('data.name_ja', '容量許容差')
            ->assertJsonPath('data.spec_scope', 'common')
            ->assertJsonPath('data.spec_kind', 'tolerance')
            ->assertJsonPath('data.base_unit', '%')
            ->assertJsonPath('data.tolerance_settings.default_mode', 'symmetric')
            ->assertJsonPath('data.tolerance_settings.default_unit', '%')
            ->assertJsonPath('data.tolerance_settings.allowed_units', ['%', 'ppm'])
            ->assertJsonPath('data.tolerance_settings.grade_options.0.label', 'F');

        $normalId = $normalResponse->json('data.id');
        $toleranceId = $toleranceResponse->json('data.id');

        $normalSpecType = SpecType::findOrFail($normalId);
        $toleranceSpecType = SpecType::findOrFail($toleranceId);

        $this->assertSame('normal', $normalSpecType->spec_kind);
        $this->assertNull($normalSpecType->tolerance_settings);
        $this->assertSame('tolerance', $toleranceSpecType->spec_kind);
        $this->assertEquals($toleranceSettings, $toleranceSpecType->tolerance_settings);

        $this->getJson('/api/spec-types?summary=1&scope=common')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $toleranceId,
                'name' => '容量許容差',
                'name_ja' => '容量許容差',
                'spec_kind' => 'tolerance',
                'tolerance_settings' => $toleranceSettings,
            ]);

        $normalList = $this->getJson('/api/spec-types?scope=common&kind=normal')
            ->assertOk()
            ->json('data');
        $toleranceList = $this->getJson('/api/spec-types?scope=common&kind=tolerance')
            ->assertOk()
            ->json('data');

        $this->assertContains($normalId, collect($normalList)->pluck('id')->all());
        $this->assertNotContains($toleranceId, collect($normalList)->pluck('id')->all());
        $this->assertContains($toleranceId, collect($toleranceList)->pluck('id')->all());
        $this->assertNotContains($normalId, collect($toleranceList)->pluck('id')->all());
    }

    public function test_authenticated_pages_render_without_backend_errors(): void
    {
        $fixture = $this->createUiFixture();
        $component = $fixture['component'];

        $vuePages = [
            '/dashboard',
            '/components',
            '/components/create',
            "/components/{$component->id}",
            "/components/{$component->id}/edit",
            '/component-series',
            '/component-compare',
            '/master',
            '/locations',
            '/stock-alert',
            '/stock-orders',
            '/stock-in',
            '/suppliers',
            '/projects',
            '/settings/integrations',
            '/settings/home',
            '/tools/calc',
            '/tools/design',
            '/tools/network',
            '/users',
            '/audit-logs',
            '/csv-import',
            '/altium',
            '/backup',
        ];
        $staticPages = [
            '/functions',
            '/profile',
            '/help',
        ];

        foreach ($vuePages as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('data-page', false)
                ->assertDontSee('Page module not found', false)
                ->assertDontSee('Undefined variable', false)
                ->assertDontSee('Internal Server Error', false);
        }

        foreach ($staticPages as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertDontSee('Page module not found', false)
                ->assertDontSee('Undefined variable', false)
                ->assertDontSee('Internal Server Error', false);
        }
    }

    public function test_network_tool_page_exposes_updated_design_surface(): void
    {
        $this->get('/tools/network')
            ->assertOk()
            ->assertSee('data-page="resistance-calc"', false)
            ->assertSee('比較トレイ', false)
            ->assertSee('可変抵抗 + 固定抵抗', false)
            ->assertSee('基準抵抗値', false)
            ->assertSee('要求範囲', false)
            ->assertSee('採用候補', false)
            ->assertSee('理想値', false)
            ->assertSee('在庫値', false);

        $this->get('/functions')
            ->assertOk()
            ->assertSee('直列・並列・混在・分圧・在庫値・可変抵抗', false);
    }

    public function test_network_tool_exposes_divider_as_top_level_tab_with_submodes_and_no_load_buttons(): void
    {
        $response = $this->get('/tools/network')
            ->assertOk()
            ->assertSee('data-page="resistance-calc"', false);

        $html = $response->getContent();
        $blade = file_get_contents(resource_path('views/app/resistance-calc.blade.php'));
        $script = file_get_contents(resource_path('js/pages/resistance-calc.js'));

        $this->assertMatchesRegularExpression("/value:\\s*'network'\\s*,\\s*label:\\s*'ネットワーク探索'/u", $script);
        $this->assertMatchesRegularExpression("/value:\\s*'divider'\\s*,\\s*label:\\s*'分圧'/u", $script);
        $this->assertMatchesRegularExpression("/value:\\s*'variable'\\s*,\\s*label:\\s*'可変抵抗'/u", $script);
        $this->assertStringContainsString("activeMode === 'divider'", $blade);
        $this->assertStringNotContainsString('分圧VR', $html.$blade.$script);
        $this->assertStringNotContainsString('divider-variable', $html.$blade.$script);
        $this->assertStringNotContainsString("activeMode === 'divider-variable'", $blade);
        $this->assertStringNotContainsString("activeMode === 'network' && isDividerVariableMode", $blade);

        $this->assertMatchesRegularExpression("/value:\\s*'fixed'\\s*,\\s*label:\\s*'分圧'/u", $script);
        $this->assertMatchesRegularExpression("/value:\\s*'variable'\\s*,\\s*label:\\s*'VR分圧'/u", $script);

        preg_match_all('/<button\b[\s\S]*?<\/button>/u', $blade, $buttonMatches);
        $buttons = $buttonMatches[0];
        $infiniteResistanceButtons = array_filter($buttons, static fn (string $button): bool => str_contains($button, '∞'));
        $zeroCurrentButtons = array_filter($buttons, static fn (string $button): bool => str_contains($button, '0A'));

        $this->assertGreaterThanOrEqual(2, count($infiniteResistanceButtons), '分圧 and VR分圧 must each expose a load-resistance infinity button.');
        $this->assertGreaterThanOrEqual(2, count($zeroCurrentButtons), '分圧 and VR分圧 must each expose a load-current zero button.');
        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'setLoadResistanceInfinite('));
        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'setLoadCurrentZero('));
    }

    public function test_tolerance_spec_value_candidates_use_combobox_surface(): void
    {
        $fixture = $this->createUiFixture();

        $this->get('/components/create')
            ->assertOk()
            ->assertSee('tolerance-combobox', false)
            ->assertSee('toggleToleranceGradeMenu', false)
            ->assertSee('selectToleranceGradeOption', false)
            ->assertDontSee('mt-2 flex flex-wrap gap-1', false);

        $this->get("/components/{$fixture['component']->id}")
            ->assertOk()
            ->assertSee('tolerance-combobox', false)
            ->assertSee('toggleToleranceGradeMenu', false)
            ->assertSee('selectToleranceGradeOption', false)
            ->assertDontSee('mt-2 flex flex-wrap gap-1', false);
    }

    public function test_components_default_order_uses_catalog_context_not_recent_update(): void
    {
        $fixture = $this->createUiFixture();

        $lateCategory = SpecGroup::create([
            'name' => '後方カテゴリ',
            'description' => 'late catalog bucket',
            'sort_order' => 99,
        ]);
        $latePackageGroup = PackageGroup::create([
            'name' => '後方パッケージ分類',
            'description' => 'late catalog bucket',
            'sort_order' => 99,
        ]);
        $latePackage = Package::create([
            'package_group_id' => $latePackageGroup->id,
            'name' => 'ZZ-LATE-PKG',
            'description' => 'late package',
            'sort_order' => 99,
        ]);
        $recentComponent = $this->createComponentFixture(
            $lateCategory,
            $latePackage,
            $fixture['specType'],
            $fixture['supplier'],
            $fixture['location'],
            'AAA-RECENT-LATE-CATALOG',
            100
        );
        $recentComponent->forceFill(['updated_at' => now()->addDay()])->save();

        $this->getJson('/api/components?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.data.0.part_number', $fixture['component']->part_number);

        $this->getJson('/api/components?per_page=10&sort=updated_at')
            ->assertOk()
            ->assertJsonPath('data.data.0.part_number', $recentComponent->part_number);
    }

    public function test_components_part_number_sort_uses_natural_numeric_order(): void
    {
        $fixture = $this->createUiFixture();

        $this->createComponentFixture(
            $fixture['category'],
            $fixture['package'],
            $fixture['specType'],
            $fixture['supplier'],
            $fixture['location'],
            '2SC1815',
            1815
        );
        $this->createComponentFixture(
            $fixture['category'],
            $fixture['package'],
            $fixture['specType'],
            $fixture['supplier'],
            $fixture['location'],
            '2SC945',
            945
        );

        $this->getJson('/api/components?per_page=10&sort=part_number')
            ->assertOk()
            ->assertJsonPath('data.data.0.part_number', '2SC945')
            ->assertJsonPath('data.data.1.part_number', '2SC1815');

        $this->getJson('/api/components?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.data.0.part_number', '2SC945')
            ->assertJsonPath('data.data.1.part_number', '2SC1815');
    }

    public function test_spec_suggestions_are_scoped_to_selected_spec_groups(): void
    {
        $fixture = $this->createUiFixture();
        $category = $fixture['category'];
        $suggestedSpecType = $fixture['specType'];

        $manualSpecType = SpecType::create([
            'name' => 'ゲイン帯域幅',
            'name_ja' => 'ゲイン帯域幅',
            'name_en' => 'Gain bandwidth product',
            'symbol' => 'GBW',
            'base_unit' => 'Hz',
            'sort_order' => 20,
        ]);

        $suggestedGroup = $category;
        $suggestedGroup->specTypes()->attach($suggestedSpecType->id, ['sort_order' => 10]);

        $manualGroup = SpecGroup::create([
            'name' => 'UI手動選択分類',
            'description' => 'manual fallback',
            'sort_order' => 20,
        ]);
        $manualGroup->specTypes()->attach($manualSpecType->id, ['sort_order' => 10]);

        $response = $this->getJson("/api/spec-suggestions?category_ids[]={$category->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['name' => $suggestedGroup->name, 'is_suggested' => true])
            ->assertJsonMissing(['name' => 'UI手動選択分類'])
            ->assertJsonPath('data.recommended_group_ids.0', $suggestedGroup->id);

        $topLevelSpecTypeIds = collect($response->json('data.spec_types'))->pluck('id')->all();
        $this->assertContains($suggestedSpecType->id, $topLevelSpecTypeIds);
        $this->assertNotContains($manualSpecType->id, $topLevelSpecTypeIds);
    }

    public function test_spec_group_index_returns_context_counts_for_sidebar(): void
    {
        $group = SpecGroup::create([
            'name' => 'カウント確認分類',
            'description' => 'sidebar count fixture',
            'sort_order' => 10,
        ]);

        $localA = SpecType::create([
            'name' => 'カウント個別A',
            'name_ja' => 'カウント個別A',
            'base_unit' => 'Ω',
            'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
            'owner_spec_group_id' => $group->id,
            'spec_kind' => SpecType::KIND_NORMAL,
        ]);
        $localB = SpecType::create([
            'name' => 'カウント個別B',
            'name_ja' => 'カウント個別B',
            'base_unit' => 'V',
            'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
            'owner_spec_group_id' => $group->id,
            'spec_kind' => SpecType::KIND_NORMAL,
        ]);
        $commonA = SpecType::create([
            'name' => 'カウント共通A',
            'name_ja' => 'カウント共通A',
            'base_unit' => 'Hz',
            'spec_scope' => SpecType::SCOPE_COMMON,
            'spec_kind' => SpecType::KIND_NORMAL,
        ]);
        $commonB = SpecType::create([
            'name' => 'カウント共通B',
            'name_ja' => 'カウント共通B',
            'base_unit' => 'A',
            'spec_scope' => SpecType::SCOPE_COMMON,
            'spec_kind' => SpecType::KIND_NORMAL,
        ]);
        $tolerance = SpecType::create([
            'name' => 'カウント許容差',
            'name_ja' => 'カウント許容差',
            'base_unit' => '%',
            'spec_scope' => SpecType::SCOPE_COMMON,
            'spec_kind' => SpecType::KIND_TOLERANCE,
        ]);
        $localTolerance = SpecType::create([
            'name' => 'カウント個別許容差',
            'name_ja' => 'カウント個別許容差',
            'base_unit' => '%',
            'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
            'owner_spec_group_id' => $group->id,
            'spec_kind' => SpecType::KIND_TOLERANCE,
        ]);

        $group->specTypes()->attach([
            $localA->id => ['sort_order' => 10],
            $localB->id => ['sort_order' => 20],
            $commonA->id => ['sort_order' => 30],
            $commonB->id => ['sort_order' => 40],
            $tolerance->id => ['sort_order' => 50],
            $localTolerance->id => ['sort_order' => 60],
        ]);
        $group->templates()->create([
            'name' => 'カウント確認テンプレート',
            'sort_order' => 10,
        ]);

        $this->getJson('/api/spec-groups?include_archived=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'カウント確認分類')
            ->assertJsonPath('data.0.usage_count', 6)
            ->assertJsonPath('data.0.local_candidate_count', 2)
            ->assertJsonPath('data.0.common_candidate_count', 2)
            ->assertJsonPath('data.0.tolerance_candidate_count', 1)
            ->assertJsonPath('data.0.owned_spec_type_count', 2)
            ->assertJsonPath('data.0.template_count', 1);

        $this->getJson("/api/spec-groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.usage_count', 6)
            ->assertJsonPath('data.local_candidate_count', 2)
            ->assertJsonPath('data.common_candidate_count', 2)
            ->assertJsonPath('data.tolerance_candidate_count', 1)
            ->assertJsonPath('data.owned_spec_type_count', 2)
            ->assertJsonPath('data.template_count', 1);
    }

    public function test_spec_groups_remain_available_before_component_series_tables_exist(): void
    {
        SpecGroup::create([
            'name' => '抵抗',
            'description' => 'migration guard',
            'sort_order' => 10,
        ]);

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('component_series_values');
        Schema::dropIfExists('component_series_value_policies');
        Schema::dropIfExists('component_series');
        Schema::enableForeignKeyConstraints();

        $this->getJson('/api/spec-groups?include_archived=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', '抵抗')
            ->assertJsonPath('data.0.series_count', 0)
            ->assertJsonPath('data.0.local_candidate_count', 0)
            ->assertJsonPath('data.0.common_candidate_count', 0)
            ->assertJsonPath('data.0.tolerance_candidate_count', 0)
            ->assertJsonPath('data.0.owned_spec_type_count', 0)
            ->assertJsonPath('data.0.series_management_mode', 'single');
    }

    /**
     * @return array<string, mixed>
     */
    private function createUiFixture(): array
    {
        $category = SpecGroup::create([
            'name' => 'UI確認カテゴリ',
            'description' => 'UI smoke',
            'sort_order' => 10,
        ]);
        $packageGroup = PackageGroup::create([
            'name' => 'UI確認パッケージ分類',
            'description' => 'UI smoke',
            'sort_order' => 10,
        ]);
        $package = Package::create([
            'package_group_id' => $packageGroup->id,
            'name' => 'UI-SOT-23',
            'description' => 'UI smoke package',
            'sort_order' => 10,
        ]);
        $specType = SpecType::create([
            'name' => '抵抗値',
            'name_ja' => '抵抗値',
            'name_en' => 'Resistance',
            'symbol' => 'R',
            'base_unit' => 'Ω',
            'suggest_prefixes' => ['k', '', 'm'],
            'display_prefixes' => ['k', '', 'm'],
            'description' => 'UI smoke spec type',
            'sort_order' => 10,
        ]);
        $specType->units()->createMany([
            ['unit' => 'kΩ', 'factor' => 1000, 'sort_order' => 10],
            ['unit' => 'Ω', 'factor' => 1, 'sort_order' => 20],
            ['unit' => 'mΩ', 'factor' => 0.001, 'sort_order' => 30],
        ]);
        $specType->aliases()->createMany([
            ['alias' => 'resistance', 'locale' => 'en', 'kind' => 'name', 'sort_order' => 10],
            ['alias' => 'R', 'locale' => null, 'kind' => 'symbol', 'sort_order' => 20],
        ]);

        $supplier = Supplier::create([
            'name' => 'UI確認商社',
            'url' => 'https://example.test/supplier',
            'color' => '#22c55e',
            'lead_days' => 3,
            'free_shipping_threshold' => 3000,
            'note' => 'UI smoke supplier',
        ]);
        $location = Location::create([
            'code' => 'UI-A-1',
            'name' => 'UI確認棚',
            'group' => 'UI棚',
            'sort_order' => 10,
        ]);
        $schLibrary = AltiumLibrary::create([
            'name' => 'UI SchLib',
            'type' => 'SchLib',
            'path' => 'C:/ui/parts.SchLib',
            'component_count' => 1,
            'note' => 'UI smoke',
        ]);
        $pcbLibrary = AltiumLibrary::create([
            'name' => 'UI PcbLib',
            'type' => 'PcbLib',
            'path' => 'C:/ui/parts.PcbLib',
            'component_count' => 1,
            'note' => 'UI smoke',
        ]);

        $component = $this->createComponentFixture(
            $category,
            $package,
            $specType,
            $supplier,
            $location,
            'UI-SMOKE-RES-4K7',
            4700
        );
        $comparisonComponent = $this->createComponentFixture(
            $category,
            $package,
            $specType,
            $supplier,
            $location,
            'UI-SMOKE-RES-5K1',
            5100
        );

        $component->altiumLink()->create([
            'sch_library_id' => $schLibrary->id,
            'sch_symbol' => 'R',
            'pcb_library_id' => $pcbLibrary->id,
            'pcb_footprint' => 'R_0603',
        ]);

        $componentSeries = ComponentSeries::create([
            'spec_group_id' => $category->id,
            'value_spec_type_id' => $specType->id,
            'package_id' => $package->id,
            'manufacturer' => 'Codex Test',
            'name' => 'UI確認抵抗シリーズ',
            'status' => 'active',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        $componentSeries->policy()->create([
            'value_set_type' => 'hybrid_series',
            'primary_series' => 'E12',
            'extra_series' => ['E24'],
            'extra_values' => ['4.99'],
            'excluded_values' => [],
            'unit' => 'Ω',
            'decade_min' => 0,
            'decade_max' => 0,
        ]);
        $componentSeries->values()->create([
            'value_text' => '4.7kΩ',
            'value_key' => 'ω|4700',
            'value_numeric' => 4700,
            'unit' => 'Ω',
            'origin' => 'manual',
            'is_enabled' => true,
            'is_stocked' => true,
            'materialized_component_id' => $component->id,
        ]);
        $component->forceFill([
            'component_series_id' => $componentSeries->id,
            'component_series_value_id' => $componentSeries->values()->first()->id,
        ])->save();

        StockOrder::create([
            'component_id' => $component->id,
            'supplier_id' => $supplier->id,
            'quantity' => 50,
            'status' => 'pending',
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(3)->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $project = Project::create([
            'name' => 'UI確認案件',
            'description' => 'UI smoke project',
            'status' => 'active',
            'color' => '#3b82f6',
            'business_code' => '010',
            'business_name' => 'UI事業',
            'source_type' => 'local',
            'source_key' => 'ui-smoke-project',
            'is_editable' => true,
            'created_by' => $this->admin->id,
        ]);
        $project->components()->attach($component->id, ['required_qty' => 3]);

        $analysisSession = AnalysisSession::create([
            'tool_id' => 'power',
            'title' => 'UI確認 電源余裕',
            'verdict' => 'PASS',
            'summary' => 'UI smoke analysis session',
            'input_payload' => ['supply_w' => 10],
            'result_payload' => ['margin_w' => 3],
            'candidate_links' => [['label' => $component->part_number, 'component_id' => $component->id]],
            'project_id' => $project->id,
            'component_id' => $component->id,
            'bom_line_key' => 'ui-smoke-line',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ProjectSyncRun::create([
            'triggered_by' => $this->admin->id,
            'status' => 'success',
            'synced_count' => 1,
            'error_count' => 0,
            'business_results' => [['business_code' => '010', 'status' => 'success']],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'created',
            'resource_type' => 'component',
            'resource_id' => $component->id,
            'diff' => ['part_number' => $component->part_number],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'ui-api-smoke',
            'created_at' => now(),
        ]);

        return compact(
            'category',
            'packageGroup',
            'package',
            'specType',
            'supplier',
            'location',
            'component',
            'comparisonComponent',
            'project',
            'componentSeries',
            'analysisSession'
        );
    }

    private function createComponentFixture(
        SpecGroup $category,
        Package $package,
        SpecType $specType,
        Supplier $supplier,
        Location $location,
        string $partNumber,
        float $resistanceValue
    ): Component {
        $component = Component::create([
            'part_number' => $partNumber,
            'manufacturer' => 'Codex Test',
            'common_name' => "{$resistanceValue}Ω UI確認抵抗",
            'description' => 'UI smoke component',
            'procurement_status' => 'active',
            'quantity_new' => 2,
            'quantity_used' => 0,
            'threshold_new' => 10,
            'threshold_used' => 0,
            'primary_location_id' => $location->id,
            'package_id' => $package->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        $component->categories()->sync([$category->id]);
        $component->locations()->sync([$location->id]);
        $component->specs()->create([
            'spec_type_id' => $specType->id,
            'display_name' => '抵抗値',
            'value' => (string) $resistanceValue,
            'unit' => 'Ω',
            'value_profile' => 'typ',
            'value_mode' => 'single',
            'value_numeric' => $resistanceValue,
            'value_numeric_typ' => $resistanceValue,
            'normalized_unit' => 'Ω',
        ]);

        $componentSupplier = ComponentSupplier::create([
            'component_id' => $component->id,
            'supplier_id' => $supplier->id,
            'supplier_part_number' => $partNumber.'-DK',
            'product_url' => 'https://example.test/parts/'.$partNumber,
            'purchase_unit' => 'tape',
            'unit_price' => 1.25,
            'price_updated_at' => now(),
            'is_preferred' => true,
        ]);
        $componentSupplier->priceBreaks()->createMany([
            ['min_qty' => 1, 'unit_price' => 1.25],
            ['min_qty' => 100, 'unit_price' => 0.9],
        ]);

        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'tape',
            'condition' => 'new',
            'quantity' => 2,
            'lot_number' => 'UI-LOT',
        ]);

        return $component;
    }
}

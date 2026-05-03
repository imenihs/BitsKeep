<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\SpecGroup;
use App\Models\SpecType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UiApiSurfaceSmokeTest extends TestCase
{
    use RefreshDatabase;
    use UiApiSurfaceSmoke\ChecksDesignToolsSurface;
    use UiApiSurfaceSmoke\CreatesUiApiSurfaceFixtures;

    private User $admin;

    /**
     * 目的: UI/APIスモークテストを管理者ログイン状態で実行できるようにする。
     * 機能: 各テストごとに管理者ユーザーを作成し、Laravelの認証セッションへ設定する。
     * 入力: なし。PHPUnitのsetUpライフサイクルから呼ばれる。
     * 出力: なし。
     * 動作条件: RefreshDatabase によりテストDBが初期化されていること。
     * 副作用: users テーブルへ管理者ユーザーを1件作成し、テストクライアントの認証状態を変更する。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($this->admin);
    }

    /**
     * 目的: 主要UIが依存するAPI群の基本レスポンス形状を固定する。
     * 機能: 部品、マスタ、案件、解析保存、発注、Altium連携の読取APIを横断して200応答と代表フィールドを確認する。
     * 入力: createUiFixture で作成した部品・案件・解析セッションなどのテストデータ。
     * 出力: アサーション結果。
     * 動作条件: 管理者として認証済みで、テスト用DBにフィクスチャが作成できること。
     * 副作用: テストDBへ部品関連データを作成し、HTTP GET/POSTリクエストを発行する。
     */
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
            'element_tolerance_pct' => 5,
            'part_type' => 'R',
            'series' => 'E12',
            'min_elements' => 1,
            'max_elements' => 2,
            'circuit_types' => ['series', 'parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'result' => [
                        'candidates' => [
                            '*' => [
                                'low_equivalent_value',
                                'low_equivalent_display',
                                'high_equivalent_value',
                                'high_equivalent_display',
                                'rss_low_equivalent_value',
                                'rss_low_equivalent_display',
                                'rss_high_equivalent_value',
                                'rss_high_equivalent_display',
                                'rss_max_target_deviation_pct',
                                'rss_max_target_deviation_display',
                                'max_target_deviation_pct',
                                'max_target_deviation_display',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * 目的: ネットワーク探索画面に採用素子許容差と範囲表示が残っていることを固定する。
     * 機能: /tools/network のHTMLから許容差入力と結果表示ラベルを確認する。
     * 入力: なし。
     * 出力: アサーション結果。
     * 動作条件: 管理者として認証済みで画面が描画できること。
     * 副作用: テスト用HTTP GETを1回発行する。
     */
    public function test_resistance_calc_ui_exposes_adopted_element_tolerance_controls_and_range_surface(): void
    {
        $this->get('/tools/network')
            ->assertOk()
            ->assertSee('element_tolerance_pct', false)
            ->assertSee('採用素子許容差', false)
            ->assertSee('独立RSS目安', false)
            ->assertSee('コーナー', false)
            ->assertSee('最大偏差', false)
            ->assertSee('R1許容差', false)
            ->assertSee('R2許容差', false)
            ->assertSee('固定抵抗許容差', false)
            ->assertSee('VR許容差', false)
            ->assertSee('R上許容差', false)
            ->assertSee('R下許容差', false)
            ->assertSee('素子誤差範囲', false);
    }

    /**
     * 目的: スペック詳細の接頭辞候補で空文字の基準単位選択が消えないことを固定する。
     * 機能: 作成、詳細取得、更新後の suggest/display prefixes をDB値まで確認する。
     * 入力: 空文字を含む接頭辞配列。
     * 出力: アサーション結果。
     * 動作条件: spec-types API が管理者権限で利用可能であること。
     * 副作用: spec_types と関連単位データをテストDBへ作成/更新する。
     */
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

    /**
     * 目的: 部品分類ローカルのスペック詳細作成でもマスタ相当の項目が保存されることを固定する。
     * 機能: 名称、英語名、記号、説明、単位、接頭辞、別名、分類紐付けを確認する。
     * 入力: owner_spec_group_id 付きの spec-types 作成payload。
     * 出力: アサーション結果。
     * 動作条件: 対象の部品分類が事前に作成済みであること。
     * 副作用: spec_groups、spec_types、aliases、units、中間テーブルへ検証用レコードを作成する。
     */
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

    /**
     * 目的: 接頭辞付き単位でスペック詳細を作っても基準単位へ正規化されることを固定する。
     * 機能: μF 入力から base_unit、候補接頭辞、表示接頭辞、保存単位を確認する。
     * 入力: unit=μF の spec-types 作成payload。
     * 出力: アサーション結果。
     * 動作条件: 単位正規化サービスがAPI保存処理で呼ばれること。
     * 副作用: spec_types と spec_type_units をテストDBへ作成する。
     */
    public function test_spec_type_create_normalizes_prefixed_unit_to_base_unit(): void
    {
        $response = $this->postJson('/api/spec-types', [
            'name' => '容量',
            'name_ja' => '容量',
            'value_type' => 'numeric',
            'unit' => 'μF',
            'spec_scope' => 'common',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.base_unit', 'F')
            ->assertJsonPath('data.suggest_prefixes', ['u'])
            ->assertJsonPath('data.display_prefixes', ['u'])
            ->assertJsonPath('data.units.0.unit', 'F');
    }

    /**
     * 目的: Byte/bit系スペックの接頭辞がIEC系と10進系で混在しないことを固定する。
     * 機能: 許可例と不許可例をAPIバリデーションで確認する。
     * 入力: B/bit/V 単位と複数の接頭辞配列。
     * 出力: アサーション結果。
     * 動作条件: spec-types API の接頭辞バリデーションが有効であること。
     * 副作用: 許可ケースのみ spec_types をテストDBへ作成する。
     */
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

    /**
     * 目的: 同じ表示名でも記号が異なるスペック詳細を別物として登録できることを固定する。
     * 機能: VDD と VCC の電源電圧を作成し、2件保存されることを確認する。
     * 入力: 同じ name/name_ja と異なる symbol を持つ作成payload。
     * 出力: アサーション結果。
     * 動作条件: 一意制約が表示名単独で閉じていないこと。
     * 副作用: spec_types を2件テストDBへ作成する。
     */
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

    /**
     * 目的: 許容差スペック詳細の設定保存とkindフィルタを固定する。
     * 機能: normal/tolerance の作成、設定JSON、一覧フィルタの出し分けを確認する。
     * 入力: 通常スペックと tolerance_settings 付きスペックの作成payload。
     * 出力: アサーション結果。
     * 動作条件: spec_kind と tolerance_settings がモデルで永続化されること。
     * 副作用: spec_types をテストDBへ作成する。
     */
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
            ->assertJsonPath('data.base_unit', 'F');

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

    /**
     * 目的: 認証後に主要画面がバックエンド例外なしで描画できることを固定する。
     * 機能: Vueページと静的ページを巡回し、画面モジュール欠落や未定義変数を検出する。
     * 入力: createUiFixture で作った詳細画面用部品。
     * 出力: アサーション結果。
     * 動作条件: 管理者として認証済みでルート定義が存在すること。
     * 副作用: テストDBへフィクスチャを作成し、複数のHTTP GETを発行する。
     */
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

    /**
     * 目的: ネットワーク探索専用画面の設計入力・評価表示が欠落しないことを固定する。
     * 機能: /tools/network の主要ラベルと、統合済み旧導線が出ないことを確認する。
     * 入力: なし。
     * 出力: アサーション結果。
     * 動作条件: Bladeがネットワーク探索ページを描画できること。
     * 副作用: テスト用HTTP GETを1回発行する。
     */
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

    /**
     * 目的: 分圧がトップレベルタブとして扱われ、不要な負荷切替ボタンが出ないことを固定する。
     * 機能: 通常分圧、VR分圧、負荷条件、許容差表示のラベルを確認する。
     * 入力: なし。
     * 出力: アサーション結果。
     * 動作条件: /tools/network が最新UI構成で描画されること。
     * 副作用: テスト用HTTP GETを1回発行する。
     */
    public function test_network_tool_exposes_divider_as_top_level_tab_with_submodes_and_no_load_buttons(): void
    {
        $response = $this->get('/tools/network')
            ->assertOk()
            ->assertSee('data-page="resistance-calc"', false);

        $html = $response->getContent();
        $blade = file_get_contents(resource_path('views/app/resistance-calc.blade.php'));
        $script = file_get_contents(resource_path('js/pages/resistance-calc.js'));
        $coreScript = file_get_contents(resource_path('js/pages/resistance-calc/core.js'));

        $this->assertMatchesRegularExpression("/value:\\s*'network'\\s*,\\s*label:\\s*'ネットワーク探索'/u", $coreScript);
        $this->assertMatchesRegularExpression("/value:\\s*'divider'\\s*,\\s*label:\\s*'分圧'/u", $coreScript);
        $this->assertMatchesRegularExpression("/value:\\s*'variable'\\s*,\\s*label:\\s*'可変抵抗'/u", $coreScript);
        $this->assertStringContainsString("activeMode === 'divider'", $blade);
        $this->assertStringNotContainsString('分圧VR', $html.$blade.$script.$coreScript);
        $this->assertStringNotContainsString('divider-variable', $html.$blade.$script.$coreScript);
        $this->assertStringNotContainsString("activeMode === 'divider-variable'", $blade);
        $this->assertStringNotContainsString("activeMode === 'network' && isDividerVariableMode", $blade);

        $this->assertMatchesRegularExpression("/value:\\s*'fixed'\\s*,\\s*label:\\s*'VR調整なし'/u", $coreScript);
        $this->assertMatchesRegularExpression("/value:\\s*'variable'\\s*,\\s*label:\\s*'VR調整あり'/u", $coreScript);
        $this->assertStringContainsString('output_low_ratio_raw', $script);
        $this->assertStringContainsString('total_res_min_raw', $script);
        $this->assertStringContainsString('output_mode: form.divider_target_mode', $script);
        $this->assertStringContainsString('sourceCurrentDisplay', $script);
        $this->assertStringContainsString('candidate.source_current_display', $blade);
        $this->assertStringContainsString('topPowerDisplay', $script);
        $this->assertStringContainsString('回路電流', $blade);
        $this->assertStringContainsString('R1 @{{ candidate.upper_power_display }}', $blade);
        $this->assertStringContainsString('R上最大電力', $blade);
        $this->assertStringContainsString('VR最大電力', $blade);
        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'dividerTargetModeOptions'));
        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'form.series'));
        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'form.total_res_min_raw'));

        preg_match_all('/<button\b[\s\S]*?<\/button>/u', $blade, $buttonMatches);
        $buttons = $buttonMatches[0];
        $infiniteResistanceButtons = array_filter($buttons, static fn (string $button): bool => str_contains($button, '∞'));
        $zeroCurrentButtons = array_filter($buttons, static fn (string $button): bool => str_contains($button, '0A'));

        $this->assertGreaterThanOrEqual(2, count($infiniteResistanceButtons), 'VR adjustment off/on must each expose a load-resistance infinity button.');
        $this->assertGreaterThanOrEqual(2, count($zeroCurrentButtons), 'VR adjustment off/on must each expose a load-current zero button.');
        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'setLoadResistanceInfinite('));
        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'setLoadCurrentZero('));
    }

    /**
     * 目的: 許容差スペック値入力が自由入力だけでなく候補選択UIを持つことを固定する。
     * 機能: 部品登録画面に候補リスト、コード、単位、カスタム入力の表示があることを確認する。
     * 入力: なし。
     * 出力: アサーション結果。
     * 動作条件: component-create Blade が描画できること。
     * 副作用: テスト用HTTP GETを1回発行する。
     */
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

    /**
     * 目的: スペック詳細の新規追加入口が候補追加操作と混ざらない位置にあることを固定する。
     * 機能: 登録/詳細画面のセパレータと新規追加導線の表示語を確認する。
     * 入力: 詳細画面用の部品フィクスチャ。
     * 出力: アサーション結果。
     * 動作条件: createUiFixture が部品を作成できること。
     * 副作用: テストDBへ部品関連データを作成し、画面GETを発行する。
     */
    public function test_spec_detail_creation_entrypoint_lives_below_separator(): void
    {
        $fixture = $this->createUiFixture();

        foreach (['/components/create', "/components/{$fixture['component']->id}"] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('border-t border-[var(--color-border)] pt-3', false)
                ->assertSee('スペックを新規で追加', false)
                ->assertSee('@click="openInlineSpecTypeModal()"', false)
                ->assertSee('基準単位', false)
                ->assertSee('例: F / Ω / A', false)
                ->assertSee('入力候補接頭辞', false)
                ->assertSee('接頭語は下の入力候補接頭辞で選びます。', false)
                ->assertDontSee('例: μF', false)
                ->assertDontSee('候補外を作成', false)
                ->assertSee('保存して追加', false)
                ->assertDontSee('openInlineSpecTypeModal(spec)', false)
                ->assertDontSee('spec-type-add-button', false)
                ->assertDontSee('保存して選択', false);
        }
    }

    /**
     * 目的: 部品一覧の既定順が更新日時ではなく型番のカタログ文脈に沿うことを固定する。
     * 機能: 更新日時を逆転させた部品を作り、自然順の並びをAPIで確認する。
     * 入力: 型番とupdated_atが異なる部品群。
     * 出力: アサーション結果。
     * 動作条件: components API の既定orderが有効であること。
     * 副作用: components をテストDBへ作成する。
     */
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

    /**
     * 目的: 型番ソートで数値部分が文字列順ではなく自然順になることを固定する。
     * 機能: R1/R2/R10を作り、昇順指定時のAPI返却順を確認する。
     * 入力: 数値違いの型番を持つ部品群。
     * 出力: アサーション結果。
     * 動作条件: components API の sort=part_number が指定できること。
     * 副作用: components をテストDBへ作成する。
     */
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

    /**
     * 目的: スペック候補が選択中の部品分類にだけ絞られることを固定する。
     * 機能: 分類ごとに別スペックを紐付け、APIのgroup指定結果を確認する。
     * 入力: 2つの部品分類とそれぞれの候補スペック。
     * 出力: アサーション結果。
     * 動作条件: spec-groups と spec-types の候補紐付けが利用可能であること。
     * 副作用: spec_groups、spec_types、中間テーブルをテストDBへ作成する。
     */
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

    /**
     * 目的: 部品分類一覧がサイドバー表示に必要な件数情報を返すことを固定する。
     * 機能: 部品、候補スペック、テンプレートの件数を作成し、APIレスポンスで確認する。
     * 入力: 件数確認用の分類、部品、スペック、テンプレート。
     * 出力: アサーション結果。
     * 動作条件: 関連テーブルが存在すること。存在しない場合のフォールバックは別テストで確認する。
     * 副作用: 複数マスタと中間テーブルをテストDBへ作成する。
     */
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

    /**
     * 目的: 部品シリーズ系テーブルが未作成の段階でも部品分類一覧APIが壊れないことを固定する。
     * 機能: Schema::hasTable を一時的に差し替え、未移行環境相当でAPI応答を確認する。
     * 入力: component_series 系テーブルなしを模擬するSchemaモック。
     * 出力: アサーション結果。
     * 動作条件: LaravelのFacadeモックがテスト内で有効であること。
     * 副作用: Schema Facade の挙動をテスト中だけ差し替え、HTTP GETを1回発行する。
     */
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
}

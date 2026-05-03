<?php

namespace Tests\Feature\UiApiSurfaceSmoke;

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

trait CreatesUiApiSurfaceFixtures
{
    /**
     * 目的: UI/APIスモークで共有する業務データ一式を作る。
     * 機能: 部品、分類、パッケージ、スペック、在庫、案件、解析保存、監査ログ、発注、Altium情報を関連付きで用意する。
     * 入力: なし。
     * 出力: テスト本文で参照するモデルを名前付き配列で返す。
     * 動作条件: テストDBが空またはRefreshDatabaseで初期化済みであること。
     * 副作用: 複数テーブルへ検証用レコードを作成する。
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

    /**
     * 目的: 比較・一覧・詳細APIで使う部品1件と周辺関連を作成する。
     * 機能: 部品基本情報、スペック、在庫ブロック、仕入先、保管棚、部品分類、パッケージを接続する。
     * 入力: 型番、通称、数量、仕入単価、共通マスタモデル群。
     * 出力: 作成した Component モデル。
     * 動作条件: 渡されたマスタと仕入先・棚が永続化済みであること。
     * 副作用: components と関連中間/子テーブルへ検証用レコードを作成する。
     */
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

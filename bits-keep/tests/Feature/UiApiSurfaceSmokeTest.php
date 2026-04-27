<?php

namespace Tests\Feature;

use App\Models\AltiumLibrary;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentSupplier;
use App\Models\Location;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\Project;
use App\Models\ProjectSyncRun;
use App\Models\SpecType;
use App\Models\StockOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        foreach ([
            '/api/categories?include_archived=1',
            '/api/package-groups?include_archived=1',
            '/api/packages?include_archived=1',
            '/api/spec-types?include_archived=1',
            '/api/suppliers?include_archived=1',
            '/api/locations?include_archived=1',
            '/api/components?per_page=10',
            "/api/components/{$component->id}",
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

        $this->getJson('/api/components?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.part_number', $component->part_number)
            ->assertJsonPath('data.data.0.package_name', $fixture['package']->name)
            ->assertJsonPath('data.data.0.needs_reorder', true);

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
     * @return array<string, mixed>
     */
    private function createUiFixture(): array
    {
        $category = Category::create([
            'name' => 'UI確認カテゴリ',
            'description' => 'UI smoke',
            'color' => '#38bdf8',
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
            'project'
        );
    }

    private function createComponentFixture(
        Category $category,
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

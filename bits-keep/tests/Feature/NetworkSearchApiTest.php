<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Location;
use App\Models\SpecGroup;
use App\Models\SpecType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]));
    }

    public function test_resistor_series_and_parallel_custom_values_are_calculated(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 2000,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [1000],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_value', 2000)
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'series');

        $this->postJson('/api/calc/networks/search', [
            'target' => 500,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [1000],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_value', 500)
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'parallel');
    }

    public function test_capacitor_series_and_parallel_use_capacitance_rules(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 5e-8,
            'tolerance_pct' => 0.01,
            'part_type' => 'C',
            'series' => 'custom',
            'custom_values' => [1e-7],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '50nF')
            ->assertJsonPath('data.result.candidates.0.topology_label', '容量直列');

        $this->postJson('/api/calc/networks/search', [
            'target' => 2e-7,
            'tolerance_pct' => 0.01,
            'part_type' => 'C',
            'series' => 'custom',
            'custom_values' => [1e-7],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '200nF')
            ->assertJsonPath('data.result.candidates.0.topology_label', '容量並列');
    }

    public function test_divider_ratio_and_total_resistance_range_are_checked(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 0.5,
            'tolerance_pct' => 0.01,
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'total_res_min' => 15000,
            'total_res_max' => 25000,
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '50%')
            ->assertJsonPath('data.result.candidates.0.total_display', '20kΩ');

        $this->postJson('/api/calc/networks/search', [
            'target' => 1.2,
            'part_type' => 'divider',
        ])
            ->assertOk()
            ->assertJsonPath('data.result', null)
            ->assertJsonPath('data.summary', '分圧比は 0 より大きく 1 より小さい値で指定してください');
    }

    public function test_divider_uses_input_and_output_voltage_with_infinite_load(): void
    {
        $response = $this->postJson('/api/calc/networks/search', [
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'input_voltage' => 3.3,
            'output_voltage' => 1.65,
            'load_resistance_infinite' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.result.target_display', '1.65V / 3.3V = 50%')
            ->assertJsonPath('data.result.candidates.0.actual_display', '50%')
            ->assertJsonPath('data.result.candidates.0.actual_output_display', '1.65V')
            ->assertJsonPath('data.result.candidates.0.load_display', '∞Ω')
            ->assertJsonPath('data.result.candidates.0.source_current_display', '165μA')
            ->assertJsonPath('data.result.candidates.0.upper_power_display', '272.25μW')
            ->assertJsonPath('data.result.candidates.0.lower_power_display', '272.25μW')
            ->assertJsonPath('data.result.candidates.0.resistor_power_display', '544.5μW');

        $this->assertEqualsWithDelta(0.5, $response->json('data.result.candidates.0.actual_value'), 1e-12);
        $this->assertEqualsWithDelta(1.65, $response->json('data.result.candidates.0.actual_output_voltage'), 1e-12);
        $this->assertEqualsWithDelta(0.000165, $response->json('data.result.candidates.0.source_current'), 1e-12);
        $this->assertEqualsWithDelta(0.00027225, $response->json('data.result.candidates.0.upper_power'), 1e-12);
    }

    public function test_divider_resistance_load_makes_10k_pair_one_third_ratio(): void
    {
        $response = $this->postJson('/api/calc/networks/search', [
            'target' => 1 / 3,
            'tolerance_pct' => 0.01,
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'load_type' => 'resistance',
            'load_resistance' => 10000,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.load_display', '10kΩ')
            ->assertJsonPath('data.result.candidates.0.total_display', '20kΩ');

        $this->assertEqualsWithDelta(1 / 3, $response->json('data.result.candidates.0.actual_value'), 1e-12);
    }

    public function test_divider_current_load_without_input_voltage_returns_invalid_response(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 0.5,
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'load_type' => 'current',
            'load_current' => 0.001,
        ])
            ->assertOk()
            ->assertJsonPath('data.result', null)
            ->assertJsonPath('data.summary', '電流負荷の計算には入力電圧が必要です');
    }

    public function test_e_series_pair_search_does_not_drop_parallel_exact_match(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 909.090909,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'series' => 'E24',
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '909.090909Ω')
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'parallel');
    }

    public function test_e48_and_e96_pair_search_reaches_exact_matches_before_evaluation_limit(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 20000,
            'tolerance_pct' => 0.001,
            'part_type' => 'R',
            'series' => 'E48',
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series', 'parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.evaluation_limited', false)
            ->assertJsonPath('data.result.candidates.0.actual_display', '20kΩ')
            ->assertJsonPath('data.result.candidates.0.expression', '10kΩ + 10kΩ');

        $this->postJson('/api/calc/networks/search', [
            'target' => 5000,
            'tolerance_pct' => 0.001,
            'part_type' => 'R',
            'series' => 'E96',
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.evaluation_limited', false)
            ->assertJsonPath('data.result.candidates.0.actual_display', '5kΩ')
            ->assertJsonPath('data.result.candidates.0.expression', '10kΩ ∥ 10kΩ');

        $this->postJson('/api/calc/networks/search', [
            'target' => 5e-8,
            'tolerance_pct' => 0.001,
            'part_type' => 'C',
            'series' => 'E48',
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.evaluation_limited', false)
            ->assertJsonPath('data.result.candidates.0.actual_display', '50nF')
            ->assertJsonPath('data.result.candidates.0.expression', '100nF + 100nF');
    }

    public function test_divider_search_uses_full_e_series_for_tight_total_range(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 0.5,
            'tolerance_pct' => 0.01,
            'part_type' => 'divider',
            'series' => 'E96',
            'min_elements' => 4,
            'max_elements' => 2,
            'total_res_min' => 19990,
            'total_res_max' => 20010,
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '50%')
            ->assertJsonPath('data.result.candidates.0.total_display', '20kΩ');
    }

    public function test_invalid_element_range_returns_design_analysis_warning(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'part_type' => 'R',
            'min_elements' => 4,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result', null)
            ->assertJsonPath('data.summary', '素子数の最小値が最大値を超えています');
    }

    public function test_empty_custom_values_return_invalid_response(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.result', null)
            ->assertJsonPath('data.summary', '任意値が入力されていません');
    }

    public function test_too_many_custom_values_are_rejected(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => range(1, 257),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_values');
    }

    public function test_evaluation_limit_counts_no_hit_attempts(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 123456789,
            'tolerance_pct' => 0.001,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => range(1, 256),
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series', 'parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.evaluation_limited', true)
            ->assertJsonPath('data.result.truncated', true)
            ->assertJsonCount(0, 'data.result.candidates');
    }

    public function test_three_element_series_exact_match_is_not_lost_by_pool_limit(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'tolerance_pct' => 0.001,
            'part_type' => 'R',
            'series' => 'E24',
            'min_elements' => 3,
            'max_elements' => 3,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '1kΩ')
            ->assertJsonPath('data.result.candidates.0.elements_count', 3);
    }

    public function test_inventory_search_uses_matching_value_spec_not_first_numeric_spec(): void
    {
        $group = SpecGroup::create(['name' => '抵抗器', 'sort_order' => 10]);
        $voltageSpec = SpecType::create(['name' => '定格電圧', 'name_ja' => '定格電圧', 'base_unit' => 'V']);
        $resistanceSpec = SpecType::create(['name' => '抵抗値', 'name_ja' => '抵抗値', 'base_unit' => 'Ω']);
        $location = Location::create(['code' => 'N-1', 'name' => 'ネットワーク探索棚']);
        $component = Component::create([
            'manufacturer' => 'Test',
            'part_number' => 'RK73-4K7',
            'common_name' => '4.7kΩテスト抵抗',
            'quantity_new' => 10,
            'quantity_used' => 0,
            'threshold_new' => 0,
            'threshold_used' => 0,
        ]);
        $component->categories()->sync([$group->id]);
        $component->specs()->create([
            'spec_type_id' => $voltageSpec->id,
            'display_name' => '定格電圧',
            'value_profile' => 'typ',
            'value_numeric_typ' => 50,
            'normalized_unit' => 'V',
        ]);
        $component->specs()->create([
            'spec_type_id' => $resistanceSpec->id,
            'display_name' => '抵抗値',
            'value_profile' => 'typ',
            'value_numeric_typ' => 4700,
            'normalized_unit' => 'Ω',
        ]);
        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'loose',
            'condition' => 'new',
            'quantity' => 10,
        ]);

        $this->postJson('/api/calc/networks/search', [
            'target' => 4700,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'inventory_only' => true,
            'min_elements' => 1,
            'max_elements' => 1,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_value', 4700)
            ->assertJsonPath('data.result.candidates.0.parts.0.component_id', $component->id)
            ->assertJsonPath('data.result.candidates.0.parts.0.stock_quantity', 10);
    }

    public function test_inventory_search_excludes_component_without_matching_value_spec(): void
    {
        $group = SpecGroup::create(['name' => '抵抗器', 'sort_order' => 10]);
        $voltageSpec = SpecType::create(['name' => '定格電圧', 'name_ja' => '定格電圧', 'base_unit' => 'V']);
        $location = Location::create(['code' => 'N-2', 'name' => '抵抗値なし棚']);
        $component = Component::create([
            'manufacturer' => 'Test',
            'part_number' => 'NO-R-VALUE',
            'common_name' => '抵抗値なしテスト抵抗',
            'quantity_new' => 10,
            'quantity_used' => 0,
            'threshold_new' => 0,
            'threshold_used' => 0,
        ]);
        $component->categories()->sync([$group->id]);
        $component->specs()->create([
            'spec_type_id' => $voltageSpec->id,
            'display_name' => '定格電圧',
            'value_profile' => 'typ',
            'value_numeric_typ' => 50,
            'normalized_unit' => 'V',
        ]);
        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'loose',
            'condition' => 'new',
            'quantity' => 10,
        ]);

        $this->postJson('/api/calc/networks/search', [
            'target' => 50,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'inventory_only' => true,
            'min_elements' => 1,
            'max_elements' => 1,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.result.candidates');
    }

    public function test_inventory_search_excludes_temperature_coefficient_as_resistance_value(): void
    {
        $group = SpecGroup::create(['name' => '抵抗器', 'sort_order' => 10]);
        $tcrSpec = SpecType::create(['name' => '抵抗温度係数', 'name_ja' => '抵抗温度係数', 'base_unit' => 'ppm/℃']);
        $location = Location::create(['code' => 'N-3', 'name' => 'TCR棚']);
        $component = Component::create([
            'manufacturer' => 'Test',
            'part_number' => 'TCR-ONLY',
            'common_name' => 'TCRのみ抵抗',
            'quantity_new' => 10,
            'quantity_used' => 0,
            'threshold_new' => 0,
            'threshold_used' => 0,
        ]);
        $component->categories()->sync([$group->id]);
        $component->specs()->create([
            'spec_type_id' => $tcrSpec->id,
            'display_name' => '抵抗温度係数',
            'value_profile' => 'typ',
            'value_numeric_typ' => 100,
            'normalized_unit' => 'ppm/℃',
        ]);
        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'loose',
            'condition' => 'new',
            'quantity' => 10,
        ]);

        $this->postJson('/api/calc/networks/search', [
            'target' => 100,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'inventory_only' => true,
            'min_elements' => 1,
            'max_elements' => 1,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.result.candidates');
    }

    public function test_inventory_search_does_not_reuse_component_beyond_stock_quantity(): void
    {
        $group = SpecGroup::create(['name' => '抵抗器', 'sort_order' => 10]);
        $resistanceSpec = SpecType::create(['name' => '抵抗値', 'name_ja' => '抵抗値', 'base_unit' => 'Ω']);
        $location = Location::create(['code' => 'N-4', 'name' => '在庫1棚']);
        $component = Component::create([
            'manufacturer' => 'Test',
            'part_number' => 'ONE-STOCK',
            'common_name' => '在庫1抵抗',
            'quantity_new' => 1,
            'quantity_used' => 0,
            'threshold_new' => 0,
            'threshold_used' => 0,
        ]);
        $component->categories()->sync([$group->id]);
        $component->specs()->create([
            'spec_type_id' => $resistanceSpec->id,
            'display_name' => '抵抗値',
            'value_profile' => 'typ',
            'value_numeric_typ' => 123456,
            'normalized_unit' => 'Ω',
        ]);
        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'loose',
            'condition' => 'new',
            'quantity' => 1,
        ]);

        $this->postJson('/api/calc/networks/search', [
            'target' => 246912,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'inventory_only' => true,
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.result.candidates');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\ComponentSeries;
use App\Models\ComponentSeriesValue;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\SpecGroup;
use App\Models\SpecType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentSeriesManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->create(['role' => 'editor', 'is_active' => true]);
        $this->viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
    }

    public function test_editor_can_create_hybrid_series_and_preview_e12_with_extra_e24_values(): void
    {
        $fixture = $this->createSeriesFixture();

        $preview = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'policy' => [
                'value_set_type' => 'hybrid_series',
                'primary_series' => 'E12',
                'extra_series' => ['E24'],
                'extra_values' => ['4.99'],
                'excluded_values' => ['1.2'],
                'unit' => 'Ω',
                'decade_min' => 0,
                'decade_max' => 0,
            ],
        ]);

        $preview
            ->assertOk()
            ->assertJsonPath('success', true);

        $values = collect($preview->json('data.values'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '1Ω' && $row['origin'] === 'primary_generated'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '1.1Ω' && $row['origin'] === 'extra_series'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '4.99Ω' && $row['origin'] === 'manual'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '1.2Ω' && $row['is_enabled'] === false));

        $response = $this->actingAs($this->editor)->postJson('/api/component-series', [
            'spec_group_id' => $fixture['group']->id,
            'value_spec_type_id' => $fixture['specType']->id,
            'package_id' => $fixture['package']->id,
            'manufacturer' => 'Yageo',
            'name' => 'RC0402FR',
            'description' => 'E12中心に一部E24を採用する抵抗シリーズ',
            'policy' => [
                'value_set_type' => 'hybrid_series',
                'primary_series' => 'E12',
                'extra_series' => ['E24'],
                'extra_values' => ['4.99'],
                'excluded_values' => ['1.2'],
                'unit' => 'Ω',
                'decade_min' => 0,
                'decade_max' => 0,
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'RC0402FR')
            ->assertJsonPath('data.policy.value_set_type', 'hybrid_series');

        $this->assertDatabaseHas('component_series', [
            'name' => 'RC0402FR',
            'spec_group_id' => $fixture['group']->id,
        ]);
        $this->assertDatabaseHas('component_series_values', [
            'component_series_id' => $response->json('data.id'),
            'value_text' => '1.1Ω',
            'origin' => 'extra_series',
        ]);
    }

    public function test_custom_list_series_keeps_non_e_values_without_generated_series(): void
    {
        $fixture = $this->createSeriesFixture(['group_name' => 'ツェナーダイオード', 'spec_type_name' => 'ツェナー電圧', 'unit' => 'V']);

        $response = $this->actingAs($this->editor)->postJson('/api/component-series', [
            'spec_group_id' => $fixture['group']->id,
            'value_spec_type_id' => $fixture['specType']->id,
            'manufacturer' => 'ExampleSemi',
            'name' => 'BZX-Custom',
            'policy' => [
                'value_set_type' => 'custom_list',
                'custom_values' => ['2.7', '3.0', '3.3', '5.1'],
                'unit' => 'V',
            ],
        ]);

        $response->assertCreated();

        $seriesId = $response->json('data.id');
        $this->assertSame(4, ComponentSeriesValue::where('component_series_id', $seriesId)->count());
        $this->assertDatabaseHas('component_series_values', [
            'component_series_id' => $seriesId,
            'value_text' => '5.1V',
            'origin' => 'custom_list',
        ]);
    }

    public function test_resistor_series_can_add_zero_ohm_and_generate_ten_giga_ohm(): void
    {
        $preview = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'policy' => [
                'value_set_type' => 'hybrid_series',
                'primary_series' => 'E12',
                'extra_values' => ['0Ω'],
                'excluded_values' => [],
                'unit' => 'Ω',
                'decade_min' => 10,
                'decade_max' => 10,
            ],
        ]);

        $preview->assertOk();

        $values = collect($preview->json('data.values'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '0Ω' && $row['origin'] === 'manual'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '10GΩ' && $row['origin'] === 'primary_generated'));
    }

    public function test_e_series_uses_engineering_start_and_end_values_with_zero_checkbox(): void
    {
        $preview = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'policy' => [
                'value_set_type' => 'e_series',
                'primary_series' => 'E12',
                'excluded_values' => [],
                'unit' => 'Ω',
                'range_min' => '2.2kΩ',
                'range_max' => '10kΩ',
                'generation_settings' => ['include_zero' => true],
            ],
        ]);

        $preview->assertOk();

        $values = collect($preview->json('data.values'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '0Ω' && $row['origin'] === 'included_zero'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '2.2kΩ'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '10kΩ'));
        $this->assertFalse($values->contains(fn ($row) => $row['value_text'] === '1kΩ'));
        $this->assertFalse($values->contains(fn ($row) => $row['value_text'] === '12kΩ'));
    }

    public function test_capacitor_series_uses_selected_spec_prefixes_for_start_and_end_values(): void
    {
        $fixture = $this->createSeriesFixture([
            'group_name' => 'コンデンサ',
            'spec_type_name' => '容量',
            'unit' => 'F',
            'suggest_prefixes' => ['', 'm', 'u', 'n', 'p', 'f'],
            'display_prefixes' => ['u', 'n', 'p', 'f'],
        ]);

        $preview = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'value_spec_type_id' => $fixture['specType']->id,
            'policy' => [
                'value_set_type' => 'e_series',
                'primary_series' => 'E12',
                'excluded_values' => [],
                'unit' => 'F',
                'range_min' => '1fF',
                'range_max' => '10pF',
            ],
        ]);

        $preview->assertOk();

        $values = collect($preview->json('data.values'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '1fF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '1pF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '8.2pF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '10pF'));
        $this->assertFalse($values->contains(fn ($row) => $row['value_text'] === '0.1fF'));
        $this->assertFalse($values->contains(fn ($row) => str_contains($row['value_text'], '999999')));

        $invalid = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'value_spec_type_id' => $fixture['specType']->id,
            'policy' => [
                'value_set_type' => 'e_series',
                'primary_series' => 'E12',
                'unit' => 'F',
                'range_min' => '1G',
                'range_max' => '10G',
            ],
        ]);

        $invalid
            ->assertStatus(422)
            ->assertJsonValidationErrors(['policy.range_min', 'policy.range_max']);
    }

    public function test_capacitor_series_preview_keeps_sub_pico_to_micro_range(): void
    {
        $fixture = $this->createSeriesFixture([
            'group_name' => 'コンデンサ',
            'spec_type_name' => '容量',
            'unit' => 'F',
            'suggest_prefixes' => ['', 'm', 'u', 'n', 'p', 'f'],
            'display_prefixes' => ['u', 'n', 'p', 'f'],
        ]);

        $preview = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'value_spec_type_id' => $fixture['specType']->id,
            'policy' => [
                'value_set_type' => 'e_series',
                'primary_series' => 'E12',
                'excluded_values' => [],
                'unit' => 'F',
                'range_min' => '0.1pF',
                'range_max' => '100uF',
                'generation_settings' => ['include_zero' => true],
            ],
        ]);

        $preview->assertOk();

        $values = collect($preview->json('data.values'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '0F' && $row['origin'] === 'included_zero'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '100fF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '1pF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '100nF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '100uF'));
        $firstGenerated = $values->first(fn ($row) => $row['origin'] !== 'included_zero');
        $this->assertSame('100fF', $firstGenerated['value_text'] ?? null);
    }

    public function test_saved_capacitor_series_can_be_regenerated_with_smaller_start_value(): void
    {
        $fixture = $this->createSeriesFixture([
            'group_name' => 'コンデンサ',
            'spec_type_name' => '容量',
            'unit' => 'F',
            'suggest_prefixes' => ['', 'm', 'u', 'n', 'p', 'f'],
            'display_prefixes' => ['u', 'n', 'p', 'f'],
        ]);

        $created = $this->actingAs($this->editor)->postJson('/api/component-series', [
            'spec_group_id' => $fixture['group']->id,
            'value_spec_type_id' => $fixture['specType']->id,
            'package_id' => $fixture['package']->id,
            'manufacturer' => 'Murata',
            'name' => 'GRM-CAP',
            'policy' => [
                'value_set_type' => 'e_series',
                'primary_series' => 'E12',
                'excluded_values' => [],
                'unit' => 'F',
                'range_min' => '1uF',
                'range_max' => '100uF',
            ],
        ]);

        $created->assertCreated();
        $seriesId = $created->json('data.id');
        $this->assertDatabaseHas('component_series_values', [
            'component_series_id' => $seriesId,
            'value_text' => '1uF',
        ]);
        $this->assertDatabaseMissing('component_series_values', [
            'component_series_id' => $seriesId,
            'value_text' => '100fF',
        ]);

        $updated = $this->actingAs($this->editor)->putJson("/api/component-series/{$seriesId}", [
            'spec_group_id' => $fixture['group']->id,
            'value_spec_type_id' => $fixture['specType']->id,
            'package_id' => $fixture['package']->id,
            'manufacturer' => 'Murata',
            'name' => 'GRM-CAP',
            'policy' => [
                'value_set_type' => 'e_series',
                'primary_series' => 'E12',
                'excluded_values' => [],
                'unit' => 'F',
                'decade_min' => -13,
                'decade_max' => -4,
                'range_min' => '100fF',
                'range_max' => '100uF',
            ],
        ]);

        $updated->assertOk();

        $values = collect($updated->json('data.values'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '100fF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '1pF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '1uF'));
        $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === '100uF'));
        $this->assertSame('100fF', $values->first()['value_text'] ?? null);
    }

    public function test_e_series_preview_covers_full_ui_prefix_range(): void
    {
        $fixture = $this->createSeriesFixture([
            'group_name' => 'コンデンサ',
            'spec_type_name' => '容量',
            'unit' => 'F',
            'suggest_prefixes' => ['T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'],
            'display_prefixes' => ['T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'],
        ]);

        $preview = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'value_spec_type_id' => $fixture['specType']->id,
            'policy' => [
                'value_set_type' => 'e_series',
                'primary_series' => 'E12',
                'excluded_values' => [],
                'unit' => 'F',
                'range_min' => '1fF',
                'range_max' => '100TF',
            ],
        ]);

        $preview->assertOk();

        $values = collect($preview->json('data.values'));
        $this->assertSame('1fF', $values->first()['value_text'] ?? null);
        $this->assertSame('100TF', $values->last()['value_text'] ?? null);
        foreach (['1fF', '1pF', '1nF', '1uF', '1mF', '1F', '1kF', '1MF', '1GF', '1TF', '100TF'] as $label) {
            $this->assertTrue($values->contains(fn ($row) => $row['value_text'] === $label), "{$label} が生成されていません");
        }
        $this->assertFalse($values->contains(fn ($row) => $row['value_text'] === '0F'));
        $this->assertFalse($values->contains(fn ($row) => str_ends_with($row['value_text'], 'PF')));
    }

    public function test_range_step_accepts_engineering_notation_and_reports_field_specific_errors(): void
    {
        $preview = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'policy' => [
                'value_set_type' => 'range_step',
                'unit' => 'Ω',
                'range_min' => '0Ω',
                'range_max' => '10GΩ',
                'range_step' => '5GΩ',
            ],
        ]);

        $preview->assertOk();

        $values = collect($preview->json('data.values'));
        $this->assertSame(['0Ω', '5GΩ', '10GΩ'], $values->pluck('value_text')->all());

        $invalid = $this->actingAs($this->editor)->postJson('/api/component-series/preview', [
            'policy' => [
                'value_set_type' => 'range_step',
                'unit' => 'Ω',
                'range_min' => '0Ω',
                'range_max' => 'abc',
                'range_step' => '5GΩ',
            ],
        ]);

        $invalid
            ->assertStatus(422)
            ->assertJsonValidationErrors(['policy.range_max']);
        $this->assertSame(
            '範囲/刻みの終了値には数値を入力してください。例: 10G, 10GΩ, 10000000000',
            $invalid->json('errors')['policy.range_max'][0] ?? null
        );
    }

    public function test_selected_virtual_value_can_be_materialized_as_real_component(): void
    {
        $fixture = $this->createSeriesFixture();
        $series = ComponentSeries::create([
            'spec_group_id' => $fixture['group']->id,
            'value_spec_type_id' => $fixture['specType']->id,
            'package_id' => $fixture['package']->id,
            'manufacturer' => 'Yageo',
            'name' => 'RC0402FR',
            'created_by' => $this->editor->id,
            'updated_by' => $this->editor->id,
        ]);
        $value = $series->values()->create([
            'value_text' => '1kΩ',
            'value_key' => 'ω|1000',
            'value_numeric' => 1000,
            'unit' => 'Ω',
            'origin' => 'manual',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->editor)->postJson("/api/component-series/{$series->id}/materialize", [
            'value_ids' => [$value->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.created.0.value_id', $value->id);

        $componentId = $response->json('data.created.0.component_id');
        $this->assertDatabaseHas('components', [
            'id' => $componentId,
            'component_series_id' => $series->id,
            'component_series_value_id' => $value->id,
            'package_id' => $fixture['package']->id,
        ]);
        $this->assertDatabaseHas('component_spec_group', [
            'component_id' => $componentId,
            'spec_group_id' => $fixture['group']->id,
        ]);
        $this->assertDatabaseHas('component_specs', [
            'component_id' => $componentId,
            'spec_type_id' => $fixture['specType']->id,
            'value_numeric_typ' => 1000,
            'normalized_unit' => 'Ω',
        ]);

        $value->refresh();
        $this->assertSame($componentId, $value->materialized_component_id);
        $this->assertTrue($value->is_stocked);
        $this->assertSame($componentId, Component::first()->id);
    }

    public function test_viewer_cannot_write_component_series(): void
    {
        $fixture = $this->createSeriesFixture();

        $this->actingAs($this->viewer)->postJson('/api/component-series', [
            'spec_group_id' => $fixture['group']->id,
            'value_spec_type_id' => $fixture['specType']->id,
            'name' => 'Denied',
            'policy' => ['value_set_type' => 'none'],
        ])->assertForbidden();

        $series = ComponentSeries::create([
            'spec_group_id' => $fixture['group']->id,
            'value_spec_type_id' => $fixture['specType']->id,
            'name' => 'Existing',
            'created_by' => $this->editor->id,
            'updated_by' => $this->editor->id,
        ]);

        $this->actingAs($this->viewer)->postJson("/api/component-series/{$series->id}/materialize", [
            'value_ids' => [1],
        ])->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function createSeriesFixture(array $overrides = []): array
    {
        $unit = $overrides['unit'] ?? 'Ω';
        $group = SpecGroup::create([
            'name' => $overrides['group_name'] ?? '抵抗',
            'description' => null,
            'sort_order' => 10,
            'series_management_mode' => 'series_recommended',
        ]);
        $specType = SpecType::create([
            'name' => $overrides['spec_type_name'] ?? '抵抗値',
            'name_ja' => $overrides['spec_type_name'] ?? '抵抗値',
            'base_unit' => $unit,
            'suggest_prefixes' => $overrides['suggest_prefixes'] ?? null,
            'display_prefixes' => $overrides['display_prefixes'] ?? null,
            'sort_order' => 10,
        ]);
        $packageGroup = PackageGroup::create(['name' => 'チップ部品', 'sort_order' => 10]);
        $package = Package::create([
            'package_group_id' => $packageGroup->id,
            'name' => '0402',
            'sort_order' => 10,
        ]);

        return compact('group', 'specType', 'packageGroup', 'package');
    }
}

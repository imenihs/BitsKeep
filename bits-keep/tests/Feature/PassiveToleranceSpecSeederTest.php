<?php

namespace Tests\Feature;

use App\Models\SpecGroup;
use App\Models\SpecType;
use Database\Seeders\AkizukiReferenceMasterSeeder;
use Database\Seeders\PassiveToleranceSpecSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassiveToleranceSpecSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_passive_tolerance_specs_are_seeded_and_attached_to_representative_groups(): void
    {
        $this->seed(PassiveToleranceSpecSeeder::class);
        $this->seed(PassiveToleranceSpecSeeder::class);

        $resistanceTolerance = SpecType::query()->where('name', '抵抗値許容差')->firstOrFail();
        $resistanceTemperatureCoefficient = SpecType::query()->where('name', '抵抗温度係数')->firstOrFail();
        $capacitanceTolerance = SpecType::query()->where('name', '容量許容差')->firstOrFail();
        $capacitanceTemperatureCharacteristic = SpecType::query()->where('name', '容量温度特性')->firstOrFail();

        $this->assertSame(1, SpecType::query()->where('name', '抵抗値許容差')->count());
        $this->assertSame(1, SpecType::query()->where('name', '抵抗温度係数')->count());
        $this->assertSame(1, SpecType::query()->where('name', '容量許容差')->count());
        $this->assertSame(1, SpecType::query()->where('name', '容量温度特性')->count());
        $this->assertSame(SpecType::KIND_TOLERANCE, $resistanceTolerance->spec_kind);
        $this->assertSame(SpecType::KIND_TOLERANCE, $resistanceTemperatureCoefficient->spec_kind);
        $this->assertSame(SpecType::KIND_TOLERANCE, $capacitanceTolerance->spec_kind);
        $this->assertSame(SpecType::KIND_TOLERANCE, $capacitanceTemperatureCharacteristic->spec_kind);
        $this->assertSame(SpecType::SCOPE_COMMON, $resistanceTolerance->spec_scope);
        $this->assertSame(SpecType::SCOPE_COMMON, $capacitanceTolerance->spec_scope);
        $this->assertNull($resistanceTolerance->owner_spec_group_id);
        $this->assertNull($capacitanceTolerance->owner_spec_group_id);
        $this->assertSame('%', $resistanceTolerance->base_unit);
        $this->assertSame('%', $capacitanceTolerance->base_unit);

        $this->assertSame('grade', $resistanceTolerance->tolerance_settings['default_mode']);
        $this->assertSame('symmetric', $resistanceTemperatureCoefficient->tolerance_settings['default_mode']);
        $this->assertSame(['%', 'pF'], $capacitanceTolerance->tolerance_settings['allowed_units']);
        $this->assertSame(['B', 'C', 'D', 'F', 'G', 'J', 'K', 'M'], collect($resistanceTolerance->tolerance_settings['grade_options'])->pluck('label')->all());
        $this->assertSame(['A', 'B', 'C', 'D', 'F', 'G', 'J', 'K', 'M', 'Z'], collect($capacitanceTolerance->tolerance_settings['grade_options'])->pluck('label')->all());

        $bGrade = collect($capacitanceTolerance->tolerance_settings['grade_options'])
            ->firstWhere('label', 'B');
        $this->assertSame(0.1, $bGrade['value']);
        $this->assertSame('pF', $bGrade['unit']);

        $zGrade = collect($capacitanceTolerance->tolerance_settings['grade_options'])
            ->firstWhere('label', 'Z');
        $this->assertSame(80, $zGrade['plus']);
        $this->assertSame(20, $zGrade['minus']);
        $this->assertSame('+80/-20%', $zGrade['text']);

        $x7rGrade = collect($capacitanceTemperatureCharacteristic->tolerance_settings['grade_options'])
            ->firstWhere('label', 'X7R');
        $this->assertSame('code', $capacitanceTemperatureCharacteristic->tolerance_settings['default_unit']);
        $this->assertSame('-55〜125℃ / ±15%', $x7rGrade['text']);

        $resistorGroup = SpecGroup::query()->where('name', '抵抗器')->firstOrFail();
        $capacitorGroup = SpecGroup::query()->where('name', 'コンデンサ')->firstOrFail();

        $this->assertDatabaseHas('spec_group_spec_type', [
            'spec_group_id' => $resistorGroup->id,
            'spec_type_id' => $resistanceTolerance->id,
            'default_unit' => '%',
            'note' => '代表許容差',
        ]);
        $this->assertDatabaseHas('spec_group_spec_type', [
            'spec_group_id' => $resistorGroup->id,
            'spec_type_id' => $resistanceTemperatureCoefficient->id,
            'default_unit' => 'ppm/℃',
            'note' => '代表温度特性',
        ]);
        $this->assertDatabaseHas('spec_group_spec_type', [
            'spec_group_id' => $capacitorGroup->id,
            'spec_type_id' => $capacitanceTolerance->id,
            'default_unit' => '%',
            'note' => '代表許容差',
        ]);
        $this->assertDatabaseHas('spec_group_spec_type', [
            'spec_group_id' => $capacitorGroup->id,
            'spec_type_id' => $capacitanceTemperatureCharacteristic->id,
            'default_unit' => 'code',
            'note' => '代表温度特性',
        ]);
        $this->assertDatabaseHas('spec_units', [
            'spec_type_id' => $resistanceTolerance->id,
            'unit' => '%',
        ]);
        $this->assertDatabaseHas('spec_units', [
            'spec_type_id' => $capacitanceTolerance->id,
            'unit' => 'pF',
        ]);
        $this->assertDatabaseHas('spec_units', [
            'spec_type_id' => $resistanceTemperatureCoefficient->id,
            'unit' => 'ppm/℃',
        ]);
        $this->assertDatabaseHas('spec_type_aliases', [
            'spec_type_id' => $capacitanceTolerance->id,
            'alias' => 'Z級',
        ]);
        $this->assertDatabaseHas('spec_type_aliases', [
            'spec_type_id' => $capacitanceTemperatureCharacteristic->id,
            'alias' => 'X7R',
        ]);
    }

    public function test_reference_master_seeder_includes_passive_tolerance_specs(): void
    {
        $this->seed(AkizukiReferenceMasterSeeder::class);

        $this->assertDatabaseHas('spec_types', [
            'name' => '抵抗値許容差',
            'spec_kind' => SpecType::KIND_TOLERANCE,
            'spec_scope' => SpecType::SCOPE_COMMON,
            'base_unit' => '%',
        ]);
        $this->assertDatabaseHas('spec_types', [
            'name' => '容量許容差',
            'spec_kind' => SpecType::KIND_TOLERANCE,
            'spec_scope' => SpecType::SCOPE_COMMON,
            'base_unit' => '%',
        ]);
        $this->assertDatabaseHas('spec_types', [
            'name' => '抵抗温度係数',
            'spec_kind' => SpecType::KIND_TOLERANCE,
            'spec_scope' => SpecType::SCOPE_COMMON,
            'base_unit' => 'ppm/℃',
        ]);
        $this->assertDatabaseHas('spec_types', [
            'name' => '容量温度特性',
            'spec_kind' => SpecType::KIND_TOLERANCE,
            'spec_scope' => SpecType::SCOPE_COMMON,
            'base_unit' => 'code',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\SpecGroup;
use App\Models\SpecType;
use App\Models\SpecTypeAlias;
use App\Models\SpecUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PassiveToleranceSpecSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $resistanceTolerance = $this->seedToleranceSpec([
                'name' => '抵抗値許容差',
                'name_en' => 'Resistance Tolerance',
                'symbol' => 'R_tol',
                'description' => '抵抗器の公称抵抗値に対する許容差',
                'sort_order' => 41,
                'default_unit' => '%',
                'allowed_units' => ['%'],
                'grades' => [
                    ['label' => 'B', 'value' => 0.1, 'unit' => '%'],
                    ['label' => 'C', 'value' => 0.25, 'unit' => '%'],
                    ['label' => 'D', 'value' => 0.5, 'unit' => '%'],
                    ['label' => 'F', 'value' => 1, 'unit' => '%'],
                    ['label' => 'G', 'value' => 2, 'unit' => '%'],
                    ['label' => 'J', 'value' => 5, 'unit' => '%'],
                    ['label' => 'K', 'value' => 10, 'unit' => '%'],
                    ['label' => 'M', 'value' => 20, 'unit' => '%'],
                ],
                'aliases' => ['抵抗許容差', '抵抗値公差', '抵抗公差', '抵抗精度', 'R tolerance', 'resistance tolerance'],
            ]);

            $resistanceTemperatureCoefficient = $this->seedToleranceSpec([
                'name' => '抵抗温度係数',
                'name_en' => 'Temperature Coefficient of Resistance',
                'symbol' => 'TCR',
                'description' => '抵抗器の温度変化に対する抵抗値変動係数',
                'sort_order' => 43,
                'default_mode' => 'symmetric',
                'default_unit' => 'ppm/℃',
                'allowed_units' => ['ppm/℃'],
                'grades' => [
                    ['label' => '25ppm', 'value' => 25, 'unit' => 'ppm/℃'],
                    ['label' => '50ppm', 'value' => 50, 'unit' => 'ppm/℃'],
                    ['label' => '100ppm', 'value' => 100, 'unit' => 'ppm/℃'],
                    ['label' => '200ppm', 'value' => 200, 'unit' => 'ppm/℃'],
                ],
                'aliases' => ['抵抗温度特性', '抵抗温度係数', 'TCR', 'tempco', 'temperature coefficient'],
            ]);

            $capacitanceTolerance = $this->seedToleranceSpec([
                'name' => '容量許容差',
                'name_en' => 'Capacitance Tolerance',
                'symbol' => 'C_tol',
                'description' => 'コンデンサの公称容量に対する許容差',
                'sort_order' => 42,
                'default_unit' => '%',
                'allowed_units' => ['%', 'pF'],
                'grades' => [
                    ['label' => 'A', 'value' => 0.05, 'unit' => 'pF'],
                    ['label' => 'B', 'value' => 0.1, 'unit' => 'pF'],
                    ['label' => 'C', 'value' => 0.25, 'unit' => 'pF'],
                    ['label' => 'D', 'value' => 0.5, 'unit' => 'pF'],
                    ['label' => 'F', 'value' => 1, 'unit' => '%'],
                    ['label' => 'G', 'value' => 2, 'unit' => '%'],
                    ['label' => 'J', 'value' => 5, 'unit' => '%'],
                    ['label' => 'K', 'value' => 10, 'unit' => '%'],
                    ['label' => 'M', 'value' => 20, 'unit' => '%'],
                    ['label' => 'Z', 'plus' => 80, 'minus' => 20, 'unit' => '%', 'text' => '+80/-20%'],
                ],
                'aliases' => ['静電容量許容差', '容量公差', '静電容量公差', '容量精度', 'Z級', 'C tolerance', 'capacitance tolerance'],
            ]);

            $capacitanceTemperatureCharacteristic = $this->seedToleranceSpec([
                'name' => '容量温度特性',
                'name_en' => 'Capacitance Temperature Characteristic',
                'symbol' => 'T_C',
                'description' => 'コンデンサの容量温度特性または誘電体特性コード',
                'sort_order' => 44,
                'default_mode' => 'grade',
                'default_unit' => 'code',
                'allowed_units' => ['code'],
                'grades' => [
                    ['label' => 'C0G/NP0', 'text' => '0±30ppm/℃', 'unit' => 'code'],
                    ['label' => 'X7R', 'text' => '-55〜125℃ / ±15%', 'unit' => 'code'],
                    ['label' => 'X5R', 'text' => '-55〜85℃ / ±15%', 'unit' => 'code'],
                    ['label' => 'X6S', 'text' => '-55〜105℃ / ±22%', 'unit' => 'code'],
                    ['label' => 'X7S', 'text' => '-55〜125℃ / ±22%', 'unit' => 'code'],
                    ['label' => 'Y5V', 'text' => '-30〜85℃ / +22/-82%', 'unit' => 'code'],
                    ['label' => 'Z5U', 'text' => '+10〜85℃ / +22/-56%', 'unit' => 'code'],
                ],
                'aliases' => ['温度特性', '容量温度係数', '容量温度特性', '誘電体特性', 'C0G', 'NP0', 'X7R', 'X5R', 'Y5V', 'Z5U'],
            ]);

            $this->attachToleranceSpec('抵抗器', 10, $resistanceTolerance, 20);
            $this->attachToleranceSpec('抵抗器', 10, $resistanceTemperatureCoefficient, 30, '代表温度特性');
            $this->attachToleranceSpec('コンデンサ', 20, $capacitanceTolerance, 20);
            $this->attachToleranceSpec('コンデンサ', 20, $capacitanceTemperatureCharacteristic, 30, '代表温度特性');
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     name_en: string,
     *     symbol: string,
     *     description: string,
     *     sort_order: int,
     *     default_mode?: string,
     *     default_unit?: string,
     *     allowed_units?: array<int, string>,
     *     grades: array<int, array<string, mixed>>,
     *     aliases: array<int, string>
     * }  $definition
     */
    private function seedToleranceSpec(array $definition): SpecType
    {
        $defaultUnit = $definition['default_unit'] ?? '%';
        $allowedUnits = array_values(array_unique($definition['allowed_units'] ?? [$defaultUnit]));

        $specType = SpecType::withTrashed()->updateOrCreate(
            ['name' => $definition['name']],
            [
                'name' => $definition['name'],
                'name_ja' => $definition['name'],
                'name_en' => $definition['name_en'],
                'symbol' => $definition['symbol'],
                'suggest_prefixes' => null,
                'display_prefixes' => null,
                'spec_scope' => SpecType::SCOPE_COMMON,
                'owner_spec_group_id' => null,
                'spec_kind' => SpecType::KIND_TOLERANCE,
                'tolerance_settings' => [
                    'default_mode' => $definition['default_mode'] ?? 'grade',
                    'default_unit' => $defaultUnit,
                    'allowed_units' => $allowedUnits,
                    'grade_options' => $definition['grades'],
                ],
                'base_unit' => $defaultUnit,
                'description' => $definition['description'],
                'sort_order' => $definition['sort_order'],
            ]
        );

        if ($specType->trashed()) {
            $specType->restore();
        }

        foreach ($allowedUnits as $index => $unit) {
            SpecUnit::query()->updateOrCreate(
                ['spec_type_id' => $specType->id, 'unit' => $unit],
                ['factor' => '1', 'sort_order' => ($index + 1) * 10]
            );
        }

        $this->seedAliases($specType, [
            $definition['name'],
            $definition['name_en'],
            $definition['symbol'],
            '許容差',
            '公差',
            'tolerance',
            ...$definition['aliases'],
        ]);

        return $specType;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function seedAliases(SpecType $specType, array $aliases): void
    {
        foreach (array_values(array_unique($aliases)) as $index => $alias) {
            SpecTypeAlias::query()->updateOrCreate(
                ['spec_type_id' => $specType->id, 'alias' => $alias],
                [
                    'locale' => preg_match('/[ぁ-んァ-ン一-龠]/u', $alias) ? 'ja' : 'en',
                    'kind' => $alias === $specType->symbol ? 'symbol' : 'alias',
                    'sort_order' => ($index + 1) * 10,
                ]
            );
        }
    }

    private function attachToleranceSpec(
        string $groupName,
        int $groupSortOrder,
        SpecType $specType,
        int $memberSortOrder,
        string $note = '代表許容差'
    ): void {
        $group = SpecGroup::withTrashed()->firstOrNew(['name' => $groupName]);
        if (! $group->exists) {
            $group->fill([
                'name' => $groupName,
                'description' => "{$groupName}の代表スペック",
                'sort_order' => $groupSortOrder,
            ]);
            $group->save();
        }
        if ($group->trashed()) {
            $group->restore();
        }

        $group->specTypes()->syncWithoutDetaching([
            $specType->id => [
                'sort_order' => $memberSortOrder,
                'is_required' => false,
                'is_recommended' => true,
                'default_profile' => 'typ',
                'default_unit' => $specType->base_unit,
                'note' => $note,
            ],
        ]);
    }
}

<?php

namespace Tests\Unit;

use App\Models\SpecType;
use App\Services\SpecValueNormalizerService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SpecValueNormalizerServiceTest extends TestCase
{
    public function test_normalizes_engineering_prefix_in_value_field(): void
    {
        $service = new SpecValueNormalizerService;
        $specType = $this->specType('Ω');

        $normalized = $service->normalizeSpecPayload($specType, [
            'value_profile' => 'typ',
            'value_typ' => '1k',
            'unit' => 'Ω',
        ]);

        $this->assertSame('1000', $normalized['value_numeric_typ']);
        $this->assertSame('1', $normalized['value']);
        $this->assertSame('kΩ', $normalized['unit']);
        $this->assertSame('Ω', $normalized['normalized_unit']);
    }

    public function test_normalizes_inline_uppercase_k_unit(): void
    {
        $service = new SpecValueNormalizerService;
        $specType = $this->specType('Ω');

        $normalized = $service->normalizeSpecPayload($specType, [
            'value_profile' => 'typ',
            'value_typ' => '1KΩ',
            'unit' => '',
        ]);

        $this->assertSame('1000', $normalized['value_numeric_typ']);
        $this->assertSame('1', $normalized['value']);
        $this->assertSame('kΩ', $normalized['unit']);
    }

    public function test_normalizes_micro_prefix_in_value_field(): void
    {
        $service = new SpecValueNormalizerService;
        $specType = $this->specType('F');

        $normalized = $service->normalizeSpecPayload($specType, [
            'value_profile' => 'typ',
            'value_typ' => '4.7u',
            'unit' => 'F',
        ]);

        $this->assertSame('0.0000047', $normalized['value_numeric_typ']);
        $this->assertSame('4.7', $normalized['value']);
        $this->assertSame('uF', $normalized['unit']);
    }

    public function test_normalizes_partial_triple_without_minimum(): void
    {
        $service = new SpecValueNormalizerService;
        $specType = $this->specType('A');

        $normalized = $service->normalizeSpecPayload($specType, [
            'value_profile' => 'triple',
            'value_min' => '',
            'value_typ' => '5',
            'value_max' => '10',
            'unit' => 'mA',
        ]);

        $this->assertSame('triple', $normalized['value_profile']);
        $this->assertNull($normalized['value_numeric_min']);
        $this->assertSame('0.005', $normalized['value_numeric_typ']);
        $this->assertSame('0.01', $normalized['value_numeric_max']);
        $this->assertSame('5 / 10', $normalized['value']);
        $this->assertSame('mA', $normalized['unit']);
    }

    private function specType(string $baseUnit): SpecType
    {
        $specType = new SpecType(['base_unit' => $baseUnit]);
        $specType->setRelation('units', new Collection);

        return $specType;
    }
}

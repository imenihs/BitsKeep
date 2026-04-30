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

    public function test_normalizes_byte_and_bit_units_with_decimal_and_iec_prefixes(): void
    {
        $service = new SpecValueNormalizerService;

        $decimalByte = $service->normalizeSpecPayload($this->specType('B'), [
            'value_profile' => 'typ',
            'value_typ' => '1MB',
            'unit' => '',
        ]);

        $this->assertSame('1000000', $decimalByte['value_numeric_typ']);
        $this->assertSame('1', $decimalByte['value']);
        $this->assertSame('MB', $decimalByte['unit']);

        $iecByte = $service->normalizeSpecPayload($this->specType('B', ['Mi', 'Ki', '']), [
            'value_profile' => 'typ',
            'value_typ' => '1MiB',
            'unit' => '',
        ]);

        $this->assertSame('1048576', $iecByte['value_numeric_typ']);
        $this->assertSame('1', $iecByte['value']);
        $this->assertSame('MiB', $iecByte['unit']);

        $iecInlinePrefix = $service->normalizeSpecPayload($this->specType('B', ['Mi', 'Ki', '']), [
            'value_profile' => 'typ',
            'value_typ' => '512Ki',
            'unit' => 'B',
        ]);

        $this->assertSame('524288', $iecInlinePrefix['value_numeric_typ']);
        $this->assertSame('512', $iecInlinePrefix['value']);
        $this->assertSame('KiB', $iecInlinePrefix['unit']);

        $decimalBit = $service->normalizeSpecPayload($this->specType('bit'), [
            'value_profile' => 'typ',
            'value_typ' => '1Mbit',
            'unit' => '',
        ]);

        $this->assertSame('1000000', $decimalBit['value_numeric_typ']);
        $this->assertSame('Mbit', $decimalBit['unit']);

        $decimalBps = $service->normalizeSpecPayload($this->specType('bps'), [
            'value_profile' => 'typ',
            'value_typ' => '1Mbps',
            'unit' => '',
        ]);

        $this->assertSame('1000000', $decimalBps['value_numeric_typ']);
        $this->assertSame('Mbps', $decimalBps['unit']);
    }

    private function specType(string $baseUnit, array $displayPrefixes = []): SpecType
    {
        $specType = new SpecType([
            'base_unit' => $baseUnit,
            'display_prefixes' => $displayPrefixes,
        ]);
        $specType->setRelation('units', new Collection);

        return $specType;
    }
}

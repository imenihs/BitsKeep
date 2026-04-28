<?php

namespace Tests\Feature;

use App\Models\SpecType;
use Database\Seeders\SpecSymbolNotationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecSymbolNotationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_spec_symbols_are_normalized_to_subscript_plain_notation(): void
    {
        $collectorVoltage = SpecType::create([
            'name' => 'コレクタ-エミッタ間電圧',
            'name_ja' => 'コレクタ-エミッタ間電圧',
            'symbol' => 'VCEO',
        ]);
        $saturationVoltage = SpecType::create([
            'name' => 'コレクタ-エミッタ飽和電圧',
            'name_ja' => 'コレクタ-エミッタ飽和電圧',
            'symbol' => 'VCE(sat)',
        ]);
        $customSupplyVoltage = SpecType::create([
            'name' => '電源電圧',
            'name_ja' => '電源電圧',
            'symbol' => 'V_DD',
        ]);

        $this->seed(SpecSymbolNotationSeeder::class);

        $this->assertSame('V_CEO', $collectorVoltage->refresh()->symbol);
        $this->assertSame('V_CE-(sat)', $saturationVoltage->refresh()->symbol);
        $this->assertSame('V_DD', $customSupplyVoltage->refresh()->symbol);

        $this->assertDatabaseHas('spec_type_aliases', [
            'spec_type_id' => $collectorVoltage->id,
            'alias' => 'VCEO',
        ]);
        $this->assertDatabaseHas('spec_type_aliases', [
            'spec_type_id' => $collectorVoltage->id,
            'alias' => 'V_CEO',
            'kind' => 'symbol',
        ]);
    }
}

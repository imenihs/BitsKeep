<?php

namespace Tests\Feature;

use App\Models\SpecType;
use Database\Seeders\SpecSymbolNotationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecSymbolNotationSeederTest extends TestCase
{
    use RefreshDatabase;
    /**
     * 目的: 「spec symbols are normalized to subscript plain notation」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
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

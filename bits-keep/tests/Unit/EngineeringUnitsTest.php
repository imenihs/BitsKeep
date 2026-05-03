<?php

namespace Tests\Unit;

use App\Support\EngineeringUnits;
use Tests\TestCase;

class EngineeringUnitsTest extends TestCase
{
    /**
     * 目的: 「normalizes prefix tokens and filters lists」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_normalizes_prefix_tokens_and_filters_lists(): void
    {
        $this->assertSame('k', EngineeringUnits::normalizePrefix('K'));
        $this->assertSame('u', EngineeringUnits::normalizePrefix('µ'));
        $this->assertSame('u', EngineeringUnits::normalizePrefix('μ'));
        $this->assertSame('meg', EngineeringUnits::normalizePrefix('MEG'));
        $this->assertSame(['k', 'u', 'meg'], EngineeringUnits::normalizePrefixList(['K', 'µ', 'bad', 'MEG', 'k']));
        $this->assertSame(['bad'], EngineeringUnits::invalidPrefixes(['K', 'bad']));
        $this->assertSame(['meg'], EngineeringUnits::invalidPrefixes(['MEG'], EngineeringUnits::UNIVERSAL_PREFIX_ORDER));
    }
    /**
     * 目的: 「detects byte bit units and prefixed base units」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_detects_byte_bit_units_and_prefixed_base_units(): void
    {
        $this->assertTrue(EngineeringUnits::isByteBitUnit('B'));
        $this->assertTrue(EngineeringUnits::isByteBitUnit('bit'));
        $this->assertTrue(EngineeringUnits::isByteBitUnit('bps'));
        $this->assertFalse(EngineeringUnits::isByteBitUnit('V'));

        $this->assertSame(['unit' => 'F', 'prefix' => 'u'], EngineeringUnits::normalizeBaseUnitInput('μF'));
        $this->assertSame(['unit' => 'Ω', 'prefix' => 'k'], EngineeringUnits::normalizeBaseUnitInput('KΩ'));
        $this->assertSame(['unit' => 'B', 'prefix' => 'Mi'], EngineeringUnits::normalizeBaseUnitInput('MiB'));
    }
}

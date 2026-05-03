<?php

namespace Tests\Unit;

use App\Models\SpecType;
use App\Services\SpecValueNormalizerService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SpecValueNormalizerServiceTest extends TestCase
{
    /**
     * 目的: 「normalizes engineering prefix in value field」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
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
    /**
     * 目的: 「normalizes inline uppercase k unit」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
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
    /**
     * 目的: 「normalizes meg prefix in inline unit」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_normalizes_meg_prefix_in_inline_unit(): void
    {
        $service = new SpecValueNormalizerService;
        $specType = $this->specType('Ω');

        $normalized = $service->normalizeSpecPayload($specType, [
            'value_profile' => 'typ',
            'value_typ' => '1MEGΩ',
            'unit' => '',
        ]);

        $this->assertSame('1000000', $normalized['value_numeric_typ']);
        $this->assertSame('1', $normalized['value']);
        $this->assertSame('MΩ', $normalized['unit']);
    }
    /**
     * 目的: 「normalizes display prefix policy before humanizing」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_normalizes_display_prefix_policy_before_humanizing(): void
    {
        $service = new SpecValueNormalizerService;
        $specType = $this->specType('Ω', ['K']);

        $normalized = $service->normalizeSpecPayload($specType, [
            'value_profile' => 'typ',
            'value_typ' => '4700',
            'unit' => 'Ω',
        ]);

        $this->assertSame('4700', $normalized['value_numeric_typ']);
        $this->assertSame('4.7', $normalized['value']);
        $this->assertSame('kΩ', $normalized['unit']);
    }
    /**
     * 目的: 「normalizes micro prefix in value field」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
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
    /**
     * 目的: 「normalizes partial triple without minimum」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
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
    /**
     * 目的: 「normalizes byte and bit units with decimal and iec prefixes」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
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
    /**
     * 目的: スペックtypeの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: $baseUnit, $displayPrefixes。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
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

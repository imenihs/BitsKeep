<?php

namespace Tests\Feature;

use App\Models\SpecType;
use App\Services\SpecTypeMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * データシート解析で得たスペック名を既存のスペック詳細へ対応づける際、
 * 電気的にありえない対応が起きないことを固定する。
 *
 * 誤った種別を自信ありげに提示するのは、未分類のまま出すより有害である。
 * 設計者はその値を信じて部品を選ぶため、電圧が容量として登録されれば
 * 判断そのものが壊れる。
 */
class SpecTypeMatchingAccuracyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 目的: 照合に必要な最小限のスペック詳細を用意する。
     * 機能: 電圧・容量・電流・温度の代表的な種別を作る。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: RefreshDatabase でテーブルが初期化されていること。
     * 副作用: テストDBへスペック詳細を作成する。
     */
    private function seedSpecTypes(): void
    {
        // 記号が1文字の種別。型番に含まれる文字と偶然一致しやすく、誤マッチの元になる
        SpecType::create(['name' => '容量', 'name_ja' => '容量', 'name_en' => 'Capacitance', 'symbol' => 'C', 'base_unit' => 'F']);
        SpecType::create(['name' => '電圧', 'name_ja' => '電圧', 'name_en' => 'Voltage', 'symbol' => 'V', 'base_unit' => 'V']);
        SpecType::create(['name' => '電流', 'name_ja' => '電流', 'name_en' => 'Current', 'symbol' => 'I', 'base_unit' => 'A']);
        // より具体的な種別。単に「電圧」と対応づけるより望ましい
        SpecType::create(['name' => 'コレクタ-ベース間電圧', 'name_ja' => 'コレクタ-ベース間電圧', 'name_en' => 'Collector-Base Voltage', 'symbol' => 'V_CBO', 'base_unit' => 'V']);
        SpecType::create(['name' => '端子間容量', 'name_ja' => '端子間容量', 'name_en' => 'Junction Capacitance', 'symbol' => 'C_j', 'base_unit' => 'F']);
        SpecType::create(['name' => 'ジャンクション温度', 'name_ja' => 'ジャンクション温度', 'name_en' => 'Junction Temperature', 'symbol' => 'T_j', 'base_unit' => '℃']);
    }

    /**
     * 目的: スペック名に型番が含まれていても、電圧が容量へ化けないことを検証する。
     * 機能: 複数品種を併記したデータシート由来の名前を照合する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_part_number_in_spec_name_does_not_cause_wrong_category(): void
    {
        $this->seedSpecTypes();

        // 実際のデータシート（2SC1213 / 2SC1213A 併記）で起きた誤マッチの再現。
        // 型番の「C」が容量の記号「C」に部分一致し、電圧が容量に化けていた
        $matched = app(SpecTypeMatchingService::class)->match([
            [
                'name' => 'コレクタ・ベース電圧 2SC1213',
                'name_ja' => 'コレクタ-ベース間電圧',
                'name_en' => 'Collector-Base Voltage',
                'symbol' => 'V_CBO',
                'unit' => 'V',
            ],
        ]);

        $this->assertNotSame('容量', $matched[0]['spec_type_name'], '電圧の項目が容量へ対応づけられてはならない');
        $this->assertSame('コレクタ-ベース間電圧', $matched[0]['spec_type_name']);
    }

    /**
     * 目的: 単位が食い違う種別へ対応づけないことを検証する。
     * 機能: 名前が紛らわしくても単位で候補を絞れることを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_unit_mismatch_is_never_matched(): void
    {
        $this->seedSpecTypes();

        $matched = app(SpecTypeMatchingService::class)->match([
            // 単位はファラド。電圧や電流の種別へ対応づいてはならない
            ['name' => '端子間容量', 'name_ja' => '端子間容量', 'name_en' => 'Junction Capacitance', 'symbol' => 'C_ob', 'unit' => 'pF'],
        ]);

        $this->assertSame('端子間容量', $matched[0]['spec_type_name']);
    }

    /**
     * 目的: 接頭辞付きの単位でも基本単位の種別へ対応づくことを検証する。
     * 機能: mV / pF / mA / kΩ のような実務上よくある表記を照合する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_unit_prefix_is_resolved_to_base_unit(): void
    {
        $this->seedSpecTypes();

        $matched = app(SpecTypeMatchingService::class)->match([
            // ミリアンペア表記でも電流として扱えること
            ['name' => 'コレクタ電流', 'name_ja' => '電流', 'name_en' => 'Current', 'symbol' => 'I_C', 'unit' => 'mA'],
        ]);

        $this->assertSame('電流', $matched[0]['spec_type_name']);
    }

    /**
     * 目的: 摂氏の表記揺れを吸収できることを検証する。
     * 機能: ℃ と °C のどちらでも同じ種別へ対応づくことを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_temperature_unit_notation_variants_match(): void
    {
        $this->seedSpecTypes();

        $service = app(SpecTypeMatchingService::class);
        foreach (['℃', '°C'] as $unit) {
            $matched = $service->match([
                ['name' => 'ジャンクション温度', 'name_ja' => 'ジャンクション温度', 'name_en' => 'Junction Temperature', 'symbol' => 'T_j', 'unit' => $unit],
            ]);

            $this->assertSame('ジャンクション温度', $matched[0]['spec_type_name'], "単位表記 {$unit} で対応づけできること");
        }
    }

    /**
     * 目的: 根拠が乏しい場合は無理に対応づけないことを検証する。
     * 機能: マスタに該当のない項目が未分類として返ることを確認する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabase で実行されること。
     * 副作用: テストDBを利用する。
     */
    public function test_unknown_spec_is_left_unmatched_instead_of_guessing(): void
    {
        $this->seedSpecTypes();

        $matched = app(SpecTypeMatchingService::class)->match([
            // マスタに存在しない項目。近いものへ当てずに未分類とする
            ['name' => 'ベース拡がり抵抗', 'name_ja' => 'ベース拡がり抵抗', 'name_en' => 'Base Spreading Resistance', 'symbol' => 'r_bb', 'unit' => 'Ω'],
        ]);

        $this->assertFalse($matched[0]['matched'], '該当する種別がない項目は未分類とすること');
        $this->assertNull($matched[0]['spec_type_id']);
    }
}

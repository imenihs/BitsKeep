<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Location;
use App\Models\SpecGroup;
use App\Models\SpecType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkSearchApiTest extends TestCase
{
    use RefreshDatabase;
    /**
     * 目的: setupの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]));
    }
    /**
     * 目的: 「resistor series and parallel custom values are calculated」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_resistor_series_and_parallel_custom_values_are_calculated(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 2000,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [1000],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_value', 2000)
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'series');

        $this->postJson('/api/calc/networks/search', [
            'target' => 500,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [1000],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_value', 500)
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'parallel');
    }
    /**
     * 目的: 「resistor network candidates include adopted element tolerance rss and corner ranges」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_resistor_network_candidates_include_adopted_element_tolerance_rss_and_corner_ranges(): void
    {
        $seriesResponse = $this->postJson('/api/calc/networks/search', [
            'target' => 20000,
            'tolerance_pct' => 0.001,
            'element_tolerance_pct' => 5,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [10000],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ]);

        $seriesResponse
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_value', 20000)
            ->assertJsonPath('data.result.candidates.0.actual_display', '20kΩ')
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'series')
            ->assertJsonPath('data.result.candidates.0.low_equivalent_display', '19kΩ')
            ->assertJsonPath('data.result.candidates.0.high_equivalent_display', '21kΩ')
            ->assertJsonPath('data.result.candidates.0.rss_max_target_deviation_display', '3.5355%')
            ->assertJsonPath('data.result.candidates.0.max_target_deviation_display', '5%');
        $this->assertEqualsWithDelta(19292.893218813, $seriesResponse->json('data.result.candidates.0.rss_low_equivalent_value'), 1e-6);
        $this->assertEqualsWithDelta(20707.106781187, $seriesResponse->json('data.result.candidates.0.rss_high_equivalent_value'), 1e-6);
        $this->assertEqualsWithDelta(3.5355, $seriesResponse->json('data.result.candidates.0.rss_max_target_deviation_pct'), 1e-4);
        $this->assertEqualsWithDelta(19000, $seriesResponse->json('data.result.candidates.0.low_equivalent_value'), 1e-9);
        $this->assertEqualsWithDelta(21000, $seriesResponse->json('data.result.candidates.0.high_equivalent_value'), 1e-9);
        $this->assertEqualsWithDelta(5, $seriesResponse->json('data.result.candidates.0.max_target_deviation_pct'), 1e-9);

        $parallelResponse = $this->postJson('/api/calc/networks/search', [
            'target' => 5000,
            'tolerance_pct' => 0.001,
            'element_tolerance_pct' => 5,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [10000],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ]);

        $parallelResponse
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_value', 5000)
            ->assertJsonPath('data.result.candidates.0.actual_display', '5kΩ')
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'parallel')
            ->assertJsonPath('data.result.candidates.0.low_equivalent_display', '4.75kΩ')
            ->assertJsonPath('data.result.candidates.0.high_equivalent_display', '5.25kΩ')
            ->assertJsonPath('data.result.candidates.0.rss_max_target_deviation_display', '3.5355%')
            ->assertJsonPath('data.result.candidates.0.max_target_deviation_display', '5%');
        $this->assertEqualsWithDelta(4823.2233047034, $parallelResponse->json('data.result.candidates.0.rss_low_equivalent_value'), 1e-6);
        $this->assertEqualsWithDelta(5176.7766952966, $parallelResponse->json('data.result.candidates.0.rss_high_equivalent_value'), 1e-6);
        $this->assertEqualsWithDelta(3.5355, $parallelResponse->json('data.result.candidates.0.rss_max_target_deviation_pct'), 1e-4);
        $this->assertEqualsWithDelta(4750, $parallelResponse->json('data.result.candidates.0.low_equivalent_value'), 1e-9);
        $this->assertEqualsWithDelta(5250, $parallelResponse->json('data.result.candidates.0.high_equivalent_value'), 1e-9);
        $this->assertEqualsWithDelta(5, $parallelResponse->json('data.result.candidates.0.max_target_deviation_pct'), 1e-9);

        $threeSeriesResponse = $this->postJson('/api/calc/networks/search', [
            'target' => 30000,
            'tolerance_pct' => 0.001,
            'element_tolerance_pct' => 5,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [10000],
            'min_elements' => 3,
            'max_elements' => 3,
            'circuit_types' => ['series'],
        ]);

        $threeSeriesResponse
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '30kΩ')
            ->assertJsonPath('data.result.candidates.0.rss_max_target_deviation_display', '2.8868%')
            ->assertJsonPath('data.result.candidates.0.max_target_deviation_display', '5%');
        $this->assertEqualsWithDelta(2.8868, $threeSeriesResponse->json('data.result.candidates.0.rss_max_target_deviation_pct'), 1e-4);
    }
    /**
     * 目的: 「capacitor series and parallel use capacitance rules」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_capacitor_series_and_parallel_use_capacitance_rules(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 5e-8,
            'tolerance_pct' => 0.01,
            'part_type' => 'C',
            'series' => 'custom',
            'custom_values' => [1e-7],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '50nF')
            ->assertJsonPath('data.result.candidates.0.topology_label', '容量直列');

        $this->postJson('/api/calc/networks/search', [
            'target' => 2e-7,
            'tolerance_pct' => 0.01,
            'part_type' => 'C',
            'series' => 'custom',
            'custom_values' => [1e-7],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '200nF')
            ->assertJsonPath('data.result.candidates.0.topology_label', '容量並列');
    }
    /**
     * 目的: 「capacitor network candidates include adopted element tolerance rss and corner ranges」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_capacitor_network_candidates_include_adopted_element_tolerance_rss_and_corner_ranges(): void
    {
        $response = $this->postJson('/api/calc/networks/search', [
            'target' => 5e-8,
            'tolerance_pct' => 0.001,
            'element_tolerance_pct' => 10,
            'part_type' => 'C',
            'series' => 'custom',
            'custom_values' => [1e-7],
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '50nF')
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'series')
            ->assertJsonPath('data.result.candidates.0.low_equivalent_display', '45nF')
            ->assertJsonPath('data.result.candidates.0.high_equivalent_display', '55nF')
            ->assertJsonPath('data.result.candidates.0.rss_max_target_deviation_display', '7.0711%')
            ->assertJsonPath('data.result.candidates.0.max_target_deviation_display', '10%');
        $this->assertEqualsWithDelta(46.464466094e-9, $response->json('data.result.candidates.0.rss_low_equivalent_value'), 1e-18);
        $this->assertEqualsWithDelta(53.535533906e-9, $response->json('data.result.candidates.0.rss_high_equivalent_value'), 1e-18);
        $this->assertEqualsWithDelta(7.0711, $response->json('data.result.candidates.0.rss_max_target_deviation_pct'), 1e-4);
        $this->assertEqualsWithDelta(45e-9, $response->json('data.result.candidates.0.low_equivalent_value'), 1e-18);
        $this->assertEqualsWithDelta(55e-9, $response->json('data.result.candidates.0.high_equivalent_value'), 1e-18);
        $this->assertEqualsWithDelta(10, $response->json('data.result.candidates.0.max_target_deviation_pct'), 1e-9);
    }
    /**
     * 目的: 「divider ratio and total resistance range are checked」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_divider_ratio_and_total_resistance_range_are_checked(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 0.5,
            'tolerance_pct' => 0.01,
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'total_res_min' => 15000,
            'total_res_max' => 25000,
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '50%')
            ->assertJsonPath('data.result.candidates.0.total_display', '20kΩ');

        $this->postJson('/api/calc/networks/search', [
            'target' => 1.2,
            'part_type' => 'divider',
        ])
            ->assertOk()
            ->assertJsonPath('data.result', null)
            ->assertJsonPath('data.summary', '分圧比は 0 より大きく 1 より小さい値で指定してください');
    }
    /**
     * 目的: 「divider uses input and output voltage with infinite load」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_divider_uses_input_and_output_voltage_with_infinite_load(): void
    {
        $response = $this->postJson('/api/calc/networks/search', [
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'input_voltage' => 3.3,
            'output_voltage' => 1.65,
            'load_resistance_infinite' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.result.target_display', '1.65V / 3.3V = 50%')
            ->assertJsonPath('data.result.candidates.0.actual_display', '50%')
            ->assertJsonPath('data.result.candidates.0.actual_output_display', '1.65V')
            ->assertJsonPath('data.result.candidates.0.load_display', '∞Ω')
            ->assertJsonPath('data.result.candidates.0.source_current_display', '165μA')
            ->assertJsonPath('data.result.candidates.0.upper_power_display', '272.25μW')
            ->assertJsonPath('data.result.candidates.0.lower_power_display', '272.25μW')
            ->assertJsonPath('data.result.candidates.0.resistor_power_display', '544.5μW');

        $this->assertEqualsWithDelta(0.5, $response->json('data.result.candidates.0.actual_value'), 1e-12);
        $this->assertEqualsWithDelta(1.65, $response->json('data.result.candidates.0.actual_output_voltage'), 1e-12);
        $this->assertEqualsWithDelta(0.000165, $response->json('data.result.candidates.0.source_current'), 1e-12);
        $this->assertEqualsWithDelta(0.00027225, $response->json('data.result.candidates.0.upper_power'), 1e-12);
    }
    /**
     * 目的: 「divider candidates include per resistor tolerance output ranges」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_divider_candidates_include_per_resistor_tolerance_output_ranges(): void
    {
        $response = $this->postJson('/api/calc/networks/search', [
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'input_voltage' => 5,
            'output_voltage' => 2.5,
            'divider_upper_tolerance_pct' => 1,
            'divider_lower_tolerance_pct' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_output_display', '2.5V')
            ->assertJsonPath('data.result.candidates.0.divider_tolerance_display', 'R1 ±1% / R2 ±1%')
            ->assertJsonPath('data.result.candidates.0.divider_rss_max_error_pct_display', '0.7071%')
            ->assertJsonPath('data.result.candidates.0.divider_corner_max_error_pct_display', '1%');

        $this->assertEqualsWithDelta(2.48232233047, $response->json('data.result.candidates.0.divider_rss_low_ratio') * 5, 1e-9);
        $this->assertEqualsWithDelta(2.51767766953, $response->json('data.result.candidates.0.divider_rss_high_ratio') * 5, 1e-9);
        $this->assertEqualsWithDelta(2.475, $response->json('data.result.candidates.0.divider_corner_low_ratio') * 5, 1e-12);
        $this->assertEqualsWithDelta(2.525, $response->json('data.result.candidates.0.divider_corner_high_ratio') * 5, 1e-12);
    }
    /**
     * 目的: 「divider resistance load makes 10k pair one third ratio」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_divider_resistance_load_makes_10k_pair_one_third_ratio(): void
    {
        $response = $this->postJson('/api/calc/networks/search', [
            'target' => 1 / 3,
            'tolerance_pct' => 0.01,
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'load_type' => 'resistance',
            'load_resistance' => 10000,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.load_display', '10kΩ')
            ->assertJsonPath('data.result.candidates.0.total_display', '20kΩ');

        $this->assertEqualsWithDelta(1 / 3, $response->json('data.result.candidates.0.actual_value'), 1e-12);
    }
    /**
     * 目的: 「divider current load without input voltage returns invalid response」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_divider_current_load_without_input_voltage_returns_invalid_response(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 0.5,
            'part_type' => 'divider',
            'series' => 'custom',
            'custom_values' => [10000],
            'load_type' => 'current',
            'load_current' => 0.001,
        ])
            ->assertOk()
            ->assertJsonPath('data.result', null)
            ->assertJsonPath('data.summary', '電流負荷の計算には入力電圧が必要です');
    }
    /**
     * 目的: 「e series pair search does not drop parallel exact match」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_e_series_pair_search_does_not_drop_parallel_exact_match(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 909.090909,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'series' => 'E24',
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '909.090909Ω')
            ->assertJsonPath('data.result.candidates.0.circuit_type', 'parallel');
    }
    /**
     * 目的: 「e48 and e96 pair search reaches exact matches before evaluation limit」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_e48_and_e96_pair_search_reaches_exact_matches_before_evaluation_limit(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 20000,
            'tolerance_pct' => 0.001,
            'part_type' => 'R',
            'series' => 'E48',
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series', 'parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.evaluation_limited', false)
            ->assertJsonPath('data.result.candidates.0.actual_display', '20kΩ')
            ->assertJsonPath('data.result.candidates.0.expression', '10kΩ + 10kΩ');

        $this->postJson('/api/calc/networks/search', [
            'target' => 5000,
            'tolerance_pct' => 0.001,
            'part_type' => 'R',
            'series' => 'E96',
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.evaluation_limited', false)
            ->assertJsonPath('data.result.candidates.0.actual_display', '5kΩ')
            ->assertJsonPath('data.result.candidates.0.expression', '10kΩ ∥ 10kΩ');

        $this->postJson('/api/calc/networks/search', [
            'target' => 5e-8,
            'tolerance_pct' => 0.001,
            'part_type' => 'C',
            'series' => 'E48',
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.evaluation_limited', false)
            ->assertJsonPath('data.result.candidates.0.actual_display', '50nF')
            ->assertJsonPath('data.result.candidates.0.expression', '100nF + 100nF');
    }
    /**
     * 目的: 「divider search uses full e series for tight total range」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_divider_search_uses_full_e_series_for_tight_total_range(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 0.5,
            'tolerance_pct' => 0.01,
            'part_type' => 'divider',
            'series' => 'E96',
            'min_elements' => 4,
            'max_elements' => 2,
            'total_res_min' => 19990,
            'total_res_max' => 20010,
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '50%')
            ->assertJsonPath('data.result.candidates.0.total_display', '20kΩ');
    }
    /**
     * 目的: 「invalid element range returns design analysis warning」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_invalid_element_range_returns_design_analysis_warning(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'part_type' => 'R',
            'min_elements' => 4,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result', null)
            ->assertJsonPath('data.summary', '素子数の最小値が最大値を超えています');
    }
    /**
     * 目的: 「empty custom values return invalid response」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_empty_custom_values_return_invalid_response(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.result', null)
            ->assertJsonPath('data.summary', '任意値が入力されていません');
    }
    /**
     * 目的: 「too many custom values are rejected」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_too_many_custom_values_are_rejected(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => range(1, 257),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_values');
    }
    /**
     * 目的: 「evaluation limit counts no hit attempts」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_evaluation_limit_counts_no_hit_attempts(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 123456789,
            'tolerance_pct' => 0.001,
            'part_type' => 'R',
            'series' => 'custom',
            'custom_values' => range(1, 256),
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series', 'parallel'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.evaluation_limited', true)
            ->assertJsonPath('data.result.truncated', true)
            ->assertJsonCount(0, 'data.result.candidates');
    }
    /**
     * 目的: 「three element series exact match is not lost by pool limit」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_three_element_series_exact_match_is_not_lost_by_pool_limit(): void
    {
        $this->postJson('/api/calc/networks/search', [
            'target' => 1000,
            'tolerance_pct' => 0.001,
            'part_type' => 'R',
            'series' => 'E24',
            'min_elements' => 3,
            'max_elements' => 3,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_display', '1kΩ')
            ->assertJsonPath('data.result.candidates.0.elements_count', 3);
    }
    /**
     * 目的: 「inventory search uses matching value spec not first numeric spec」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_inventory_search_uses_matching_value_spec_not_first_numeric_spec(): void
    {
        $group = SpecGroup::create(['name' => '抵抗器', 'sort_order' => 10]);
        $voltageSpec = SpecType::create(['name' => '定格電圧', 'name_ja' => '定格電圧', 'base_unit' => 'V']);
        $resistanceSpec = SpecType::create(['name' => '抵抗値', 'name_ja' => '抵抗値', 'base_unit' => 'Ω']);
        $location = Location::create(['code' => 'N-1', 'name' => 'ネットワーク探索棚']);
        $component = Component::create([
            'manufacturer' => 'Test',
            'part_number' => 'RK73-4K7',
            'common_name' => '4.7kΩテスト抵抗',
            'quantity_new' => 10,
            'quantity_used' => 0,
            'threshold_new' => 0,
            'threshold_used' => 0,
        ]);
        $component->categories()->sync([$group->id]);
        $component->specs()->create([
            'spec_type_id' => $voltageSpec->id,
            'display_name' => '定格電圧',
            'value_profile' => 'typ',
            'value_numeric_typ' => 50,
            'normalized_unit' => 'V',
        ]);
        $component->specs()->create([
            'spec_type_id' => $resistanceSpec->id,
            'display_name' => '抵抗値',
            'value_profile' => 'typ',
            'value_numeric_typ' => 4700,
            'normalized_unit' => 'Ω',
        ]);
        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'loose',
            'condition' => 'new',
            'quantity' => 10,
        ]);

        $this->postJson('/api/calc/networks/search', [
            'target' => 4700,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'inventory_only' => true,
            'min_elements' => 1,
            'max_elements' => 1,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonPath('data.result.candidates.0.actual_value', 4700)
            ->assertJsonPath('data.result.candidates.0.parts.0.component_id', $component->id)
            ->assertJsonPath('data.result.candidates.0.parts.0.stock_quantity', 10);
    }
    /**
     * 目的: 「inventory search excludes component without matching value spec」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_inventory_search_excludes_component_without_matching_value_spec(): void
    {
        $group = SpecGroup::create(['name' => '抵抗器', 'sort_order' => 10]);
        $voltageSpec = SpecType::create(['name' => '定格電圧', 'name_ja' => '定格電圧', 'base_unit' => 'V']);
        $location = Location::create(['code' => 'N-2', 'name' => '抵抗値なし棚']);
        $component = Component::create([
            'manufacturer' => 'Test',
            'part_number' => 'NO-R-VALUE',
            'common_name' => '抵抗値なしテスト抵抗',
            'quantity_new' => 10,
            'quantity_used' => 0,
            'threshold_new' => 0,
            'threshold_used' => 0,
        ]);
        $component->categories()->sync([$group->id]);
        $component->specs()->create([
            'spec_type_id' => $voltageSpec->id,
            'display_name' => '定格電圧',
            'value_profile' => 'typ',
            'value_numeric_typ' => 50,
            'normalized_unit' => 'V',
        ]);
        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'loose',
            'condition' => 'new',
            'quantity' => 10,
        ]);

        $this->postJson('/api/calc/networks/search', [
            'target' => 50,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'inventory_only' => true,
            'min_elements' => 1,
            'max_elements' => 1,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.result.candidates');
    }
    /**
     * 目的: 「inventory search excludes temperature coefficient as resistance value」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_inventory_search_excludes_temperature_coefficient_as_resistance_value(): void
    {
        $group = SpecGroup::create(['name' => '抵抗器', 'sort_order' => 10]);
        $tcrSpec = SpecType::create(['name' => '抵抗温度係数', 'name_ja' => '抵抗温度係数', 'base_unit' => 'ppm/℃']);
        $location = Location::create(['code' => 'N-3', 'name' => 'TCR棚']);
        $component = Component::create([
            'manufacturer' => 'Test',
            'part_number' => 'TCR-ONLY',
            'common_name' => 'TCRのみ抵抗',
            'quantity_new' => 10,
            'quantity_used' => 0,
            'threshold_new' => 0,
            'threshold_used' => 0,
        ]);
        $component->categories()->sync([$group->id]);
        $component->specs()->create([
            'spec_type_id' => $tcrSpec->id,
            'display_name' => '抵抗温度係数',
            'value_profile' => 'typ',
            'value_numeric_typ' => 100,
            'normalized_unit' => 'ppm/℃',
        ]);
        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'loose',
            'condition' => 'new',
            'quantity' => 10,
        ]);

        $this->postJson('/api/calc/networks/search', [
            'target' => 100,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'inventory_only' => true,
            'min_elements' => 1,
            'max_elements' => 1,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.result.candidates');
    }
    /**
     * 目的: 「inventory search does not reuse component beyond stock quantity」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_inventory_search_does_not_reuse_component_beyond_stock_quantity(): void
    {
        $group = SpecGroup::create(['name' => '抵抗器', 'sort_order' => 10]);
        $resistanceSpec = SpecType::create(['name' => '抵抗値', 'name_ja' => '抵抗値', 'base_unit' => 'Ω']);
        $location = Location::create(['code' => 'N-4', 'name' => '在庫1棚']);
        $component = Component::create([
            'manufacturer' => 'Test',
            'part_number' => 'ONE-STOCK',
            'common_name' => '在庫1抵抗',
            'quantity_new' => 1,
            'quantity_used' => 0,
            'threshold_new' => 0,
            'threshold_used' => 0,
        ]);
        $component->categories()->sync([$group->id]);
        $component->specs()->create([
            'spec_type_id' => $resistanceSpec->id,
            'display_name' => '抵抗値',
            'value_profile' => 'typ',
            'value_numeric_typ' => 123456,
            'normalized_unit' => 'Ω',
        ]);
        $component->inventoryBlocks()->create([
            'location_id' => $location->id,
            'stock_type' => 'loose',
            'condition' => 'new',
            'quantity' => 1,
        ]);

        $this->postJson('/api/calc/networks/search', [
            'target' => 246912,
            'tolerance_pct' => 0.01,
            'part_type' => 'R',
            'inventory_only' => true,
            'min_elements' => 2,
            'max_elements' => 2,
            'circuit_types' => ['series'],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.result.candidates');
    }
}

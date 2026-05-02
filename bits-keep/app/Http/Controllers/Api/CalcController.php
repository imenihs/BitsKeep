<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\DesignAnalysisResponse;
use App\Services\NetworkSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 計算ツール API（SCR-015/016）
 * 各種設計解析ツールのバックエンド計算エンドポイント。
 */
class CalcController extends Controller
{
    private const MAX_CUSTOM_VALUES = 256;

    /**
     * POST /api/calc/networks/search
     * 抵抗/容量ネットワーク探索（FNC-022）
     *
     * Request:
     *   target        float   目標値（Ω/F/分圧比）
     *   input_voltage float   分圧の入力電圧。指定時は output_voltage / input_voltage を target として扱う
     *   output_voltage float  分圧の出力電圧。指定時は output_voltage / input_voltage を target として扱う
     *   tolerance_pct float   許容誤差 % (default: 5.0)
     *   part_type     string  'R' | 'C' | 'divider' (default: 'R')
     *   series        string  'E6'|'E12'|'E24'|'E48'|'E96'|'custom' (default: 'E24')
     *   custom_values float[] series='custom' の時の値リスト (max: 256)
     *   min_elements  int     素子数下限 (default: 1)
     *   max_elements  int     素子数上限 (default: 3, max: 4)
     *   inventory_only bool   在庫限定フラグ (default: false)
     *   circuit_types string[] 探索回路種別 (default: ['series','parallel'])
     *   total_res_min float  分圧総抵抗下限
     *   total_res_max float  分圧総抵抗上限
     *   load_type     string 分圧負荷 'resistance' | 'current'
     *   load_resistance float|null 抵抗負荷。null と load_resistance_infinite=true は無負荷
     *   load_current  float 電流負荷。0 は無負荷
     *
     * Response:
     *   { candidates: [...], elapsed_ms: int, truncated: bool }
     */
    public function networkSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target'        => ['nullable', 'numeric', 'gt:0'],
            'input_voltage' => ['nullable', 'numeric', 'gt:0'],
            'output_voltage'=> ['nullable', 'numeric', 'gt:0'],
            'tolerance_pct' => ['nullable', 'numeric', 'min:0.001', 'max:50'],
            'part_type'     => ['nullable', 'in:R,C,divider'],
            'series'        => ['nullable', 'in:E6,E12,E24,E48,E96,custom'],
            'custom_values' => ['nullable', 'array', 'max:'.self::MAX_CUSTOM_VALUES],
            'custom_values.*' => ['numeric', 'gt:0'],
            'min_elements'  => ['nullable', 'integer', 'min:1', 'max:4'],
            'max_elements'  => ['nullable', 'integer', 'min:1', 'max:4'],
            'inventory_only'=> ['nullable', 'boolean'],
            'circuit_types' => ['nullable', 'array'],
            'circuit_types.*' => ['in:series,parallel,mixed,divider'],
            'total_res_min' => ['nullable', 'numeric', 'min:0'],
            'total_res_max' => ['nullable', 'numeric', 'min:0'],
            'load_type'     => ['nullable', 'in:resistance,current'],
            'load_resistance' => ['nullable', 'numeric', 'min:0'],
            'load_resistance_infinite' => ['nullable', 'boolean'],
            'load_current'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $partType = $validated['part_type'] ?? 'R';
        if ($partType === 'divider' && isset($validated['input_voltage'], $validated['output_voltage'])) {
            if ((float) $validated['output_voltage'] >= (float) $validated['input_voltage']) {
                return DesignAnalysisResponse::invalid(
                    '出力電圧は入力電圧より低い値で指定してください',
                    ['例: 入力 3.3V、出力 2.5V のように指定してください']
                );
            }
            $validated['target'] = (float) $validated['output_voltage'] / (float) $validated['input_voltage'];
        }
        if (! isset($validated['target'])) {
            return DesignAnalysisResponse::invalid(
                '目標値が入力されていません',
                ['抵抗/容量は目標値、分圧は比率または入力電圧と出力電圧を入力してください']
            );
        }
        if (
            $partType === 'divider'
            && ($validated['load_type'] ?? 'resistance') === 'current'
            && (float) ($validated['load_current'] ?? 0) > 0
            && ! isset($validated['input_voltage'])
        ) {
            return DesignAnalysisResponse::invalid(
                '電流負荷の計算には入力電圧が必要です',
                ['比率指定のまま電流負荷を使う場合も、入力電圧を入力してください']
            );
        }
        if (($validated['series'] ?? null) === 'custom' && empty($validated['custom_values'])) {
            return DesignAnalysisResponse::invalid(
                '任意値が入力されていません',
                ['E系列を選ぶか、任意値を1つ以上入力してください']
            );
        }
        if ($partType === 'divider' && ((float) $validated['target'] <= 0 || (float) $validated['target'] >= 1)) {
            return DesignAnalysisResponse::invalid(
                '分圧比は 0 より大きく 1 より小さい値で指定してください',
                ['例: 0.5 または 50% のように Vout/Vin を入力してください']
            );
        }
        if ($partType !== 'divider' && ($validated['min_elements'] ?? 1) > ($validated['max_elements'] ?? 4)) {
            return DesignAnalysisResponse::invalid(
                '素子数の最小値が最大値を超えています',
                ['最小素子数を最大素子数以下にしてください']
            );
        }
        if (array_key_exists('circuit_types', $validated) && $validated['circuit_types'] === []) {
            return DesignAnalysisResponse::invalid(
                '探索回路種別が選択されていません',
                ['直列、並列、直並列混在のいずれかを選んでください']
            );
        }
        if (
            array_key_exists('total_res_min', $validated)
            && array_key_exists('total_res_max', $validated)
            && $validated['total_res_max'] !== null
            && (float) $validated['total_res_min'] > (float) $validated['total_res_max']
        ) {
            return DesignAnalysisResponse::invalid(
                '分圧総抵抗範囲の最小値が最大値を超えています',
                ['総抵抗の最小値を最大値以下にしてください']
            );
        }

        $service = new NetworkSearchService();
        $result  = $service->search($validated);

        $warnings    = [];
        $nextActions = [];

        if ($result['truncated'] ?? false) {
            $warnings[]    = '候補が多すぎるため上位のみ表示しています';
            $nextActions[] = '許容誤差を狭めるか、素子数の上限を下げてください';
        }
        if ($result['evaluation_limited'] ?? false) {
            $warnings[]    = '探索量上限に達したため途中までの候補を表示しています';
            $nextActions[] = '直並列混在を外すか、E系列/任意値/素子数を絞って再探索してください';
        }
        if (empty($result['candidates'])) {
            $nextActions[] = '許容誤差を広げるか、素子数の上限を増やしてください';
            $nextActions[] = '在庫限定をオフにすると候補が増える場合があります';
        }

        $summary = empty($result['candidates'])
            ? '候補が見つかりませんでした'
            : count($result['candidates']) . ' 件の候補が見つかりました';

        return DesignAnalysisResponse::success($result, $summary, $warnings, $nextActions);
    }
}

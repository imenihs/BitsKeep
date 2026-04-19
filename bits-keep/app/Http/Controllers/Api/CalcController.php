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
    /**
     * POST /api/calc/networks/search
     * 抵抗/容量ネットワーク探索（FNC-022）
     *
     * Request:
     *   target        float   目標値（Ω/F/分圧比）
     *   tolerance_pct float   許容誤差 % (default: 5.0)
     *   part_type     string  'R' | 'C' | 'divider' (default: 'R')
     *   series        string  'E6'|'E12'|'E24'|'E48'|'E96'|'custom' (default: 'E24')
     *   custom_values float[] series='custom' の時の値リスト
     *   min_elements  int     素子数下限 (default: 1)
     *   max_elements  int     素子数上限 (default: 3, max: 4)
     *   inventory_only bool   在庫限定フラグ (default: false)
     *   circuit_types string[] 探索回路種別 (default: ['series','parallel'])
     *   total_res_min float  分圧総抵抗下限
     *   total_res_max float  分圧総抵抗上限
     *
     * Response:
     *   { candidates: [...], elapsed_ms: int, truncated: bool }
     */
    public function networkSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target'        => ['required', 'numeric', 'min:0'],
            'tolerance_pct' => ['nullable', 'numeric', 'min:0.001', 'max:50'],
            'part_type'     => ['nullable', 'in:R,C,divider'],
            'series'        => ['nullable', 'in:E6,E12,E24,E48,E96,custom'],
            'custom_values' => ['nullable', 'array'],
            'custom_values.*' => ['numeric', 'min:0'],
            'min_elements'  => ['nullable', 'integer', 'min:1', 'max:4'],
            'max_elements'  => ['nullable', 'integer', 'min:1', 'max:4'],
            'inventory_only'=> ['nullable', 'boolean'],
            'circuit_types' => ['nullable', 'array'],
            'circuit_types.*' => ['in:series,parallel,mixed,divider'],
            'total_res_min' => ['nullable', 'numeric', 'min:0'],
            'total_res_max' => ['nullable', 'numeric', 'min:0'],
        ]);

        // 目標値0は探索不能
        if ($validated['target'] == 0) {
            return DesignAnalysisResponse::invalid(
                '目標値が 0 のため探索できません',
                ['0より大きい目標値を入力してください']
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

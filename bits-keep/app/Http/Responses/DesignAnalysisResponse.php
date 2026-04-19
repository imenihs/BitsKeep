<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * 設計解析ツール共通レスポンス形式（SCR-016）
 *
 * data: {
 *   result:       計算結果（ツール固有データ）
 *   summary:      結果要約テキスト
 *   warnings:     注意事項・制約違反リスト
 *   chart_model:  グラフ描画用データ（任意）
 *   next_actions: 推奨アクション文言リスト
 * }
 */
class DesignAnalysisResponse
{
    /**
     * 解析成功レスポンスを生成する。
     *
     * @param  mixed        $result      ツール固有の計算結果
     * @param  string       $summary     結果を一言で説明するテキスト
     * @param  string[]     $warnings    注意事項・閾値超過などの警告リスト
     * @param  string[]     $nextActions 次に取るべき推奨アクション
     * @param  array|null   $chartModel  フロントエンドのグラフ描画用データ
     */
    public static function success(
        mixed $result,
        string $summary = '',
        array $warnings = [],
        array $nextActions = [],
        ?array $chartModel = null
    ): JsonResponse {
        $data = ['result' => $result];

        if ($summary !== '') {
            $data['summary'] = $summary;
        }
        if ($warnings !== []) {
            $data['warnings'] = array_values($warnings);
        }
        if ($nextActions !== []) {
            $data['next_actions'] = array_values($nextActions);
        }
        if ($chartModel !== null) {
            $data['chart_model'] = $chartModel;
        }

        return ApiResponse::success($data);
    }

    /**
     * 入力値が計算不能な場合のレスポンスを生成する。
     *
     * @param  string    $reason   計算不能の理由
     * @param  string[]  $nextActions 入力修正の案内
     */
    public static function invalid(string $reason, array $nextActions = []): JsonResponse
    {
        return ApiResponse::success([
            'result'       => null,
            'summary'      => $reason,
            'warnings'     => [$reason],
            'next_actions' => $nextActions,
        ]);
    }
}

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
    /**
     * 目的: 設計解析ツールの判定・指摘・次アクションを成功レスポンスへ整形する。
     * 機能: 呼び出し元から受けた値を検証または整形し、対象処理へ渡す。
     * 入力: 関数シグネチャで指定された引数。
     * 出力: 型宣言または呼び出し規約に従う処理結果。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと入力値を渡すこと。
     * 副作用: なし。
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
     * 目的: Design Analysis Responseのinvalidを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $reason, $nextActions。
     * 出力: JsonResponseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
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

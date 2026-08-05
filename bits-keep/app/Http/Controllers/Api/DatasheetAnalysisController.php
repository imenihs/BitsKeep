<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\AnalyzeDatasheetJob;
use App\Models\DatasheetAnalysis;
use App\Services\Datasheet\DatasheetAnalyzerRegistry;
use App\Services\TempDatasheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * サーバ側で実行するデータシート解析の登録・状態取得・破棄。
 * 解析はキュー経由の非同期実行であり、登録時点では結果を返さない。
 * 画面は解析IDで状態を取得するため、ブラウザのタブを閉じても解析は継続する。
 */
class DatasheetAnalysisController extends Controller
{
    /**
     * 目的: 解析を登録し、解析IDを返す。
     * 機能: PDFを一時保管して（または既存の一時PDFトークンを検証して）解析記録を作り、キューへ流す。
     * 入力: $request は pdf ファイル、または既に預けてある temp_token を持つHTTPリクエスト。
     * 出力: 解析IDと初期状態を含むJSONレスポンス。
     * 動作条件: 認証済みユーザーで、PDFが上限サイズ以内、または一時PDFが有効期限内であること。
     * 副作用: 一時PDFを保存し、解析記録を作成し、キューへジョブを登録する。
     */
    public function store(
        Request $request,
        TempDatasheetService $tempDatasheets,
        DatasheetAnalyzerRegistry $registry,
    ): JsonResponse {
        $request->validate([
            // PDFを直接渡す経路と、既に預けてある一時PDFを指す経路の両方を受ける
            'pdf' => ['required_without:temp_token', 'file', 'mimes:pdf', 'max:20480'],
            'temp_token' => ['required_without:pdf', 'string', 'max:64'],
        ]);

        try {
            $token = $this->resolveTempToken($request, $tempDatasheets);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError(['pdf' => [$e->getMessage()]]);
        } catch (\RuntimeException $e) {
            // 期限切れや消失したPDFでジョブを積まない。ここで弾けば利用者へ即座に理由を返せる
            return ApiResponse::validationError(['temp_token' => [$e->getMessage()]]);
        }

        $analyzer = $registry->active();
        $availability = $analyzer->checkAvailability();
        // 使えないエンジンでジョブを積むと、待たせた末に失敗を見せることになる
        if (! ($availability['available'] ?? false)) {
            return ApiResponse::error(
                (string) ($availability['message'] ?? '解析エンジンが利用できません。連携設定を確認してください。'),
                ['engine' => [$analyzer->key()]],
                403
            );
        }

        $analysis = DatasheetAnalysis::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'temp_token' => $token,
            'engine' => $analyzer->key(),
            'state' => DatasheetAnalysis::STATE_QUEUED,
        ]);

        AnalyzeDatasheetJob::dispatch($analysis->id);

        Log::info('Datasheet analysis queued', [
            'analysis_id' => $analysis->id,
            'user_id' => $request->user()?->id,
            'engine' => $analyzer->key(),
        ]);

        return ApiResponse::created($this->present($analysis), '解析を開始しました');
    }

    /**
     * 目的: 解析の進行状態と結果を返す。
     * 機能: 解析IDから記録を引き、状態と、完了していれば結果を返す。
     * 入力: $publicId は解析登録時に返した解析ID。
     * 出力: 状態と結果を含むJSONレスポンス。
     * 動作条件: 認証済みユーザーで、自分が開始した解析であること。
     * 副作用: なし。
     */
    public function show(Request $request, string $publicId): JsonResponse
    {
        $analysis = $this->findOwned($request, $publicId);
        if ($analysis === null) {
            return ApiResponse::notFound('対象の解析が見つかりません。');
        }

        return ApiResponse::success($this->present($analysis));
    }

    /**
     * 目的: 解析を破棄する。
     * 機能: 未完了の解析を失敗として閉じ、画面のポーリングを終わらせる。
     * 入力: $publicId は解析登録時に返した解析ID。
     * 出力: 破棄後の状態を含むJSONレスポンス。
     * 動作条件: 認証済みユーザーで、自分が開始した解析であること。
     * 副作用: 解析記録の状態を更新する。
     */
    public function destroy(Request $request, string $publicId): JsonResponse
    {
        $analysis = $this->findOwned($request, $publicId);
        if ($analysis === null) {
            return ApiResponse::notFound('対象の解析が見つかりません。');
        }

        // 完了済みの結果は破棄操作で書き換えない。取り違えて結果を失わせないため
        if (! $analysis->isFinished()) {
            // 中止は利用者の操作どおりの結果であり、失敗ではない。
            // 失敗として記録すると画面が不具合のように見え、原因を探させてしまう
            $analysis->update([
                'state' => DatasheetAnalysis::STATE_CANCELED,
                'failure_kind' => null,
                'failure_message' => null,
                'finished_at' => now(),
            ]);
        }

        return ApiResponse::success($this->present($analysis), '解析を中止しました');
    }

    /**
     * 目的: 解析対象の一時PDFトークンを決める。
     * 機能: PDFが渡されていれば一時保管して新しいトークンを作り、渡されていなければ既存トークンの有効性を確認する。
     * 入力: $request はPDFまたはトークンを持つリクエスト、$tempDatasheets は一時PDF保管サービス。
     * 出力: 有効な一時PDFトークン。
     * 動作条件: pdf と temp_token のいずれかが渡されていること。
     * 副作用: PDFが渡された場合は一時PDFを保存する。
     *
     * @throws \InvalidArgumentException PDFの内容が受け付けられない場合
     * @throws \RuntimeException 既存トークンが無効な場合、または保存に失敗した場合
     */
    private function resolveTempToken(Request $request, TempDatasheetService $tempDatasheets): string
    {
        $file = $request->file('pdf');
        if ($file !== null) {
            // 解析対象はこの1件のみ。表示名は元ファイル名をそのまま使う
            $entries = $tempDatasheets->createMany([$file], [$file->getClientOriginalName()]);

            return (string) $entries[0]['token'];
        }

        $token = (string) $request->input('temp_token');
        $tempDatasheets->getActiveMeta($token);

        return $token;
    }

    /**
     * 目的: 自分が開始した解析を引く。
     * 機能: 解析IDで記録を引き、他利用者の解析は返さない。
     * 入力: $request は認証済みリクエスト、$publicId は解析ID。
     * 出力: 解析記録、無ければ null。
     * 動作条件: なし。
     * 副作用: DBを参照する。
     */
    private function findOwned(Request $request, string $publicId): ?DatasheetAnalysis
    {
        $analysis = DatasheetAnalysis::where('public_id', $publicId)->first();
        if ($analysis === null) {
            return null;
        }

        // 解析結果には部品仕様が含まれる。他利用者の解析IDを推測されても返さない
        if ($analysis->user_id !== null && $analysis->user_id !== $request->user()?->id) {
            return null;
        }

        return $analysis;
    }

    /**
     * 目的: 解析記録を画面が扱う形へ整える。
     * 機能: 状態、失敗理由、再実行可否、結果を並べる。
     * 入力: $analysis は解析記録。
     * 出力: 画面へ渡す配列。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array<string, mixed>
     */
    private function present(DatasheetAnalysis $analysis): array
    {
        return [
            'analysis_id' => $analysis->public_id,
            'state' => $analysis->state,
            'finished' => $analysis->isFinished(),
            'engine' => $analysis->engine,
            'failure_kind' => $analysis->failure_kind,
            'failure_message' => $analysis->failure_message,
            // 中止した解析も同じPDFでやり直せるため、再実行導線を出す対象に含める
            'retryable' => in_array($analysis->state, [
                DatasheetAnalysis::STATE_FAILED,
                DatasheetAnalysis::STATE_CANCELED,
            ], true) && $analysis->isRetryable(),
            'input_mode' => $analysis->input_mode,
            'input_page_count' => $analysis->input_page_count,
            // 未完了時に結果キーを持たせると、画面が空の候補を確認モーダルへ渡してしまう
            'result' => $analysis->state === DatasheetAnalysis::STATE_SUCCEEDED ? $analysis->result : null,
        ];
    }
}

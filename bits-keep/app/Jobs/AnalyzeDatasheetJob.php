<?php

namespace App\Jobs;

use App\Exceptions\DatasheetAnalysisException;
use App\Models\DatasheetAnalysis;
use App\Services\Datasheet\DatasheetAnalyzerRegistry;
use App\Services\Datasheet\DatasheetRecommendationDecorator;
use App\Services\SpecTypeMatchingService;
use App\Services\TempDatasheetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * データシート解析1件をサーバ側で実行する。
 * 解析はブラウザのタブ寿命と切り離す必要があるため、HTTPリクエスト内では実行せず
 * このジョブへ委ねる。画面は解析記録の状態を参照して進行を知る。
 */
class AnalyzeDatasheetJob implements ShouldQueue
{
    use Queueable;

    /**
     * 解析エンジンの実行は待ち時間が長いため、自動再試行はしない。
     * 同じPDFを二重に解析すると利用枠を無駄に消費するうえ、
     * 失敗理由は画面へ出して利用者に再実行を選ばせるほうが妥当である。
     */
    public $tries = 1;

    /**
     * ワーカー側の実行時間上限（秒）。
     * 解析エンジンの上限より短いとエンジンの失敗分類より先にワーカーが落ち、
     * 利用者へ理由を出せなくなるため、コンストラクタで余裕を足して設定する。
     */
    public $timeout;

    /**
     * 目的: 解析対象の解析記録IDを受け取り、ワーカーの実行時間上限を決める。
     * 機能: ジョブが扱う解析記録の識別子を保持し、解析上限へ余裕を足した上限を設定する。
     * 入力: $analysisId は datasheet_analyses の主キー。
     * 出力: インスタンス。
     * 動作条件: 該当する解析記録が作成済みであること。
     * 副作用: なし。
     */
    public function __construct(
        private int $analysisId,
    ) {
        // 解析本体の上限に、PDF変換と結果保存の分の余裕を足す
        $this->timeout = (int) config('datasheet.analysis_timeout') + 120;
    }

    /**
     * 目的: データシート解析を実行し、結果または失敗理由を解析記録へ書く。
     * 機能: 一時PDFを取得し、選択中エンジンで解析し、スペック詳細照合と推薦を通して保存する。
     * 入力: 各依存オブジェクトはコンテナから注入される。
     * 出力: なし。
     * 動作条件: 解析記録が存在し、対象の一時PDFが有効期限内であること。
     * 副作用: 解析記録の状態と結果を更新し、外部プロセスまたは外部APIを呼び出す。
     */
    public function handle(
        DatasheetAnalyzerRegistry $registry,
        TempDatasheetService $tempDatasheets,
        SpecTypeMatchingService $matcher,
        DatasheetRecommendationDecorator $decorator,
    ): void {
        $analysis = DatasheetAnalysis::find($this->analysisId);
        if ($analysis === null) {
            // 解析記録が消えている場合は破棄済みと見なし、何もしない
            Log::info('Datasheet analysis record is gone, skipping', ['analysis_id' => $this->analysisId]);

            return;
        }

        // 破棄済みや実行済みのジョブを取り違えて二重解析しない
        if ($analysis->isFinished()) {
            return;
        }

        $analysis->update([
            'state' => DatasheetAnalysis::STATE_PREPARING,
            'started_at' => now(),
        ]);

        try {
            $meta = $tempDatasheets->getActiveMeta($analysis->temp_token);
            $pdfPath = Storage::disk('local')->path($meta['file_path']);

            $analyzer = $registry->find($analysis->engine) ?? $registry->active();

            // エンジン側の進行段階をそのまま解析記録へ写し、画面に「準備中」と「解析中」を出し分けさせる
            $outcome = $analyzer->analyze($pdfPath, function (string $phase) use ($analysis) {
                $analysis->update([
                    'state' => $phase === 'preparing'
                        ? DatasheetAnalysis::STATE_PREPARING
                        : DatasheetAnalysis::STATE_RUNNING,
                ]);
            });

            // 後段はエンジンによらず同一にする。ここで分岐させると画面側の候補の出方が揃わなくなる
            $result = $outcome->result;
            $result['specs'] = $matcher->match($result['specs'] ?? []);
            $result = $decorator->decorate($result);

            $analysis->update([
                'state' => DatasheetAnalysis::STATE_SUCCEEDED,
                'result' => $result,
                'input_mode' => $outcome->inputMode,
                'input_page_count' => $outcome->inputPageCount,
                'failure_kind' => null,
                'failure_message' => null,
                'finished_at' => now(),
            ]);
        } catch (DatasheetAnalysisException $e) {
            // 失敗種別ごとに画面の案内文と再実行導線が変わるため、種別を保持したまま記録する
            $this->recordFailure($analysis, $e->kind(), $e->getMessage());
        } catch (\RuntimeException $e) {
            // 一時PDFの期限切れや消失はここへ来る。もう一度アップロードさせる必要がある
            $this->recordFailure($analysis, DatasheetAnalysis::FAILURE_UNREADABLE_PDF, $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Datasheet analysis job failed unexpectedly', [
                'analysis_id' => $analysis->id,
                'engine' => $analysis->engine,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $this->recordFailure(
                $analysis,
                DatasheetAnalysis::FAILURE_UNKNOWN,
                'データシート解析中に想定外のエラーが発生しました。時間を置いて再実行してください。'
            );
        }
    }

    /**
     * 目的: ジョブ自体が失敗した場合に解析記録を失敗へ倒す。
     * 機能: 実行時間超過やワーカー停止で handle() を抜けた場合でも、画面が待ち続けないようにする。
     * 入力: $e はジョブ失敗の原因例外。
     * 出力: なし。
     * 動作条件: キューが failed 経路を呼ぶこと。
     * 副作用: 解析記録の状態を更新する。
     */
    public function failed(?\Throwable $e): void
    {
        $analysis = DatasheetAnalysis::find($this->analysisId);
        // handle() 内で失敗を記録できている場合は上書きしない
        if ($analysis === null || $analysis->isFinished()) {
            return;
        }

        $this->recordFailure(
            $analysis,
            DatasheetAnalysis::FAILURE_TIMEOUT,
            '解析が完了しませんでした。もう一度実行してください。'
        );
    }

    /**
     * 目的: 失敗理由を解析記録へ書く。
     * 機能: 状態を失敗へ倒し、失敗種別と利用者向け文面と終了時刻を記録する。
     * 入力: $analysis は対象の解析記録、$kind は失敗種別、$message は利用者向け文面。
     * 出力: なし。
     * 動作条件: $analysis が保存済みであること。
     * 副作用: 解析記録を更新する。
     */
    private function recordFailure(DatasheetAnalysis $analysis, string $kind, string $message): void
    {
        $analysis->update([
            'state' => DatasheetAnalysis::STATE_FAILED,
            'failure_kind' => $kind,
            'failure_message' => $message,
            'finished_at' => now(),
        ]);
    }
}

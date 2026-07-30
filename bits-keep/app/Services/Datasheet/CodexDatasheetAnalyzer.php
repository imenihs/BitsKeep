<?php

namespace App\Services\Datasheet;

use App\Exceptions\DatasheetAnalysisException;
use App\Services\DatasheetPromptService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Codex CLI をサーバ上で実行してデータシートを解析する。
 * PDF は Codex CLI へ直接渡せないため、テキストまたはページ画像へ変換してから渡す。
 */
class CodexDatasheetAnalyzer implements DatasheetAnalyzer
{
    /**
     * 目的: 解析に必要な依存オブジェクトを受け取る。
     * 機能: プロンプト正本、PDF変換、Codex実行、結果正規化を保持する。
     * 入力: 各依存オブジェクト。
     * 出力: インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function __construct(
        private DatasheetPromptService $promptService,
        private PdfContentExtractor $extractor,
        private CodexCliRunner $runner,
        private DatasheetResultNormalizer $normalizer,
    ) {}

    /**
     * 目的: エンジン識別子を返す。
     * 機能: 設定値と解析記録で使う内部キーを返す。
     * 入力: なし。
     * 出力: codex。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function key(): string
    {
        return 'codex';
    }

    /**
     * 目的: 画面へ出す表示名を返す。
     * 機能: 連携設定の選択肢に出す名称を返す。
     * 入力: なし。
     * 出力: 表示名。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function label(): string
    {
        return 'サーバ内解析';
    }

    /**
     * 目的: Codex 実行環境が整っているかを返す。
     * 機能: Codex CLI の前提確認結果をそのまま返す。
     * 入力: なし。
     * 出力: available と message、および内訳を持つ配列。
     * 動作条件: なし。
     * 副作用: ファイルの存在確認を行う。
     *
     * @return array<string, mixed>
     */
    public function checkAvailability(): array
    {
        return $this->runner->checkAvailability();
    }

    /**
     * 目的: データシートPDFを Codex CLI で解析し、正規化済みの結果を返す。
     * 機能: 専用作業ディレクトリを作り、PDFを変換して Codex を実行し、結果を正規化してから作業ディレクトリを削除する。
     * 入力: $pdfPath は対象PDFの絶対パス、$onPhase は進行状態の通知先。
     * 出力: 正規化済み結果と入力方式を持つ DatasheetAnalysisOutcome。
     * 動作条件: $pdfPath が存在し、作業ディレクトリの親が作成可能であること。
     * 副作用: 作業ディレクトリの作成と削除、外部プロセスの実行を行う。
     *
     * @throws DatasheetAnalysisException 解析に失敗した場合
     */
    public function analyze(string $pdfPath, ?callable $onPhase = null): DatasheetAnalysisOutcome
    {
        if (! is_file($pdfPath)) {
            throw DatasheetAnalysisException::unreadablePdf('解析対象のPDFが見つかりませんでした。もう一度アップロードしてください。');
        }

        $workDir = $this->createWorkDir();

        try {
            // PDF変換とエンジン実行は所要時間が大きく違うため、画面へ別の進行状態として見せる
            if ($onPhase !== null) {
                $onPhase('preparing');
            }
            $extraction = $this->extractor->extract($pdfPath, $workDir);

            if ($onPhase !== null) {
                $onPhase('running');
            }
            $raw = $this->runner->run($this->promptService->getPromptText(), $extraction, $workDir);

            return new DatasheetAnalysisOutcome(
                $this->normalizer->normalize($raw),
                $extraction->mode,
                $extraction->pageCount(),
            );
        } finally {
            // 抽出したデータシート本文を作業領域へ残さない。失敗時も必ず削除する
            $this->removeWorkDir($workDir);
        }
    }

    /**
     * 目的: 解析専用の作業ディレクトリを作る。
     * 機能: 設定された親ディレクトリ配下へ、解析ごとに一意なディレクトリを作る。
     * 入力: なし。
     * 出力: 作成した作業ディレクトリの絶対パス。
     * 動作条件: 親ディレクトリが作成可能であること。
     * 副作用: ディレクトリを作成する。
     *
     * @throws DatasheetAnalysisException 作成できない場合
     */
    private function createWorkDir(): string
    {
        $root = rtrim((string) config('datasheet.codex.workspace_root'), '/');
        if ($root === '') {
            throw DatasheetAnalysisException::environment('解析用の作業ディレクトリが設定されていません。');
        }

        // リポジトリ配下を作業根にすると AGENTS.md や CLAUDE.md が読み込まれて解析指示が汚染される
        if (str_starts_with($root, base_path())) {
            throw DatasheetAnalysisException::environment(
                '解析用の作業ディレクトリがアプリ配下に設定されています。リポジトリ外のパスへ変更してください。'
            );
        }

        $workDir = $root.'/'.Str::uuid()->toString();
        if (! mkdir($workDir, 0700, true) && ! is_dir($workDir)) {
            throw DatasheetAnalysisException::environment(
                "解析用の作業ディレクトリ {$root} を作成できませんでした。権限を確認してください。"
            );
        }

        return $workDir;
    }

    /**
     * 目的: 作業ディレクトリを削除する。
     * 機能: 配下のファイルを削除してからディレクトリを削除する。
     * 入力: $workDir は削除対象の絶対パス。
     * 出力: なし。
     * 動作条件: $workDir が createWorkDir() で作ったものであること。
     * 副作用: ファイルとディレクトリを削除する。
     */
    private function removeWorkDir(string $workDir): void
    {
        if (! is_dir($workDir)) {
            return;
        }

        // 作業ディレクトリは1階層しか作らないため、再帰削除は行わずに直下だけを対象にする
        foreach (glob($workDir.'/*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        if (! @rmdir($workDir)) {
            // 消し残しは情報が残り続けるため、後から気付けるようログへ残す
            Log::warning('Datasheet work directory could not be removed', ['path' => $workDir]);
        }
    }
}

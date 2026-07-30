<?php

namespace App\Services\Datasheet;

use App\Exceptions\DatasheetAnalysisException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Codex CLI を非対話モードで実行し、最終応答を JSON として受け取る。
 * 応答本文から JSON を探索して取り出す処理は持たない。出力の形はスキーマで強制し、
 * 形が合わない応答は失敗として扱う。
 */
class CodexCliRunner
{
    /**
     * 目的: 実行に必要な依存オブジェクトを受け取る。
     * 機能: スキーマ提供元を保持する。
     * 入力: $schemaProvider は解析結果のスキーマ提供元。
     * 出力: インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function __construct(
        private DatasheetSchemaProvider $schemaProvider,
    ) {}

    /**
     * 目的: 実行環境が整っているかを確認する。
     * 機能: 実行ファイル、Codexホーム、認証情報の有無を調べる。
     * 入力: なし。
     * 出力: available と message、および画面表示用の詳細を持つ配列。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array{available: bool, message: ?string, binary_found: bool, home_writable: bool, authenticated: bool}
     */
    public function checkAvailability(): array
    {
        $binary = (string) config('datasheet.codex.binary');
        $home = (string) config('datasheet.codex.home');

        $binaryFound = $binary !== '' && is_file($binary) && is_executable($binary);
        $homeWritable = $home !== '' && is_dir($home) && is_writable($home);
        // 認証トークンは自動更新されるため、存在確認だけでなく後段で書き込み可否も見る
        $authenticated = $homeWritable && is_file($home.'/auth.json');

        $message = null;
        if (! $binaryFound) {
            $message = "Codex CLI が見つかりません（{$binary}）。サーバへ導入してください。";
        } elseif (! $homeWritable) {
            $message = "Codex 設定ディレクトリ {$home} が書き込み可能ではありません。認証トークンの自動更新ができないため、キューワーカー実行ユーザの権限を確認してください。";
        } elseif (! $authenticated) {
            $message = 'Codex にログインしていません。サーバ上で codex login --device-auth を実行してください。';
        }

        return [
            'available' => $binaryFound && $homeWritable && $authenticated,
            'message' => $message,
            'binary_found' => $binaryFound,
            'home_writable' => $homeWritable,
            'authenticated' => $authenticated,
        ];
    }

    /**
     * 目的: Codex CLI を実行して解析結果の JSON を得る。
     * 機能: スキーマファイルを書き出し、隔離設定で codex exec を実行し、最終応答を JSON デコードする。
     * 入力: $prompt は解析指示、$extraction は PDF から取り出した入力、$workDir は作業ディレクトリの絶対パス。
     * 出力: デコード済みの解析結果配列。
     * 動作条件: 実行環境が checkAvailability() を満たし、$workDir が書き込み可能であること。
     * 副作用: 外部プロセスを実行し、$workDir 配下へスキーマファイルと応答ファイルを書き出す。
     *
     * @return array<string, mixed>
     *
     * @throws DatasheetAnalysisException 環境不備、未ログイン、時間超過、出力形式不一致、利用枠超過のいずれか
     */
    public function run(string $prompt, PdfExtraction $extraction, string $workDir): array
    {
        $availability = $this->checkAvailability();
        // 実行ファイル不在と権限不足は管理者がサーバ側で対処する。利用者を連携設定へ誘導しても解決しない
        if (! $availability['binary_found'] || ! $availability['home_writable']) {
            throw DatasheetAnalysisException::environment((string) $availability['message']);
        }
        // 未ログインは利用者が連携設定の案内から対処できるため、環境不備とは分けて扱う
        if (! $availability['authenticated']) {
            throw DatasheetAnalysisException::notAuthenticated((string) $availability['message']);
        }

        $schemaPath = $workDir.'/output-schema.json';
        if (file_put_contents($schemaPath, $this->schemaProvider->toJson()) === false) {
            throw DatasheetAnalysisException::environment('解析用のスキーマファイルを書き出せませんでした。作業ディレクトリの権限を確認してください。');
        }

        $lastMessagePath = $workDir.'/last-message.json';
        $timeout = max(30, (int) config('datasheet.analysis_timeout'));

        $process = new Process(
            $this->buildCommand($extraction, $workDir, $schemaPath, $lastMessagePath),
            // 作業ディレクトリはリポジトリ外へ置く。リポジトリを作業根にすると AGENTS.md や
            // CLAUDE.md が読み込まれ、解析プロンプトが汚染される
            $workDir,
            $this->buildEnvironment(),
            // 解析指示は引数ではなく標準入力へ渡す。--image は複数値を取るため、
            // 引数末尾に指示文を置くと指示文が画像パスとして解釈される危険がある
            $this->buildPromptWithInputReference($prompt, $extraction),
            (float) $timeout
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            Log::warning('Codex datasheet analysis timed out', ['timeout' => $timeout]);

            throw DatasheetAnalysisException::timedOut($timeout);
        }

        if (! $process->isSuccessful()) {
            $stderr = $process->getErrorOutput();
            Log::error('Codex datasheet analysis failed', [
                'exit_code' => $process->getExitCode(),
                'stderr' => mb_substr($stderr, 0, 1000),
            ]);

            throw $this->classifyFailure($stderr);
        }

        return $this->decodeLastMessage($lastMessagePath, $process->getOutput());
    }

    /**
     * 目的: codex exec の実行引数を組み立てる。
     * 機能: 隔離設定、スキーマ指定、出力先指定、添付画像を引数へ落とす。
     * 入力: $extraction は PDF から取り出した入力、$workDir は作業ディレクトリ、$schemaPath はスキーマファイル、$lastMessagePath は最終応答の書き出し先。
     * 出力: Process へ渡す引数配列。解析指示は含めず、標準入力側で渡す。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array<int, string>
     */
    private function buildCommand(
        PdfExtraction $extraction,
        string $workDir,
        string $schemaPath,
        string $lastMessagePath,
    ): array {
        $command = [
            (string) config('datasheet.codex.binary'),
            'exec',
            // 作業根を解析専用ディレクトリへ固定し、リポジトリの指示ファイルを読ませない
            '--cd', $workDir,
            // 解析はファイル読み取りだけで足りる。書き込みとコマンド実行は許可しない
            '--sandbox', 'read-only',
            // 利用者ごとの設定やルールを読ませず、解析ごとに同じ条件で動かす
            '--ignore-user-config',
            '--ignore-rules',
            // 作業ディレクトリは git 管理外のため、リポジトリ判定で止まらせない
            '--skip-git-repo-check',
            // 解析履歴をサーバへ残さない
            '--ephemeral',
            // 最終応答の形をスキーマで強制する。応答本文からの JSON 探索を不要にする
            '--output-schema', $schemaPath,
            '--output-last-message', $lastMessagePath,
        ];

        $model = config('datasheet.codex.model');
        if (is_string($model) && $model !== '') {
            $command[] = '--model';
            $command[] = $model;
        }

        // スキャンPDFはページ画像として添付する。テキスト方式では作業ディレクトリ内のテキストを読ませる
        if ($extraction->mode === PdfExtraction::MODE_IMAGE) {
            foreach ($extraction->imagePaths as $imagePath) {
                $command[] = '--image';
                $command[] = $imagePath;
            }
        }

        // 解析指示は位置引数として渡さない。位置引数を置くと --image が指示文まで
        // 画像パスとして取り込む可能性があるため、指示文は標準入力から読ませる
        return $command;
    }

    /**
     * 目的: 解析指示へ入力の在り処を書き添える。
     * 機能: テキスト方式は読むべきファイル名を、画像方式は添付ページの扱いを追記する。
     * 入力: $prompt は解析指示、$extraction は PDF から取り出した入力。
     * 出力: 入力の在り処を含む解析指示。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function buildPromptWithInputReference(string $prompt, PdfExtraction $extraction): string
    {
        if ($extraction->mode === PdfExtraction::MODE_TEXT) {
            $fileName = basename((string) $extraction->textPath);

            // PDF本体は渡せないため、抽出済みテキストを読ませる。作業根直下にあることを明示する
            return "作業ディレクトリ直下の `{$fileName}` は、解析対象データシートPDFから抽出した本文テキストです。"
                ."このファイルを読み取り、以下の指示に従ってください。\n\n"
                .$prompt;
        }

        $pageCount = count($extraction->imagePaths);

        // 画像方式は添付順がページ順であることを明示する。順序が崩れると規格表の対応が壊れる
        return "添付した{$pageCount}枚の画像は、解析対象データシートPDFの先頭から{$pageCount}ページ分を、ページ順に画像化したものです。"
            ."添付画像を読み取り、以下の指示に従ってください。\n\n"
            .$prompt;
    }

    /**
     * 目的: Codex 実行時の環境変数を組み立てる。
     * 機能: 必要な変数だけを明示的に渡し、Webサーバ側の環境を持ち込ませない。
     * 入力: なし。
     * 出力: 環境変数の連想配列。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array<string, string>
     */
    private function buildEnvironment(): array
    {
        $home = (string) config('datasheet.codex.home');

        // 認証情報と設定の在り処を固定する。Webサーバ実行ユーザのホームを参照させない
        return [
            'CODEX_HOME' => $home,
            'HOME' => $home,
            'PATH' => (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'),
            // 解析結果は機械可読な形だけを使うため、装飾を抑える
            'NO_COLOR' => '1',
            'TERM' => 'dumb',
        ];
    }

    /**
     * 目的: 標準エラー出力から失敗種別を判定する。
     * 機能: 認証切れと利用枠超過を判別し、それ以外は不明として扱う。
     * 入力: $stderr は Codex CLI の標準エラー出力。
     * 出力: 失敗種別を持つ例外。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function classifyFailure(string $stderr): DatasheetAnalysisException
    {
        $lower = mb_strtolower($stderr);

        // 認証切れは連携設定からの再ログインで解消する。再実行を促しても無意味なため区別する
        foreach (['unauthorized', '401', 'not logged in', 'login', 'authentication', 'credentials'] as $needle) {
            if (str_contains($lower, $needle)) {
                return DatasheetAnalysisException::notAuthenticated();
            }
        }

        // 利用枠超過は時間を置けば通る。恒久的な失敗と混ぜない
        foreach (['rate limit', 'quota', 'usage limit', '429', 'too many requests'] as $needle) {
            if (str_contains($lower, $needle)) {
                return DatasheetAnalysisException::quotaExceeded();
            }
        }

        return DatasheetAnalysisException::unknown('解析エンジンの実行に失敗しました。時間を置いて再実行してください。');
    }

    /**
     * 目的: 最終応答を JSON としてデコードする。
     * 機能: 応答ファイルを読み、配列へデコードできない場合は形式不一致として扱う。
     * 入力: $lastMessagePath は最終応答の書き出し先、$stdout は標準出力。
     * 出力: デコード済みの配列。
     * 動作条件: スキーマ指定付きで実行済みであること。
     * 副作用: なし。
     *
     * @return array<string, mixed>
     *
     * @throws DatasheetAnalysisException 応答が無い、または JSON として配列にならない場合
     */
    private function decodeLastMessage(string $lastMessagePath, string $stdout): array
    {
        // 応答ファイルを正とし、書き出しに失敗した場合だけ標準出力へ退避する
        $raw = is_file($lastMessagePath) ? (string) file_get_contents($lastMessagePath) : '';
        if (trim($raw) === '') {
            $raw = $stdout;
        }

        $raw = trim($raw);
        if ($raw === '') {
            throw DatasheetAnalysisException::schemaMismatch('解析エンジンから応答が返りませんでした。貼り付け入力をお試しください。');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            Log::warning('Codex datasheet response was not JSON', ['head' => mb_substr($raw, 0, 500)]);

            throw DatasheetAnalysisException::schemaMismatch();
        }

        return $decoded;
    }
}

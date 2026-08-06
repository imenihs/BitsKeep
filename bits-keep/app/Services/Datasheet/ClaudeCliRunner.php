<?php

namespace App\Services\Datasheet;

use App\Exceptions\DatasheetAnalysisException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Claude CLI を非対話モードで実行し、構造化出力として解析結果を受け取る。
 * 応答本文から JSON を探索する処理は持たない。出力の形はスキーマで強制し、
 * 形が合わない応答は失敗として扱う。
 */
class ClaudeCliRunner
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
     * 機能: 実行ファイル、設定ディレクトリ、認証情報の有無を調べる。
     * 入力: なし。
     * 出力: available と message、および画面表示用の詳細を持つ配列。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array{available: bool, message: ?string, binary_found: bool, home_writable: bool, authenticated: bool}
     */
    public function checkAvailability(): array
    {
        $binary = (string) config('datasheet.claude.binary');
        $home = (string) config('datasheet.claude.home');

        $binaryFound = $binary !== '' && is_file($binary) && is_executable($binary);
        // 認証トークンは自動更新されるため、存在確認だけでなく書き込み可否も見る
        $homeWritable = $home !== '' && is_dir($home) && is_writable($home);
        $authenticated = $homeWritable && is_file($home.'/.credentials.json');

        $message = null;
        if (! $binaryFound) {
            $message = "Claude CLI が見つかりません（{$binary}）。サーバへ導入してください。";
        } elseif (! $homeWritable) {
            $message = "Claude 設定ディレクトリ {$home} が書き込み可能ではありません。認証トークンの自動更新ができないため、キューワーカー実行ユーザの権限を確認してください。";
        } elseif (! $authenticated) {
            // 具体的なログイン手順は CLI の版で変わるため画面には出さない。
            // 利用者が自力で対処できる作業ではないので、管理者への依頼を促す
            $message = 'サーバ内解析の利用登録が切れています。復旧はサーバ管理者の作業が必要です。管理者へ連絡してください。';
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
     * 目的: Claude CLI を実行して解析結果の JSON を得る。
     * 機能: 解析指示を標準入力から渡し、構造化出力としてスキーマ準拠の結果を受け取る。
     * 入力: $prompt は解析指示、$extraction は PDF から取り出した入力、$workDir は作業ディレクトリの絶対パス。
     * 出力: デコード済みの解析結果配列。
     * 動作条件: 実行環境が checkAvailability() を満たし、$workDir が書き込み可能であること。
     * 副作用: 外部プロセスを実行する。
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
        // 未ログインは環境不備とは分けて扱う。復旧の担当が異なる
        if (! $availability['authenticated']) {
            throw DatasheetAnalysisException::notAuthenticated((string) $availability['message']);
        }

        $timeout = max(30, (int) config('datasheet.analysis_timeout'));

        $process = new Process(
            $this->buildCommand($extraction, $workDir),
            // 作業ディレクトリはリポジトリ外へ置く。リポジトリを作業根にすると
            // CLAUDE.md などの指示ファイルが読み込まれ、解析指示が汚染される
            $workDir,
            $this->buildEnvironment(),
            // 解析指示は引数ではなく標準入力へ渡す。指示文が長く、
            // 引数長の上限やシェル解釈の影響を受けないようにする
            $this->buildPromptWithInputReference($prompt, $extraction),
            (float) $timeout
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            Log::warning('Claude datasheet analysis timed out', ['timeout' => $timeout]);

            throw DatasheetAnalysisException::timedOut($timeout);
        }

        if (! $process->isSuccessful()) {
            $stderr = $process->getErrorOutput();
            Log::error('Claude datasheet analysis failed', [
                'exit_code' => $process->getExitCode(),
                'stderr' => mb_substr($stderr, 0, 1000),
            ]);

            throw $this->classifyFailure($stderr);
        }

        return $this->decodeResult($process->getOutput());
    }

    /**
     * 目的: claude の実行引数を組み立てる。
     * 機能: 非対話実行、スキーマ指定、作業ディレクトリの限定、利用者設定の無効化を引数へ落とす。
     * 入力: $extraction は PDF から取り出した入力、$workDir は作業ディレクトリ。
     * 出力: Process へ渡す引数配列。解析指示は含めず、標準入力側で渡す。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array<int, string>
     */
    private function buildCommand(PdfExtraction $extraction, string $workDir): array
    {
        $command = [
            (string) config('datasheet.claude.binary'),
            // 非対話で1回だけ実行し、結果を出力して終了する
            '-p',
            // 解析結果を機械可読な形で受け取る。structured_output からスキーマ準拠の値を読む
            '--output-format', 'json',
            // 最終応答の形をスキーマで強制する。応答本文からの JSON 探索を不要にする
            '--json-schema', $this->schemaProvider->toJson(),
            // 解析対象だけを見せる。他のディレクトリは参照させない
            '--add-dir', $workDir,
            // 利用者ごとの設定やルールを読ませず、解析ごとに同じ条件で動かす
            '--setting-sources', '',
            // 解析はファイル読み取りだけで足りる。書き込みと実行は許可しない
            '--disallowedTools', 'Bash,Edit,Write,NotebookEdit,WebFetch,WebSearch',
            // 解析中に許可を求めて止まらせない。読み取り専用の範囲で完結させる
            '--permission-mode', 'dontAsk',
        ];

        $model = config('datasheet.claude.model');
        if (is_string($model) && $model !== '') {
            $command[] = '--model';
            $command[] = $model;
        }

        return $command;
    }

    /**
     * 目的: 解析指示へ入力の在り処を書き添える。
     * 機能: テキスト方式は読むべきファイル名を、画像方式はページ画像の在り処を追記する。
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

        // 画像方式はページ順が崩れると規格表の対応が壊れるため、ファイル名の順序であることを明示する
        $fileNames = array_map( fn ($path) => basename((string) $path), $extraction->imagePaths);
        $pageCount = count($fileNames);
        $list = implode(', ', array_map( fn ($name) => "`{$name}`", $fileNames));

        return "作業ディレクトリ直下の次の{$pageCount}枚の画像は、解析対象データシートPDFの先頭から{$pageCount}ページ分を、"
            ."ファイル名の順にページ順で画像化したものです: {$list}\n"
            ."これらの画像をすべて読み取り、以下の指示に従ってください。\n\n"
            .$prompt;
    }

    /**
     * 目的: Claude 実行時の環境変数を組み立てる。
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
        $home = (string) config('datasheet.claude.home');

        // 認証情報と設定の在り処を固定する。Webサーバ実行ユーザのホームを参照させない
        return [
            'CLAUDE_CONFIG_DIR' => $home,
            'HOME' => $home,
            'PATH' => (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'),
            // 解析結果は機械可読な形だけを使うため、装飾を抑える
            'NO_COLOR' => '1',
            'TERM' => 'dumb',
            // 対話的な確認や外部連携を持ち込ませない
            'CI' => '1',
        ];
    }

    /**
     * 目的: 標準エラー出力から失敗種別を判定する。
     * 機能: 認証切れと利用枠超過を判別し、それ以外は不明として扱う。
     * 入力: $stderr は Claude CLI の標準エラー出力。
     * 出力: 失敗種別を持つ例外。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function classifyFailure(string $stderr): DatasheetAnalysisException
    {
        $lower = mb_strtolower($stderr);

        // 認証切れは再ログインで解消する。再実行を促しても無意味なため区別する
        foreach (['not logged in', 'unauthorized', '401', 'login', 'authentication', 'credentials'] as $needle) {
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
     * 目的: 実行結果から解析内容を取り出す。
     * 機能: 実行結果をデコードし、構造化出力の中身を解析結果として返す。
     * 入力: $stdout は claude の標準出力。
     * 出力: デコード済みの解析結果配列。
     * 動作条件: スキーマ指定付きで実行済みであること。
     * 副作用: なし。
     *
     * @return array<string, mixed>
     *
     * @throws DatasheetAnalysisException 応答が無い、失敗応答である、または構造化出力を得られない場合
     */
    private function decodeResult(string $stdout): array
    {
        $raw = trim($stdout);
        if ($raw === '') {
            throw DatasheetAnalysisException::schemaMismatch('解析エンジンから応答が返りませんでした。貼り付け入力をお試しください。');
        }

        $envelope = json_decode($raw, true);
        if (! is_array($envelope)) {
            Log::warning('Claude datasheet response was not JSON', ['head' => mb_substr($raw, 0, 500)]);

            throw DatasheetAnalysisException::schemaMismatch();
        }

        // CLI は失敗時も終了コード 0 で結果を返すことがある。
        // 応答内の is_error を見ないと、失敗を成功として扱ってしまう
        if (($envelope['is_error'] ?? false) === true) {
            $reason = (string) ($envelope['result'] ?? '');
            Log::error('Claude datasheet analysis returned an error result', ['reason' => mb_substr($reason, 0, 500)]);

            throw $this->classifyFailure($reason);
        }

        // スキーマ指定時はここへ結果が入る。本文側は説明文であり解析結果ではない
        $structured = $envelope['structured_output'] ?? null;
        if (! is_array($structured)) {
            Log::warning('Claude datasheet response had no structured output', [
                'head' => mb_substr((string) ($envelope['result'] ?? ''), 0, 500),
            ]);

            throw DatasheetAnalysisException::schemaMismatch();
        }

        return $structured;
    }
}

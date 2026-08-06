<?php

namespace App\Services\Datasheet;

use App\Exceptions\DatasheetAnalysisException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * PDF から解析エンジンへ渡せる入力を取り出す。
 * 解析エンジンは PDF を直接受け取れないため、テキスト層があるPDFは文字として、
 * テキスト層のないスキャンPDFはページ画像として渡せる形へ変換する。
 */
class PdfContentExtractor
{
    /**
     * 目的: PDFから解析入力を取り出す。
     * 機能: まずテキスト層を取り出し、文字数が閾値未満ならページ画像へ切り替える。
     * 入力: $pdfPath は対象PDFの絶対パス、$workDir は生成物を置く作業ディレクトリの絶対パス。
     * 出力: 取り出した入力を表す PdfExtraction。
     * 動作条件: $pdfPath が存在し、$workDir が書き込み可能であること。
     * 副作用: $workDir 配下へテキストファイルまたはページ画像を書き出す。
     *
     * @throws DatasheetAnalysisException 変換コマンドが無い場合、またはテキストも画像も取れない場合
     */
    public function extract(string $pdfPath, string $workDir): PdfExtraction
    {
        $text = $this->extractText($pdfPath);
        $threshold = (int) config('datasheet.pdf.text_threshold');

        // テキスト層が十分にあるPDFは、画像化せず文字として渡す。トークン消費も所要時間も小さい
        if (mb_strlen($text) >= $threshold) {
            $maxLength = (int) config('datasheet.pdf.max_text_length');
            $trimmed = $this->trimText($text, $maxLength);

            $textPath = $workDir.'/datasheet.txt';
            if (file_put_contents($textPath, $trimmed) === false) {
                throw DatasheetAnalysisException::environment('解析用の作業ファイルを書き出せませんでした。作業ディレクトリの権限を確認してください。');
            }

            return PdfExtraction::text($textPath, mb_strlen($trimmed));
        }

        // テキスト層が薄いPDFはスキャン原稿と判断し、ページ画像へ変換して渡す
        $imagePaths = $this->extractPageImages($pdfPath, $workDir);
        if ($imagePaths === []) {
            throw DatasheetAnalysisException::unreadablePdf();
        }

        return PdfExtraction::images($imagePaths);
    }

    /**
     * 目的: PDFのテキスト層を取り出す。
     * 機能: pdftotext を表組み維持モードで実行し、標準出力を受け取る。
     * 入力: $pdfPath は対象PDFの絶対パス。
     * 出力: 取り出したテキスト。テキスト層が無い場合は空文字。
     * 動作条件: pdftotext が実行可能であること。
     * 副作用: 外部コマンドを実行する。
     *
     * @throws DatasheetAnalysisException pdftotext が存在しない場合
     */
    private function extractText(string $pdfPath): string
    {
        $binary = (string) config('datasheet.pdf.pdftotext_binary');
        $this->assertBinaryExists($binary, 'pdftotext', 'poppler-utils');

        // -layout で列組みを保つ。規格表の行と値の対応が崩れると読み取り精度が落ちる
        $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $pdfPath, '-']);
        $process->setTimeout((float) config('datasheet.pdf.convert_timeout'));

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw DatasheetAnalysisException::timedOut((int) config('datasheet.pdf.convert_timeout'));
        }

        // 失敗しても即座に落とさない。この後のページ画像化で救える可能性がある
        if (! $process->isSuccessful()) {
            Log::warning('Datasheet pdftotext failed', [
                'exit_code' => $process->getExitCode(),
                'stderr' => mb_substr($process->getErrorOutput(), 0, 500),
            ]);

            return '';
        }

        return trim($process->getOutput());
    }

    /**
     * 目的: PDFをページ画像へ変換する。
     * 機能: pdftoppm で先頭から上限ページ数まで PNG へ変換する。
     * 入力: $pdfPath は対象PDFの絶対パス、$workDir は画像を置く作業ディレクトリ。
     * 出力: 生成した画像の絶対パス配列。ページ順に並ぶ。
     * 動作条件: pdftoppm が実行可能で、$workDir が書き込み可能であること。
     * 副作用: $workDir 配下へ PNG を書き出す。
     *
     * @return array<int, string>
     *
     * @throws DatasheetAnalysisException pdftoppm が存在しない場合、または変換が時間超過した場合
     */
    private function extractPageImages(string $pdfPath, string $workDir): array
    {
        $binary = (string) config('datasheet.pdf.pdftoppm_binary');
        $this->assertBinaryExists($binary, 'pdftoppm', 'poppler-utils');

        $maxPages = max(1, (int) config('datasheet.pdf.max_image_pages'));
        $dpi = max(72, (int) config('datasheet.pdf.image_dpi'));
        $prefix = $workDir.'/page';

        // 全ページ画像化は利用枠を大きく消費するため、先頭から上限ページ数だけに絞る
        $process = new Process([
            $binary,
            '-png',
            '-r', (string) $dpi,
            '-f', '1',
            '-l', (string) $maxPages,
            $pdfPath,
            $prefix,
        ]);
        $process->setTimeout((float) config('datasheet.pdf.convert_timeout'));

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw DatasheetAnalysisException::timedOut((int) config('datasheet.pdf.convert_timeout'));
        }

        if (! $process->isSuccessful()) {
            Log::warning('Datasheet pdftoppm failed', [
                'exit_code' => $process->getExitCode(),
                'stderr' => mb_substr($process->getErrorOutput(), 0, 500),
            ]);

            return [];
        }

        $paths = glob($workDir.'/page*.png') ?: [];
        // pdftoppm はページ番号を連番で付ける。ページ順を保つため名前順へ揃える
        sort($paths, SORT_NATURAL);

        return array_values($paths);
    }

    /**
     * 目的: 解析へ渡すテキストを上限文字数へ収める。
     * 機能: 上限を超える場合は先頭側を残して切り、切ったことを本文へ明示する。
     * 入力: $text は取り出したテキスト、$maxLength は上限文字数。
     * 出力: 上限内へ収めたテキスト。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function trimText(string $text, int $maxLength): string
    {
        if ($maxLength <= 0 || mb_strlen($text) <= $maxLength) {
            return $text;
        }

        // データシートは巻末に長い注記や改訂履歴が続く。仕様値は前半に集まるため先頭側を残す
        return mb_substr($text, 0, $maxLength)."\n\n[この先は文字数上限のため省略]";
    }

    /**
     * 目的: 必要な変換コマンドの存在を確認する。
     * 機能: 実行可能ファイルとして存在しない場合は環境不備として失敗させる。
     * 入力: $binary は設定されたパス、$name はコマンド名、$package は導入すべきパッケージ名。
     * 出力: なし。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @throws DatasheetAnalysisException コマンドが存在しない場合
     */
    private function assertBinaryExists(string $binary, string $name, string $package): void
    {
        if ($binary === '' || ! is_file($binary) || ! is_executable($binary)) {
            throw DatasheetAnalysisException::environment(
                "PDF変換コマンド {$name} が見つかりません。サーバへ {$package} を導入してください。"
            );
        }
    }
}

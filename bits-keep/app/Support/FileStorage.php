<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ファイル管理ヘルパー
 * 画像・データシートは storage/app/public/ 配下に種別ごとに保存。
 * DBにはパス文字列（例: components/images/abc123.jpg）のみ保存する。
 */
class FileStorage
{
    // 許可する画像 MIME タイプ
    const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // 許可する PDF MIME タイプ
    const PDF_MIMES = ['application/pdf'];

    /**
     * 目的: File Storageのstore部品imageを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $file。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function storeComponentImage(UploadedFile $file): string
    {
        self::validateMime($file, self::IMAGE_MIMES);
        $name = self::nextAvailableName('components/images', 'component', $file->getClientOriginalExtension());

        return self::storeVerified($file, 'components/images', $name);
    }

    /**
     * 目的: File Storageのstoreデータシートを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $file。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function storeDatasheet(UploadedFile $file): string
    {
        self::validateMime($file, self::PDF_MIMES);
        $name = self::nextAvailableName('components/datasheets', 'datasheet', 'pdf');

        return self::storeVerified($file, 'components/datasheets', $name);
    }

    /**
     * 目的: File Storageのstore部品imagenamedを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $file, $parts。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function storeComponentImageNamed(UploadedFile $file, array $parts): string
    {
        self::validateMime($file, self::IMAGE_MIMES);
        $name = self::nextAvailableName('components/images', self::buildStem($parts, 'component'), $file->getClientOriginalExtension());

        return self::storeVerified($file, 'components/images', $name);
    }

    /**
     * 目的: File Storageのstore部品データシートnamedを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $file, $parts。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function storeComponentDatasheetNamed(UploadedFile $file, array $parts): string
    {
        self::validateMime($file, self::PDF_MIMES);
        $name = self::nextAvailableName('components/datasheets', self::buildStem($parts, 'datasheet'), 'pdf');

        return self::storeVerified($file, 'components/datasheets', $name);
    }

    /**
     * 目的: File Storageのstore部品データシートfrompathnamedを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $absolutePath, $parts。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function storeComponentDatasheetFromPathNamed(string $absolutePath, array $parts): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('保存元の一時PDFが見つかりません。');
        }

        $name = self::nextAvailableName('components/datasheets', self::buildStem($parts, 'datasheet'), 'pdf');
        $targetPath = trim('components/datasheets/'.$name, '/');
        $stream = fopen($absolutePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('一時PDFの読み込みに失敗しました。');
        }

        try {
            $stored = Storage::disk('public')->put($targetPath, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored || ! Storage::disk('public')->exists($targetPath)) {
            throw new RuntimeException('データシート保存に失敗しました。保存先の権限を確認してください。');
        }

        return $targetPath;
    }

    /**
     * 目的: File Storageのstoreパッケージimageを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $file。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function storePackageImage(UploadedFile $file): string
    {
        self::validateMime($file, self::IMAGE_MIMES);
        $name = Str::uuid().'.'.$file->getClientOriginalExtension();

        return self::storeVerified($file, 'packages/images', $name);
    }

    /**
     * 目的: File Storageのdeleteを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $path。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * 目的: File Storageのurlを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $path。
     * 出力: ?stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return url('/files/public/'.ltrim($path, '/'));
    }

    /**
     * 目的: File Storageのvalidatemimeを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $file, $allowed。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    protected static function validateMime(UploadedFile $file, array $allowed): void
    {
        if (! in_array($file->getMimeType(), $allowed, true)) {
            throw new \InvalidArgumentException(
                '許可されていないファイル形式です: '.$file->getMimeType()
            );
        }
    }

    /**
     * 目的: File Storageのvalidatepdfuploadを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $file。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function validatePdfUpload(UploadedFile $file): void
    {
        self::validateMime($file, self::PDF_MIMES);
    }

    /**
     * 目的: File Storageの生成stemを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $parts, $fallback。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     */
    protected static function buildStem(array $parts, string $fallback): string
    {
        $stem = collect($parts)
            ->map( fn ($part) => trim((string) $part))
            ->filter()
            ->map( fn ($part) => Str::of($part)
                ->ascii()
                ->replaceMatches('/[^A-Za-z0-9_\-]+/', '_')
                ->trim('_')
                ->value()
            )
            ->filter()  // ASCII変換で空になった部分（日本語など）を除外
            ->join('_');

        $stem = Str::limit($stem ?: $fallback, 80, '');
        $stem = trim($stem, '_');

        return $stem !== '' ? $stem : $fallback;
    }

    /**
     * 目的: File Storageのnextavailable名称を担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $directory, $stem, $extension。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    protected static function nextAvailableName(string $directory, string $stem, string $extension): string
    {
        $disk = Storage::disk('public');
        $base = trim($stem, '_');
        $candidate = "{$base}.{$extension}";

        if (! $disk->exists("{$directory}/{$candidate}")) {
            return $candidate;
        }

        for ($i = 1; $i <= 99; $i++) {
            $candidate = sprintf('%s_%02d.%s', $base, $i, $extension);
            if (! $disk->exists("{$directory}/{$candidate}")) {
                return $candidate;
            }
        }

        return sprintf('%s_%s.%s', $base, Str::lower(Str::random(6)), $extension);
    }

    /**
     * 目的: File Storageのstoreverifiedを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $file, $directory, $name。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    protected static function storeVerified(UploadedFile $file, string $directory, string $name): string
    {
        $storedPath = $file->storeAs($directory, $name, 'public');
        $expectedPath = trim($directory.'/'.$name, '/');

        if ($storedPath === false || ! Storage::disk('public')->exists($expectedPath)) {
            throw new RuntimeException('ファイル保存に失敗しました。保存先の権限を確認してください。');
        }

        return $expectedPath;
    }
}

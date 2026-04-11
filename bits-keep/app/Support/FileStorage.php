<?php

namespace App\Support;

use RuntimeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    const PDF_MIMES   = ['application/pdf'];

    /**
     * 部品画像を保存して保存パスを返す
     */
    public static function storeComponentImage(UploadedFile $file): string
    {
        self::validateMime($file, self::IMAGE_MIMES);
        $name = self::nextAvailableName('components/images', 'component', $file->getClientOriginalExtension());
        return self::storeVerified($file, 'components/images', $name);
    }

    /**
     * データシート PDF を保存して保存パスを返す
     */
    public static function storeDatasheet(UploadedFile $file): string
    {
        self::validateMime($file, self::PDF_MIMES);
        $name = self::nextAvailableName('components/datasheets', 'datasheet', 'pdf');
        return self::storeVerified($file, 'components/datasheets', $name);
    }

    public static function storeComponentImageNamed(UploadedFile $file, array $parts): string
    {
        self::validateMime($file, self::IMAGE_MIMES);
        $name = self::nextAvailableName('components/images', self::buildStem($parts, 'component'), $file->getClientOriginalExtension());
        return self::storeVerified($file, 'components/images', $name);
    }

    public static function storeComponentDatasheetNamed(UploadedFile $file, array $parts): string
    {
        self::validateMime($file, self::PDF_MIMES);
        $name = self::nextAvailableName('components/datasheets', self::buildStem($parts, 'datasheet'), 'pdf');
        return self::storeVerified($file, 'components/datasheets', $name);
    }

    /**
     * パッケージ画像保存
     */
    public static function storePackageImage(UploadedFile $file): string
    {
        self::validateMime($file, self::IMAGE_MIMES);
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        return self::storeVerified($file, 'packages/images', $name);
    }

    /**
     * ファイル削除（パスがnullでも安全）
     */
    public static function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * 公開URLを取得
     */
    public static function url(?string $path): ?string
    {
        if (!$path) return null;
        return url('/files/public/' . ltrim($path, '/'));
    }

    /**
     * MIME タイプ検証（許可外はバリデーション例外）
     */
    protected static function validateMime(UploadedFile $file, array $allowed): void
    {
        if (!in_array($file->getMimeType(), $allowed, true)) {
            throw new \InvalidArgumentException(
                '許可されていないファイル形式です: ' . $file->getMimeType()
            );
        }
    }

    protected static function buildStem(array $parts, string $fallback): string
    {
        $stem = collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->map(fn ($part) => Str::of($part)
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

    protected static function nextAvailableName(string $directory, string $stem, string $extension): string
    {
        $disk = Storage::disk('public');
        $base = trim($stem, '_');
        $candidate = "{$base}.{$extension}";

        if (!$disk->exists("{$directory}/{$candidate}")) {
            return $candidate;
        }

        for ($i = 1; $i <= 99; $i++) {
            $candidate = sprintf('%s_%02d.%s', $base, $i, $extension);
            if (!$disk->exists("{$directory}/{$candidate}")) {
                return $candidate;
            }
        }

        return sprintf('%s_%s.%s', $base, Str::lower(Str::random(6)), $extension);
    }

    protected static function storeVerified(UploadedFile $file, string $directory, string $name): string
    {
        $storedPath = $file->storeAs($directory, $name, 'public');
        $expectedPath = trim($directory . '/' . $name, '/');

        if ($storedPath === false || !Storage::disk('public')->exists($expectedPath)) {
            throw new RuntimeException('ファイル保存に失敗しました。保存先の権限を確認してください。');
        }

        return $expectedPath;
    }
}

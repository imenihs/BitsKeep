<?php

namespace App\Support;

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
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('components/images', $name, 'public');
        return 'components/images/' . $name;
    }

    /**
     * データシート PDF を保存して保存パスを返す
     */
    public static function storeDatasheet(UploadedFile $file): string
    {
        self::validateMime($file, self::PDF_MIMES);
        $name = Str::uuid() . '.pdf';
        $file->storeAs('components/datasheets', $name, 'public');
        return 'components/datasheets/' . $name;
    }

    /**
     * パッケージ画像保存
     */
    public static function storePackageImage(UploadedFile $file): string
    {
        self::validateMime($file, self::IMAGE_MIMES);
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('packages/images', $name, 'public');
        return 'packages/images/' . $name;
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
        return Storage::disk('public')->url($path);
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
}

<?php

namespace App\Services;

use App\Support\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TempDatasheetService
{
    private const DIRECTORY = 'component-helper-temp';

    private const TTL_HOURS = 2;
    /**
     * 目的: Temp Datasheetのcreatemanyを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $files, $displayNames。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function createMany(array $files, array $displayNames = []): array
    {
        $this->purgeExpired();

        $created = [];
        foreach (array_values($files) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            FileStorage::validatePdfUpload($file);

            $token = (string) Str::uuid();
            $storedPath = $file->storeAs(self::DIRECTORY, "{$token}.pdf", 'local');
            if ($storedPath === false || ! Storage::disk('local')->exists($storedPath)) {
                throw new RuntimeException('一時PDFの保存に失敗しました。保存先の権限を確認してください。');
            }

            $absolutePath = Storage::disk('local')->path($storedPath);
            $sha256 = hash_file('sha256', $absolutePath) ?: '';
            $expiresAt = now()->addHours(self::TTL_HOURS);

            $meta = [
                'token' => $token,
                'file_path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'display_name' => $this->normalizeDisplayName($displayNames[$index] ?? null),
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'size' => $file->getSize() ?: 0,
                'sha256' => $sha256,
                'created_at' => now()->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
            ];

            $this->writeMeta($token, $meta);

            $created[] = [
                'token' => $token,
                'original_name' => $meta['original_name'],
                'display_name' => $meta['display_name'],
                'sha256' => $sha256,
                'expires_at' => $meta['expires_at'],
            ];
        }

        return $created;
    }
    /**
     * 目的: Temp Datasheetのgetactivemetaを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $token。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function getActiveMeta(string $token): array
    {
        $this->purgeExpired();

        $meta = $this->readMeta($token);
        if (! $meta) {
            throw new RuntimeException('対象の一時PDFが見つかりません。もう一度解析を開始してください。');
        }

        if ($this->isExpired($meta)) {
            $this->deleteToken($token);
            throw new RuntimeException('一時PDFの有効期限が切れました。もう一度解析を開始してください。');
        }

        if (! Storage::disk('local')->exists($meta['file_path'] ?? '')) {
            $this->deleteToken($token);
            throw new RuntimeException('一時PDFが失われました。もう一度解析を開始してください。');
        }

        return $meta;
    }
    /**
     * 目的: Temp Datasheetのclaimmanyを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $tokens, $displayNames, $parts。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function claimMany(array $tokens, array $displayNames = [], array $parts = []): array
    {
        $this->purgeExpired();

        $claimed = [];
        foreach (array_values(array_unique(array_filter(array_map('strval', $tokens)))) as $index => $token) {
            $meta = $this->getActiveMeta($token);
            $absolutePath = Storage::disk('local')->path($meta['file_path']);

            $storedPath = FileStorage::storeComponentDatasheetFromPathNamed($absolutePath, $parts);
            $claimed[] = [
                'token' => $token,
                'file_path' => $storedPath,
                'original_name' => $meta['original_name'] ?? basename($storedPath),
                'display_name' => $this->normalizeDisplayName($displayNames[$index] ?? null) ?? ($meta['display_name'] ?? null),
            ];

            $this->deleteToken($token);
        }

        return $claimed;
    }
    /**
     * 目的: Temp Datasheetのdeletetokenを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $token。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function deleteToken(string $token): bool
    {
        $deleted = false;
        $meta = $this->readMeta($token);
        if ($meta && ! empty($meta['file_path']) && Storage::disk('local')->exists($meta['file_path'])) {
            Storage::disk('local')->delete($meta['file_path']);
            $deleted = true;
        }

        $metaPath = $this->metaPath($token);
        if (Storage::disk('local')->exists($metaPath)) {
            Storage::disk('local')->delete($metaPath);
            $deleted = true;
        }

        return $deleted;
    }
    /**
     * 目的: Temp Datasheetのpurgeexpiredを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: なし。
     * 出力: intで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function purgeExpired(): int
    {
        $purged = 0;
        foreach (Storage::disk('local')->files(self::DIRECTORY) as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }

            $token = pathinfo($path, PATHINFO_FILENAME);
            $meta = $this->readMeta($token);
            if (! $meta || $this->isExpired($meta)) {
                if ($this->deleteToken($token)) {
                    $purged++;
                }
            }
        }

        return $purged;
    }
    /**
     * 目的: Temp Datasheetのreadmetaを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $token。
     * 出力: ?arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    private function readMeta(string $token): ?array
    {
        $path = $this->metaPath($token);
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $raw = Storage::disk('local')->get($path);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
    /**
     * 目的: Temp Datasheetのwritemetaを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $token, $meta。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    private function writeMeta(string $token, array $meta): void
    {
        $written = Storage::disk('local')->put($this->metaPath($token), json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if (! $written) {
            $this->deleteToken($token);
            throw new RuntimeException('一時PDFメタデータの保存に失敗しました。');
        }
    }
    /**
     * 目的: Temp Datasheetのmetapathを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $token。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    private function metaPath(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.json';
    }
    /**
     * 目的: Temp Datasheetのisexpiredを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $meta。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    private function isExpired(array $meta): bool
    {
        $expiresAt = Carbon::parse($meta['expires_at'] ?? now()->subSecond()->toIso8601String());

        return $expiresAt->isPast();
    }
    /**
     * 目的: Temp Datasheetの正規化表示名称を担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $value。
     * 出力: ?stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     */
    private function normalizeDisplayName(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}

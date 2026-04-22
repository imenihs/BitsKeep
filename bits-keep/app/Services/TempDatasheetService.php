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

    private function writeMeta(string $token, array $meta): void
    {
        $written = Storage::disk('local')->put($this->metaPath($token), json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if (! $written) {
            $this->deleteToken($token);
            throw new RuntimeException('一時PDFメタデータの保存に失敗しました。');
        }
    }

    private function metaPath(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.json';
    }

    private function isExpired(array $meta): bool
    {
        $expiresAt = Carbon::parse($meta['expires_at'] ?? now()->subSecond()->toIso8601String());

        return $expiresAt->isPast();
    }

    private function normalizeDisplayName(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}

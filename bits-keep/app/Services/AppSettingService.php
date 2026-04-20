<?php

namespace App\Services;

use App\Models\AppSetting;

class AppSettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return AppSetting::where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public function delete(string $key): void
    {
        AppSetting::where('key', $key)->delete();
    }

    public function getNotionConfig(): array
    {
        $token = $this->get('notion.api_token', config('services.notion.token'));
        $rootPageUrl = $this->get('notion.root_page_url', config('services.notion.root_page_url'));
        $rootPageId = $this->get('notion.root_page_id', config('services.notion.root_page_id'));

        if (empty($rootPageId) && ! empty($rootPageUrl)) {
            $rootPageId = $this->parseNotionPageId($rootPageUrl);
        }

        $tokenConfigured = ! empty($token);
        $rootPageConfigured = ! empty($rootPageId);

        return [
            'configured' => $tokenConfigured,
            'token' => $token,
            'token_preview' => $this->maskSecret($token),
            'root_page_url' => $rootPageUrl,
            'root_page_id' => $rootPageId,
            'token_configured' => $tokenConfigured,
            'root_page_configured' => $rootPageConfigured,
            'discovery_mode' => $rootPageConfigured ? 'root-page' : 'workspace-search',
            'missing' => array_values(array_filter([
                $tokenConfigured ? null : 'NOTION_API_TOKEN',
            ])),
        ];
    }

    public function updateNotionConfig(?string $token, ?string $rootPageUrl, bool $clearToken = false, bool $clearRootPageUrl = false): array
    {
        $token = $token !== null ? trim($token) : null;
        $rootPageUrl = $rootPageUrl !== null ? trim($rootPageUrl) : null;

        if ($clearToken) {
            $this->delete('notion.api_token');
        } elseif ($token !== null && $token !== '') {
            $this->set('notion.api_token', $token);
        }

        // null/空文字は「既存維持」。削除は明示操作のみ
        if ($clearRootPageUrl) {
            $this->delete('notion.root_page_url');
            $this->delete('notion.root_page_id');
        } elseif ($rootPageUrl !== null && $rootPageUrl !== '') {
            $rootPageId = $this->parseNotionPageId($rootPageUrl);
            if (! $rootPageId) {
                throw new \InvalidArgumentException('NotionのページURLからページIDを抽出できませんでした。ページURLを確認してください。');
            }
            $this->set('notion.root_page_url', $rootPageUrl);
            $this->set('notion.root_page_id', $rootPageId);
        }

        return $this->getNotionConfig();
    }

    public function parseNotionPageId(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (preg_match('/([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/', $value, $matches)) {
            return strtolower($matches[1]);
        }

        if (preg_match('/([0-9a-fA-F]{32})/', $value, $matches)) {
            $hex = strtolower($matches[1]);

            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12)
            );
        }

        return null;
    }

    public function getGeminiConfig(): array
    {
        $key = $this->get('gemini.api_key', env('GEMINI_API_KEY'));
        $configured = ! empty($key);

        return [
            'configured'   => $configured,
            'key_preview'  => $this->maskSecret($key),
        ];
    }

    public function updateGeminiConfig(?string $apiKey, bool $clearKey = false): array
    {
        if ($clearKey) {
            $this->delete('gemini.api_key');
        } elseif ($apiKey !== null && $apiKey !== '') {
            $this->set('gemini.api_key', trim($apiKey));
        }

        return $this->getGeminiConfig();
    }

    private function maskSecret(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $len = mb_strlen($value);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return mb_substr($value, 0, 4).str_repeat('•', max($len - 8, 4)).mb_substr($value, -4);
    }
}

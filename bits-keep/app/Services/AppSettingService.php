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

    public function updateNotionConfig(?string $token, ?string $rootPageUrl): array
    {
        $token = $token !== null ? trim($token) : null;
        $rootPageUrl = $rootPageUrl !== null ? trim($rootPageUrl) : null;

        if ($token === '') {
            $this->delete('notion.api_token');
        } else {
            $this->set('notion.api_token', $token);
        }

        // null と空文字どちらも「未設定」として扱う
        if ($rootPageUrl === null || $rootPageUrl === '') {
            $this->delete('notion.root_page_url');
            $this->delete('notion.root_page_id');
        } else {
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
}

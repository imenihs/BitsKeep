<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSyncRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notion案件同期サービス（FNC-024）
 *
 * 対象: DB名が「01_案件管理」かつ親ページ名が「010_〜099_」で始まる事業配下のみ。
 * ルートページURLがあればその配下を探索し、未設定ならアクセス可能なNotion全体から検索する。
 *
 * NOTION_API_TOKEN / NOTION_ROOT_PAGE_ID が .env に設定されている必要がある。
 */
class NotionSyncService
{
    private const NOTION_API_BASE = 'https://api.notion.com/v1';
    private const NOTION_VERSION  = '2022-06-28';

    // 事業ページ番号の正規表現（010〜099）
    private const BUSINESS_CODE_PATTERN = '/^(0[1-9][0-9])_(.+)$/u';

    public function isConfigured(): bool
    {
        return app(AppSettingService::class)->getNotionConfig()['configured'];
    }

    /**
     * Notion事業ページを自動発見し、01_案件管理 DBを同期する。
     * 同期結果を ProjectSyncRun として保存して返す。
     */
    public function discoverAndSync(int $triggeredBy): ProjectSyncRun
    {
        $run = ProjectSyncRun::create([
            'triggered_by' => $triggeredBy,
            'status'       => 'running',
            'started_at'   => now(),
        ]);

        $syncedCount = 0;
        $errorCount  = 0;
        $errors      = [];

        try {
            $config = app(AppSettingService::class)->getNotionConfig();
            if (! empty($config['root_page_id'])) {
                $this->syncFromRootPage($config['root_page_id'], $syncedCount, $errorCount, $errors);
            } else {
                $syncedCount += $this->syncByWorkspaceSearch($errorCount, $errors);
            }

            $run->update([
                'status'       => $errorCount > 0 && $syncedCount === 0 ? 'error' : 'success',
                'synced_count' => $syncedCount,
                'error_count'  => $errorCount,
                'error_detail' => $errors ? implode("\n", $errors) : null,
                'finished_at'  => now(),
            ]);
        } catch (\Exception $e) {
            $run->update([
                'status'       => 'error',
                'error_count'  => 1,
                'error_detail' => $e->getMessage(),
                'finished_at'  => now(),
            ]);
            Log::error('NotionSync: 全体エラー', ['error' => $e->getMessage()]);
        }

        return $run->fresh();
    }

    private function syncFromRootPage(string $rootPageId, int &$syncedCount, int &$errorCount, array &$errors): void
    {
        $children = $this->fetchBlockChildren($rootPageId);

        foreach ($children as $block) {
            if (($block['type'] ?? '') !== 'child_page') {
                continue;
            }

            $pageTitle = $block['child_page']['title'] ?? '';
            if (! preg_match(self::BUSINESS_CODE_PATTERN, $pageTitle, $matches)) {
                continue;
            }

            $businessCode = $matches[1];
            $businessName = $matches[2];
            $businessPageId = $block['id'];

            try {
                $synced = $this->syncBusinessPage($businessPageId, $businessCode, $businessName);
                $syncedCount += $synced;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "[{$businessCode}_{$businessName}] " . $e->getMessage();
                Log::warning('NotionSync: 事業ページ同期失敗', [
                    'business_code' => $businessCode,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    private function syncByWorkspaceSearch(int &$errorCount, array &$errors): int
    {
        $synced = 0;
        $databases = $this->searchDatabasesByTitle('01_案件管理');

        foreach ($databases as $database) {
            $title = collect($database['title'] ?? [])->pluck('plain_text')->implode('');
            if ($title !== '01_案件管理') {
                continue;
            }

            $parentPageId = $database['parent']['page_id'] ?? null;
            if (! $parentPageId) {
                continue;
            }

            try {
                $parentPage = $this->fetchPage($parentPageId);
                $parentTitle = $this->extractPageTitle($parentPage);
                if (! preg_match(self::BUSINESS_CODE_PATTERN, $parentTitle, $matches)) {
                    continue;
                }

                $businessCode = $matches[1];
                $businessName = $matches[2];
                foreach ($this->queryDatabase($database['id']) as $record) {
                    $this->upsertProject($record, $businessCode, $businessName);
                    $synced++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = '[workspace-search] ' . $e->getMessage();
                Log::warning('NotionSync: 検索同期失敗', ['error' => $e->getMessage()]);
            }
        }

        return $synced;
    }

    /**
     * 事業ページ配下の「01_案件管理」DBを検索し、案件をupsertする。
     * 返却値: 同期した件数
     */
    private function syncBusinessPage(string $pageId, string $businessCode, string $businessName): int
    {
        $children = $this->fetchBlockChildren($pageId);
        $synced   = 0;

        foreach ($children as $block) {
            if (($block['type'] ?? '') !== 'child_database') {
                continue;
            }
            $dbTitle = $block['child_database']['title'] ?? '';
            if ($dbTitle !== '01_案件管理') {
                continue;
            }

            // DBのレコード（案件）を全件取得
            $records = $this->queryDatabase($block['id']);
            foreach ($records as $record) {
                $this->upsertProject($record, $businessCode, $businessName);
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * Notion DBレコードを projects テーブルへ upsert する。
     */
    private function upsertProject(array $page, string $businessCode, string $businessName): void
    {
        $props = $page['properties'] ?? [];

        // タイトルプロパティ「案件」から案件名を取得
        $name = $this->extractTitle($props['案件'] ?? $props['Name'] ?? []);
        if (empty($name)) {
            return; // 案件名がない場合はスキップ
        }

        // 案件番号（テキスト/数値プロパティ）
        $externalCode = $this->extractText($props['案件番号'] ?? []);

        Project::updateOrCreate(
            [
                'source_type' => 'notion',
                'source_key'  => $page['id'],
            ],
            [
                'name'          => $name,
                'business_code' => $businessCode,
                'business_name' => $businessName,
                'external_code' => $externalCode,
                'external_url'  => $page['url'] ?? null,
                'status'        => 'active',
                'is_editable'   => false,
                'sync_state'    => 'synced',
                'last_synced_at'=> now(),
            ]
        );
    }

    // ── Notion API ヘルパー ────────────────────────────────

    private function fetchBlockChildren(string $blockId): array
    {
        $results  = [];
        $cursor   = null;

        do {
            $params = ['page_size' => 100];
            if ($cursor) {
                $params['start_cursor'] = $cursor;
            }

            $response = $this->notionRequest('GET', "/blocks/{$blockId}/children", $params);
            $results  = array_merge($results, $response['results'] ?? []);
            $cursor   = $response['has_more'] ? ($response['next_cursor'] ?? null) : null;
        } while ($cursor);

        return $results;
    }

    private function queryDatabase(string $dbId): array
    {
        $results = [];
        $cursor  = null;

        do {
            $body = ['page_size' => 100];
            if ($cursor) {
                $body['start_cursor'] = $cursor;
            }

            $response = $this->notionRequest('POST', "/databases/{$dbId}/query", $body);
            $results  = array_merge($results, $response['results'] ?? []);
            $cursor   = $response['has_more'] ? ($response['next_cursor'] ?? null) : null;
        } while ($cursor);

        return $results;
    }

    private function searchDatabasesByTitle(string $query): array
    {
        $results = [];
        $cursor = null;

        do {
            $body = [
                'query' => $query,
                'page_size' => 100,
                'filter' => ['property' => 'object', 'value' => 'database'],
            ];
            if ($cursor) {
                $body['start_cursor'] = $cursor;
            }

            $response = $this->notionRequest('POST', '/search', $body);
            $results = array_merge($results, $response['results'] ?? []);
            $cursor = $response['has_more'] ? ($response['next_cursor'] ?? null) : null;
        } while ($cursor);

        return $results;
    }

    private function fetchPage(string $pageId): array
    {
        return $this->notionRequest('GET', "/pages/{$pageId}");
    }

    private function notionRequest(string $method, string $path, array $params = []): array
    {
        $token    = app(AppSettingService::class)->getNotionConfig()['token'];
        $url      = self::NOTION_API_BASE . $path;

        $request  = Http::withToken($token)
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION]);

        $response = match (strtoupper($method)) {
            'GET'  => $request->get($url, $params),
            'POST' => $request->post($url, $params),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if ($response->failed()) {
            throw new \RuntimeException(
                "Notion API エラー ({$response->status()}): " . $response->body()
            );
        }

        return $response->json();
    }

    private function extractPageTitle(array $page): string
    {
        foreach (($page['properties'] ?? []) as $property) {
            if (($property['type'] ?? null) === 'title') {
                return collect($property['title'] ?? [])->pluck('plain_text')->implode('');
            }
        }
        return '';
    }

    // ── プロパティ値抽出ヘルパー ─────────────────────────

    private function extractTitle(array $prop): string
    {
        return collect($prop['title'] ?? [])
            ->pluck('plain_text')
            ->implode('');
    }

    private function extractText(array $prop): ?string
    {
        // rich_text / number いずれにも対応
        if (isset($prop['rich_text'])) {
            $text = collect($prop['rich_text'])->pluck('plain_text')->implode('');
            return $text ?: null;
        }
        if (isset($prop['number'])) {
            return (string) $prop['number'];
        }
        return null;
    }
}

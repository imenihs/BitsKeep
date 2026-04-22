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

    private const NOTION_VERSION = '2022-06-28';

    // 事業ページ番号の正規表現（010〜099）
    private const BUSINESS_CODE_PATTERN = '/^(0[1-9][0-9])_(.+)$/u';

    public function isConfigured(): bool
    {
        return app(AppSettingService::class)->getNotionConfig()['configured'];
    }

    public function diagnoseConnection(): array
    {
        $config = app(AppSettingService::class)->getNotionConfig();

        if (empty($config['token'])) {
            return [
                'status' => 'unconfigured',
                'message' => 'Notion APIトークンが未設定です。',
                'action' => '連携設定から Notion API トークンを保存してください。',
            ];
        }

        try {
            $this->notionRequest('POST', '/search', [
                'page_size' => 1,
                'filter' => ['property' => 'object', 'value' => 'page'],
            ]);

            return [
                'status' => 'ok',
                'message' => 'Notion API への接続は正常です。',
                'action' => $config['root_page_id']
                    ? '案件管理で Notion同期を実行してください。'
                    : '対象DBまたは親ページをインテグレーションに共有したうえで、案件管理から Notion同期を実行してください。',
            ];
        } catch (\RuntimeException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'action' => $config['root_page_id']
                    ? 'ルートページURLと共有設定を確認し、必要なら連携設定を修正してください。'
                    : '対象の `01_案件管理` DB または親ページをインテグレーションに共有し、案件管理から再同期してください。',
            ];
        }
    }

    /**
     * Notion事業ページを自動発見し、01_案件管理 DBを同期する。
     * 同期結果を ProjectSyncRun として保存して返す。
     */
    public function discoverAndSync(int $triggeredBy): ProjectSyncRun
    {
        $run = ProjectSyncRun::create([
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $syncedCount = 0;
        $errorCount = 0;
        $errors = [];
        $notices = [];
        $businessResults = [];

        try {
            $config = app(AppSettingService::class)->getNotionConfig();
            if (! empty($config['root_page_id'])) {
                $this->syncFromRootPage($config['root_page_id'], $syncedCount, $errorCount, $errors, $notices, $businessResults);
            } else {
                $syncedCount += $this->syncByWorkspaceSearch($errorCount, $errors, $notices, $businessResults);
            }

            $run->update([
                'status' => $errorCount > 0 && $syncedCount === 0 ? 'error' : 'success',
                'synced_count' => $syncedCount,
                'error_count' => $errorCount,
                'error_detail' => array_filter([...$errors, ...$notices]) ? implode("\n", array_filter([...$errors, ...$notices])) : null,
                'business_results' => array_values($businessResults),
                'finished_at' => now(),
            ]);
        } catch (\Exception $e) {
            $run->update([
                'status' => 'error',
                'error_count' => 1,
                'error_detail' => $e->getMessage(),
                'business_results' => array_values($businessResults),
                'finished_at' => now(),
            ]);
            Log::error('NotionSync: 全体エラー', ['error' => $e->getMessage()]);
        }

        return $run->fresh();
    }

    private function syncFromRootPage(string $rootPageId, int &$syncedCount, int &$errorCount, array &$errors, array &$notices, array &$businessResults): void
    {
        $businessPages = $this->findBusinessPages($rootPageId);

        foreach ($businessPages as $businessPage) {
            try {
                $result = $this->syncBusinessPage($businessPage['id'], $businessPage['business_code'], $businessPage['business_name']);
                $syncedCount += $result['synced_count'];
                $businessResults[] = $this->buildBusinessResult(
                    $businessPage['business_code'],
                    $businessPage['business_name'],
                    $result['found_database']
                        ? ($result['synced_count'] > 0 ? 'success' : 'warning')
                        : 'warning',
                    $result['synced_count'],
                    $result['found_database']
                        ? ($result['synced_count'] > 0 ? "{$result['synced_count']}件を同期しました。" : '案件レコードが0件でした。')
                        : '`01_案件管理` データベースが見つかりませんでした。'
                );
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "[{$businessPage['business_code']}_{$businessPage['business_name']}] ".$e->getMessage();
                $businessResults[] = $this->buildBusinessResult(
                    $businessPage['business_code'],
                    $businessPage['business_name'],
                    'error',
                    0,
                    $e->getMessage()
                );
                Log::warning('NotionSync: 事業ページ同期失敗', [
                    'business_code' => $businessPage['business_code'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($businessPages)) {
            $notices[] = '指定したルートページ配下に `010_`〜`099_` の事業ページが見つかりません。URLと共有範囲を確認してください。';
        } elseif ($syncedCount === 0 && $errorCount === 0) {
            $notices[] = '同期対象は見つかりましたが、案件レコードが0件でした。Notion側の案件データを確認してください。';
        }
    }

    private function syncByWorkspaceSearch(int &$errorCount, array &$errors, array &$notices, array &$businessResults): int
    {
        $synced = 0;
        $databases = $this->searchDatabasesByTitle('01_案件管理');
        $matchedDatabases = 0;
        $groupedResults = [];

        foreach ($databases as $database) {
            $title = collect($database['title'] ?? [])->pluck('plain_text')->implode('');
            if ($title !== '01_案件管理') {
                continue;
            }
            $matchedDatabases++;

            $parentPageId = $database['parent']['page_id'] ?? null;
            if (! $parentPageId) {
                continue;
            }

            try {
                $businessPage = $this->resolveNearestBusinessPage($parentPageId);
                if (! $businessPage) {
                    continue;
                }

                $key = $businessPage['business_code'];
                if (! isset($groupedResults[$key])) {
                    $groupedResults[$key] = [
                        'business_code' => $businessPage['business_code'],
                        'business_name' => $businessPage['business_name'],
                        'synced_count' => 0,
                        'status' => 'success',
                        'messages' => [],
                    ];
                }

                foreach ($this->queryDatabase($database['id']) as $record) {
                    $this->upsertProject($record, $businessPage['business_code'], $businessPage['business_name']);
                    $synced++;
                    $groupedResults[$key]['synced_count']++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = '[workspace-search] '.$e->getMessage();
                if (isset($businessPage['business_code'])) {
                    $key = $businessPage['business_code'];
                    if (! isset($groupedResults[$key])) {
                        $groupedResults[$key] = [
                            'business_code' => $businessPage['business_code'],
                            'business_name' => $businessPage['business_name'],
                            'synced_count' => 0,
                            'status' => 'error',
                            'messages' => [$e->getMessage()],
                        ];
                    } else {
                        $groupedResults[$key]['status'] = 'error';
                        $groupedResults[$key]['messages'][] = $e->getMessage();
                    }
                }
                Log::warning('NotionSync: 検索同期失敗', ['error' => $e->getMessage()]);
            }
        }

        if ($matchedDatabases === 0) {
            $notices[] = 'アクセス可能な Notion 内に `01_案件管理` データベースが見つかりません。対象DBまたは親ページをインテグレーションへ共有してください。';
        } elseif ($synced === 0 && $errorCount === 0) {
            $notices[] = '対象の `01_案件管理` データベースは見つかりましたが、案件レコードが0件でした。Notion側の案件データを確認してください。';
        }

        foreach ($groupedResults as $result) {
            $status = $result['status'];
            $message = implode(' / ', array_filter($result['messages']));

            if ($status !== 'error' && $result['synced_count'] === 0) {
                $status = 'warning';
                $message = $message !== '' ? $message : '案件レコードが0件でした。';
            } elseif ($status === 'success') {
                $message = $message !== '' ? $message : "{$result['synced_count']}件を同期しました。";
            }

            $businessResults[] = $this->buildBusinessResult(
                $result['business_code'],
                $result['business_name'],
                $status,
                $result['synced_count'],
                $message
            );
        }

        return $synced;
    }

    /**
     * 事業ページ配下の「01_案件管理」DBを検索し、案件をupsertする。
     * 返却値: 同期した件数
     */
    private function syncBusinessPage(string $pageId, string $businessCode, string $businessName): array
    {
        $synced = 0;
        $databaseIds = $this->findProjectDatabases($pageId);
        $foundDatabase = ! empty($databaseIds);

        foreach ($databaseIds as $databaseId) {
            $records = $this->queryDatabase($databaseId);
            foreach ($records as $record) {
                $this->upsertProject($record, $businessCode, $businessName);
                $synced++;
            }
        }

        return [
            'synced_count' => $synced,
            'found_database' => $foundDatabase,
        ];
    }

    private function buildBusinessResult(string $businessCode, string $businessName, string $status, int $syncedCount, string $message): array
    {
        return [
            'business_code' => $businessCode,
            'business_name' => $businessName,
            'status' => $status,
            'synced_count' => $syncedCount,
            'message' => $message,
        ];
    }

    private function findBusinessPages(string $rootPageId, array &$visited = []): array
    {
        if (isset($visited[$rootPageId])) {
            return [];
        }
        $visited[$rootPageId] = true;

        $results = [];
        foreach ($this->fetchBlockChildren($rootPageId) as $block) {
            if (($block['type'] ?? '') !== 'child_page') {
                continue;
            }

            $pageTitle = $block['child_page']['title'] ?? '';
            if (preg_match(self::BUSINESS_CODE_PATTERN, $pageTitle, $matches)) {
                $results[] = [
                    'id' => $block['id'],
                    'business_code' => $matches[1],
                    'business_name' => $matches[2],
                ];
            }

            $results = array_merge($results, $this->findBusinessPages($block['id'], $visited));
        }

        return $results;
    }

    private function findProjectDatabases(string $pageId, array &$visited = []): array
    {
        if (isset($visited[$pageId])) {
            return [];
        }
        $visited[$pageId] = true;

        $databaseIds = [];
        foreach ($this->fetchBlockChildren($pageId) as $block) {
            if (($block['type'] ?? '') === 'child_database' && ($block['child_database']['title'] ?? '') === '01_案件管理') {
                $databaseIds[] = $block['id'];
            }

            if (($block['type'] ?? '') === 'child_page') {
                $databaseIds = array_merge($databaseIds, $this->findProjectDatabases($block['id'], $visited));
            }
        }

        return array_values(array_unique($databaseIds));
    }

    private function resolveNearestBusinessPage(string $pageId): ?array
    {
        $page = $this->fetchPage($pageId);
        $title = $this->extractPageTitle($page);

        if (preg_match(self::BUSINESS_CODE_PATTERN, $title, $matches)) {
            return [
                'id' => $pageId,
                'business_code' => $matches[1],
                'business_name' => $matches[2],
            ];
        }

        $parentPageId = $page['parent']['page_id'] ?? null;
        if (! $parentPageId || $parentPageId === $pageId) {
            return null;
        }

        return $this->resolveNearestBusinessPage($parentPageId);
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
                'source_key' => $page['id'],
            ],
            [
                'name' => $name,
                'business_code' => $businessCode,
                'business_name' => $businessName,
                'external_code' => $externalCode,
                'external_url' => $page['url'] ?? null,
                'status' => 'active',
                'is_editable' => false,
                'sync_state' => 'synced',
                'last_synced_at' => now(),
            ]
        );
    }

    // ── Notion API ヘルパー ────────────────────────────────

    private function fetchBlockChildren(string $blockId): array
    {
        $results = [];
        $cursor = null;

        do {
            $params = ['page_size' => 100];
            if ($cursor) {
                $params['start_cursor'] = $cursor;
            }

            $response = $this->notionRequest('GET', "/blocks/{$blockId}/children", $params);
            $results = array_merge($results, $response['results'] ?? []);
            $cursor = $response['has_more'] ? ($response['next_cursor'] ?? null) : null;
        } while ($cursor);

        return $results;
    }

    private function queryDatabase(string $dbId): array
    {
        $results = [];
        $cursor = null;

        do {
            $body = ['page_size' => 100];
            if ($cursor) {
                $body['start_cursor'] = $cursor;
            }

            $response = $this->notionRequest('POST', "/databases/{$dbId}/query", $body);
            $results = array_merge($results, $response['results'] ?? []);
            $cursor = $response['has_more'] ? ($response['next_cursor'] ?? null) : null;
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
        $token = app(AppSettingService::class)->getNotionConfig()['token'];
        $url = self::NOTION_API_BASE.$path;

        $request = Http::withToken($token)
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION]);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $params),
            'POST' => $request->post($url, $params),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if ($response->failed()) {
            $body = $response->json() ?? [];
            $code = $body['code'] ?? null;
            $message = $body['message'] ?? $response->body();
            throw new \RuntimeException($this->buildUserFacingErrorMessage($response->status(), $code, $message, $path));
        }

        return $response->json();
    }

    private function buildUserFacingErrorMessage(int $status, ?string $code, string $message, string $path): string
    {
        if (in_array($status, [401, 403], true)) {
            return 'Notion APIトークンが無効か、対象ページ/DBへのアクセス権がありません。トークンと共有設定を確認してください。';
        }

        if ($status === 404 || $code === 'object_not_found') {
            if (str_starts_with($path, '/blocks/') || str_starts_with($path, '/pages/')) {
                return '指定したルートページURLにアクセスできません。URLが正しいか、そのページがインテグレーションに共有されているか確認してください。';
            }

            return 'Notion上の対象オブジェクトが見つかりません。共有設定または対象ページ/DBの存在を確認してください。';
        }

        if ($status === 429) {
            return 'Notion API の呼び出し回数制限に達しました。少し待ってから再試行してください。';
        }

        if ($status >= 500) {
            return 'Notion API 側でエラーが発生しました。時間を置いて再試行してください。';
        }

        return "Notion API エラー ({$status}): {$message}";
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
        // rich_text / number / formula いずれにも対応
        if (isset($prop['rich_text'])) {
            $text = collect($prop['rich_text'])->pluck('plain_text')->implode('');

            return $text ?: null;
        }
        if (isset($prop['number'])) {
            return (string) $prop['number'];
        }
        if (isset($prop['formula'])) {
            $f = $prop['formula'];

            return match ($f['type'] ?? null) {
                'string' => $f['string'] ?: null,
                'number' => isset($f['number']) ? (string) $f['number'] : null,
                default => null,
            };
        }

        return null;
    }
}

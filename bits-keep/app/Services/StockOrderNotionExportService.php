<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class StockOrderNotionExportService
{
    private const NOTION_API_BASE = 'https://api.notion.com/v1';

    private const NOTION_VERSION = '2022-06-28';

    public function isConfigured(): bool
    {
        $config = app(AppSettingService::class)->getNotionConfig();

        return ! empty($config['token']) && ! empty($config['root_page_id']);
    }

    public function exportSupplierOrder(string $supplierName, array $items, string $operatorName): array
    {
        $config = app(AppSettingService::class)->getNotionConfig();
        if (empty($config['token'])) {
            throw new RuntimeException('Notion APIトークンが未設定です。連携設定から登録してください。');
        }
        if (empty($config['root_page_id'])) {
            throw new RuntimeException('Notion出力先のルートページURLが未設定です。連携設定でルートページを保存してください。');
        }

        $title = sprintf('発注リスト_%s_%s', $supplierName, now()->format('Y-m-d_H-i'));
        $total = collect($items)->sum(fn ($item) => (float) ($item['subtotal'] ?? 0));

        $children = [
            $this->paragraphBlock(sprintf('作成日時: %s', now()->format('Y-m-d H:i'))),
            $this->paragraphBlock(sprintf('作成者: %s', $operatorName)),
            $this->paragraphBlock(sprintf('商社: %s', $supplierName)),
            $this->paragraphBlock(sprintf('合計: %s 円', number_format($total, 2))),
            $this->headingBlock('発注明細'),
        ];

        foreach ($items as $item) {
            $children[] = $this->bulletBlock(sprintf(
                '%s / %s / %s / 数量:%s / 単位:%s / 単価:%s / 小計:%s',
                $item['name'] ?? '-',
                $item['part_number'] ?? '-',
                $item['supplier_part_number'] ?: '商社型番未設定',
                number_format((float) ($item['quantity'] ?? 0), 0),
                $item['purchase_unit_label'] ?? '未設定',
                number_format((float) ($item['unit_price'] ?? 0), 2),
                number_format((float) ($item['subtotal'] ?? 0), 2),
            ));
        }

        $response = $this->notionRequest('POST', '/pages', [
            'parent' => [
                'type' => 'page_id',
                'page_id' => $config['root_page_id'],
            ],
            'properties' => [
                'title' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => $title],
                    ],
                ],
            ],
            'children' => $children,
        ], $config['token']);

        return [
            'page_id' => $response['id'] ?? null,
            'page_url' => $response['url'] ?? null,
            'title' => $title,
        ];
    }

    private function notionRequest(string $method, string $path, array $payload, string $token): array
    {
        $response = Http::withToken($token)
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->send($method, self::NOTION_API_BASE.$path, [
                'json' => $payload,
            ]);

        if ($response->failed()) {
            $body = $response->json() ?? [];
            $message = $body['message'] ?? $response->body();
            throw new RuntimeException("Notion出力に失敗しました: {$message}");
        }

        return $response->json();
    }

    private function headingBlock(string $text): array
    {
        return [
            'object' => 'block',
            'type' => 'heading_2',
            'heading_2' => [
                'rich_text' => [$this->richText($text)],
            ],
        ];
    }

    private function paragraphBlock(string $text): array
    {
        return [
            'object' => 'block',
            'type' => 'paragraph',
            'paragraph' => [
                'rich_text' => [$this->richText($text)],
            ],
        ];
    }

    private function bulletBlock(string $text): array
    {
        return [
            'object' => 'block',
            'type' => 'bulleted_list_item',
            'bulleted_list_item' => [
                'rich_text' => [$this->richText($text)],
            ],
        ];
    }

    private function richText(string $text): array
    {
        return [
            'type' => 'text',
            'text' => ['content' => mb_substr($text, 0, 1900)],
        ];
    }
}

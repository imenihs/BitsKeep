<?php

namespace App\Services\Datasheet;

use App\Models\SpecGroup;

/**
 * 解析結果へ、部品分類候補とスペック詳細テンプレートの推薦を付ける。
 * 解析エンジンを増やしても推薦の出方を揃えるため、この処理は1箇所に置き、
 * 同期解析APIとキュー経由の解析の双方から使う。
 */
class DatasheetRecommendationDecorator
{
    /**
     * 目的: 解析結果へ分類候補と推薦テンプレートを付ける。
     * 機能: 解析結果の部品種別名から既存分類を突き合わせ、対応するスペック詳細グループとテンプレートを添える。
     * 入力: $result は正規化済み解析結果。
     * 出力: category_candidates / recommended_spec_groups / template_candidates / recommended_group_ids / recommended_template_ids を追加した配列。
     * 動作条件: spec_groups が参照可能であること。
     * 副作用: DBを参照する。
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function decorate(array $result): array
    {
        $categoryCandidates = $this->resolveCategoryCandidates($this->extractCategoryNames($result));
        $categoryIds = collect($categoryCandidates)
            ->pluck('category_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // 分類が1つも当たらなかった場合は推薦を出さない。無関係なテンプレートを勧めない
        $groups = $categoryIds->isEmpty()
            ? collect()
            : SpecGroup::query()
                ->where('name', '!=', '共通')
                ->whereIn('id', $categoryIds)
                ->with([
                    'specTypes' => fn ($query) => $query->with(['units', 'aliases']),
                    'templates' => fn ($query) => $query->with(['items.specType.units', 'items.specType.aliases']),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

        $groups->each(function (SpecGroup $group) {
            $group->is_suggested = true;
        });

        $templates = $groups
            ->flatMap(function (SpecGroup $group) {
                return $group->templates->each(function ($template) {
                    $template->is_suggested = true;
                });
            })
            ->values();

        $result['category_candidates'] = $categoryCandidates;
        $result['recommended_spec_groups'] = $groups->values();
        $result['template_candidates'] = $templates;
        $result['recommended_group_ids'] = $groups->pluck('id')->values();
        $result['recommended_template_ids'] = $templates->pluck('id')->values();

        return $result;
    }

    /**
     * 目的: 解析結果から部品分類名の候補を集める。
     * 機能: 複数形キーと単数形キーの双方を見て、文字列と配列のどちらの形でも拾う。
     * 入力: $result は正規化済み解析結果。
     * 出力: 空要素と重複を除いた分類名の配列。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @param  array<string, mixed>  $result
     * @return array<int, string>
     */
    private function extractCategoryNames(array $result): array
    {
        $names = collect();

        foreach (['component_types', 'category_names', 'categories'] as $key) {
            $values = $result[$key] ?? [];
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (is_string($value)) {
                    $names->push($value);
                } elseif (is_array($value)) {
                    $names->push($value['name'] ?? $value['category_name'] ?? '');
                }
            }
        }

        foreach (['component_type', 'category_name'] as $key) {
            if (is_string($result[$key] ?? null)) {
                $names->push($result[$key]);
            }
        }

        return $names
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 目的: 分類名の候補を既存分類へ突き合わせる。
     * 機能: 各候補名について一致した分類を添えて返す。
     * 入力: $names は分類名の候補配列。
     * 出力: name / category_id / category / matched を持つ配列。
     * 動作条件: spec_groups が参照可能であること。
     * 副作用: DBを参照する。
     *
     * @param  array<int, string>  $names
     * @return array<int, array<string, mixed>>
     */
    private function resolveCategoryCandidates(array $names): array
    {
        $categories = SpecGroup::query()
            ->where('name', '!=', '共通')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return collect($names)
            ->map(function (string $name) use ($categories) {
                $matched = $this->matchCategoryByName($name, $categories);

                return [
                    'name' => $name,
                    'category_id' => $matched?->id,
                    'category' => $matched,
                    'matched' => (bool) $matched,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 目的: 分類名から既存分類を1件選ぶ。
     * 機能: 記号と空白を無視した完全一致を先に見て、無ければ部分一致へ落とす。
     * 入力: $name は分類名、$categories は既存分類の一覧。
     * 出力: 一致した分類、無ければ null。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @param  \Illuminate\Support\Collection<int, SpecGroup>  $categories
     */
    private function matchCategoryByName(string $name, $categories): ?SpecGroup
    {
        $normalized = $this->normalizeMatchText($name);
        if ($normalized === '') {
            return null;
        }

        // 完全一致を優先する。部分一致を先に見ると短い分類名が広く当たってしまう
        $matched = $categories->first(fn (SpecGroup $category) => $this->normalizeMatchText($category->name) === $normalized);
        if ($matched) {
            return $matched;
        }

        return $categories->first(function (SpecGroup $category) use ($normalized) {
            $categoryName = $this->normalizeMatchText($category->name);

            return $categoryName !== '' && (str_contains($normalized, $categoryName) || str_contains($categoryName, $normalized));
        });
    }

    /**
     * 目的: 突き合わせ用に表記を揃える。
     * 機能: 空白と記号を除き、小文字へ寄せる。
     * 入力: $value は比較対象の文字列。
     * 出力: 正規化済み文字列。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function normalizeMatchText(?string $value): string
    {
        return mb_strtolower(preg_replace('/[\s()\[\]_.-]+/u', '', (string) $value) ?? '');
    }
}

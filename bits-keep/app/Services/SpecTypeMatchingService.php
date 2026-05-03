<?php

namespace App\Services;

use App\Models\SpecType;
use Illuminate\Support\Collection;

/**
 * Gemini が抽出したスペック名を既存 spec_types と照合し、
 * マッチしたものに spec_type_id を付与する。
 */
class SpecTypeMatchingService
{
    /**
     * 目的: Spec Type Matchingのmatchを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $specs。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     * @param  array<int, array<string, mixed>>  $specs
     * @return array<int, array<string, mixed>>
     */
    public function match(array $specs): array
    {
        if (empty($specs)) {
            return [];
        }

        // DB から全 spec_type を取得（アーカイブ済みは除外）
        $specTypes = SpecType::query()
            ->with('aliases')
            ->select('id', 'name', 'name_ja', 'name_en', 'symbol')
            ->get();

        // specType ごとの正規化名を事前計算してキャッシュ（matchOne 内で繰り返し再構築しない）
        $normalizedNamesCache = $specTypes->mapWithKeys( fn ($st) => [$st->id => $this->normalizedNames($st)]
        )->all();

        return array_map( fn ($spec) => $this->matchOne($spec, $specTypes, $normalizedNamesCache), $specs);
    }

    /**
     * 目的: Spec Type Matchingのmatchoneを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $spec, $specTypes, $normalizedNamesCache。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     * @param  array<int|string, array<int, string>>  $normalizedNamesCache
     */
    private function matchOne(array $spec, Collection $specTypes, array $normalizedNamesCache): array
    {
        $names = array_filter([
            $spec['name'] ?? '',
            $spec['name_ja'] ?? '',
            $spec['name_en'] ?? '',
            $spec['symbol'] ?? '',
        ], fn ($value) => trim((string) $value) !== '');
        $matched = null;

        foreach ($names as $name) {
            foreach ($this->normalizedInputCandidates((string) $name) as $normalized) {
                $matched = $specTypes->first( fn ($st) => in_array($normalized, $normalizedNamesCache[$st->id] ?? [], true));

                if (! $matched) {
                    $matched = $specTypes->first(function ($st) use ($normalized, $normalizedNamesCache) {
                        foreach ($normalizedNamesCache[$st->id] ?? [] as $candidate) {
                            if ($candidate !== '' && (str_contains($normalized, $candidate) || str_contains($candidate, $normalized))) {
                                return true;
                            }
                        }
                        return false;
                    });
                }

                if ($matched) {
                    break 2;
                }
            }
        }

        return array_merge($spec, [
            'name' => (string) ($spec['name'] ?? $spec['name_ja'] ?? ''),
            'name_ja' => (string) ($spec['name_ja'] ?? ''),
            'name_en' => (string) ($spec['name_en'] ?? ''),
            'symbol' => (string) ($spec['symbol'] ?? ''),
            'value' => $spec['value'] ?? '',
            'unit' => $spec['unit'] ?? '',
            'spec_type_id' => $matched?->id,
            'spec_type_name' => $matched?->name_ja ?? $matched?->name ?? '',
            'matched' => $matched !== null,
        ]);
    }

    /**
     * 目的: Spec Type Matchingのnormalized名称を担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $specType。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     * @return array<int, string>
     */
    private function normalizedNames(SpecType $specType): array
    {
        return collect([
            $specType->name,
            $specType->name_ja,
            $specType->name_en,
            $specType->symbol,
            ...$specType->aliases->pluck('alias')->all(),
        ])
            ->flatMap( fn ($value) => $this->normalizedInputCandidates((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 目的: Spec Type Matchingのnormalized入力候補を担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $value。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     * @return array<int, string>
     */
    private function normalizedInputCandidates(string $value): array
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return [];
        }

        return collect([
            $normalized,
            $this->stripValueModifiers($normalized),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
    /**
     * 目的: Spec Type Matchingのstrip値modifiersを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $value。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    private function stripValueModifiers(string $value): string
    {
        $patterns = [
            '/^(最大|最小|標準|代表|typ|type|typical|min|max|minimum|maximum)/u',
            '/(最大値|最小値|標準値|代表値|typ値|min値|max値|typicalvalue|minimumvalue|maximumvalue)$/u',
            '/(absolute|maximumrating|maximumratings|rating|ratings)$/u',
            '/(dc|ac|pulse|pulsed|peak|continuous)$/u',
        ];

        $stripped = $value;
        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, '', $stripped) ?? $stripped;
        }

        return $stripped;
    }
    /**
     * 目的: Spec Type Matchingの正規化を担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $s。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     */
    private function normalize(string $s): string
    {
        return strtolower(preg_replace('/[\s\(\)\[\]_\-\.~]/u', '', $s));
    }
}

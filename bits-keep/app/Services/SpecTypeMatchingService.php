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

        return array_map(fn ($spec) => $this->matchOne($spec, $specTypes), $specs);
    }

    private function matchOne(array $spec, Collection $specTypes): array
    {
        $names = array_filter([
            $spec['name'] ?? '',
            $spec['name_ja'] ?? '',
            $spec['name_en'] ?? '',
            $spec['symbol'] ?? '',
        ], fn ($value) => trim((string) $value) !== '');
        $matched = null;

        foreach ($names as $name) {
            $normalized = $this->normalize((string) $name);
            if ($normalized === '') {
                continue;
            }

            $matched = $specTypes->first(fn ($st) => in_array($normalized, $this->normalizedNames($st), true));

            if (! $matched) {
                $matched = $specTypes->first(function ($st) use ($normalized) {
                    foreach ($this->normalizedNames($st) as $candidate) {
                        if ($candidate !== '' && (str_contains($normalized, $candidate) || str_contains($candidate, $normalized))) {
                            return true;
                        }
                    }
                    return false;
                });
            }

            if ($matched) {
                break;
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
            ->map(fn ($value) => $this->normalize((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalize(string $s): string
    {
        return strtolower(preg_replace('/[\s\(\)\[\]_\-\.~]/u', '', $s));
    }
}

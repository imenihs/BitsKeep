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
            ->select('id', 'name')
            ->get();

        return array_map(fn ($spec) => $this->matchOne($spec, $specTypes), $specs);
    }

    private function matchOne(array $spec, Collection $specTypes): array
    {
        $name = $spec['name'] ?? '';
        $normalized = $this->normalize($name);
        $matched = null;

        if ($normalized !== '') {
            // 1. 完全一致（大文字小文字・スペース無視）
            $matched = $specTypes->first(
                fn ($st) => $this->normalize($st->name) === $normalized
            );

            // 2. 部分一致（Geminiが長い条件名を返した場合に備える）
            if (! $matched) {
                $matched = $specTypes->first(function ($st) use ($normalized) {
                    $specTypeName = $this->normalize($st->name);

                    return $specTypeName !== ''
                        && (str_contains($normalized, $specTypeName) || str_contains($specTypeName, $normalized));
                });
            }
        }

        return array_merge($spec, [
            'name' => $name,
            'value' => $spec['value'] ?? '',
            'unit' => $spec['unit'] ?? '',
            'spec_type_id' => $matched?->id,
            'matched' => $matched !== null,
        ]);
    }

    private function normalize(string $s): string
    {
        return strtolower(preg_replace('/[\s\(\)\[\]_\-\.]/u', '', $s));
    }
}

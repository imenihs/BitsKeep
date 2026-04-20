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
     * @param  array<array{name: string, value: string, unit: string}>  $specs
     * @return array<array{name: string, value: string, unit: string, spec_type_id: int|null, matched: bool}>
     */
    public function match(array $specs): array
    {
        if (empty($specs)) {
            return [];
        }

        // DB から全 spec_type を取得（アーカイブ済みは除外）
        $specTypes = SpecType::select('id', 'name', 'symbol', 'unit')
            ->whereNull('deleted_at')
            ->get();

        return array_map(fn ($spec) => $this->matchOne($spec, $specTypes), $specs);
    }

    private function matchOne(array $spec, Collection $specTypes): array
    {
        $name = $spec['name'] ?? '';

        // 1. 完全一致（大文字小文字・スペース無視）
        $normalized = $this->normalize($name);
        $matched = $specTypes->first(
            fn ($st) => $this->normalize($st->name) === $normalized
                     || $this->normalize($st->symbol ?? '') === $normalized
        );

        // 2. 部分一致（Geminiが長い条件名を返した場合に備える）
        if (! $matched) {
            $matched = $specTypes->first(
                fn ($st) => str_contains($normalized, $this->normalize($st->name))
                         || str_contains($normalized, $this->normalize($st->symbol ?? ''))
            );
        }

        return [
            'name'         => $name,
            'value'        => $spec['value'] ?? '',
            'unit'         => $spec['unit'] ?? '',
            'spec_type_id' => $matched?->id,
            'matched'      => $matched !== null,
        ];
    }

    private function normalize(string $s): string
    {
        return strtolower(preg_replace('/[\s\(\)\[\]_\-\.]/u', '', $s));
    }
}

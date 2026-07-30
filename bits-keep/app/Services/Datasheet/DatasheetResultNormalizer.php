<?php

namespace App\Services\Datasheet;

/**
 * 解析エンジンが返した生の JSON を、画面と後段処理が扱う形へ正規化する。
 * エンジンを増やしても後段（スペック詳細照合、分類候補、テンプレート推薦）を
 * 分岐させないため、正規化はここ1箇所に持たせる。
 */
class DatasheetResultNormalizer
{
    /**
     * 目的: 解析エンジンの生JSONを正規化済み解析結果へ変換する。
     * 機能: スペック値のプロファイル別補完、部品種別とパッケージ候補の重複排除、空値の除去を行う。
     * 入力: $raw は解析エンジンが返した連想配列。
     * 出力: part_number / manufacturer / common_name / component_types / package_names / description / specs を持つ配列。
     * 動作条件: $raw が JSON デコード済みの配列であること。キー欠落は許容する。
     * 副作用: なし。
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalize(array $raw): array
    {
        $specs = [];
        foreach ($raw['specs'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            // 名称も記号も無い行は候補として意味を持たないため落とす
            if (empty($item['name']) && empty($item['name_ja']) && empty($item['name_en']) && empty($item['symbol'])) {
                continue;
            }

            $profile = $this->normalizeProfile((string) ($item['value_profile'] ?? $item['profile'] ?? $item['value_mode'] ?? ''));
            $valueTyp = trim((string) ($item['value_typ'] ?? $item['typ'] ?? ''));
            $valueMin = trim((string) ($item['value_min'] ?? $item['min'] ?? ''));
            $valueMax = trim((string) ($item['value_max'] ?? $item['max'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));

            // プロファイルごとに、対応する値が空なら単一値 value から埋め戻す
            if ($profile === 'typ' && $valueTyp === '') {
                $valueTyp = $value;
            } elseif ($profile === 'range' && ($valueMin === '' || $valueMax === '')) {
                [$valueMin, $valueMax] = $this->splitRangeFallback($value, $valueMin, $valueMax);
            } elseif ($profile === 'max_only' && $valueMax === '') {
                $valueMax = $value;
            } elseif ($profile === 'min_only' && $valueMin === '') {
                $valueMin = $value;
            } elseif ($profile === 'triple' && ($valueMin === '' || $valueTyp === '' || $valueMax === '')) {
                [$valueMin, $valueTyp, $valueMax] = $this->splitTripleFallback($value, $valueMin, $valueTyp, $valueMax);
            }

            $specs[] = [
                'name' => (string) ($item['name'] ?? $item['name_ja'] ?? ''),
                'name_ja' => (string) ($item['name_ja'] ?? $item['name'] ?? ''),
                'name_en' => (string) ($item['name_en'] ?? ''),
                'symbol' => (string) ($item['symbol'] ?? ''),
                'value_profile' => $profile,
                'value' => $value,
                'value_typ' => $valueTyp,
                'value_min' => $valueMin,
                'value_max' => $valueMax,
                'unit' => (string) ($item['unit'] ?? ''),
            ];
        }

        return [
            'part_number' => ! empty($raw['part_number']) ? trim((string) $raw['part_number']) : null,
            'manufacturer' => ! empty($raw['manufacturer']) ? trim((string) $raw['manufacturer']) : null,
            'common_name' => ! empty($raw['common_name']) ? trim((string) $raw['common_name']) : null,
            'component_types' => $this->normalizeNameList($raw, 'component_types', 'component_type'),
            'package_names' => $this->normalizeNameList($raw, 'package_names', 'package_name'),
            'description' => ! empty($raw['description']) ? trim((string) $raw['description']) : null,
            'specs' => $specs,
        ];
    }

    /**
     * 目的: 候補名の配列を整えて返す。
     * 機能: 配列キーが空のときは単数キーから拾い、空要素と重複を落とす。
     * 入力: $raw は生JSON、$pluralKey は配列キー名、$singularKey は単数キー名。
     * 出力: 空要素と重複を除いた文字列配列。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, string>
     */
    private function normalizeNameList(array $raw, string $pluralKey, string $singularKey): array
    {
        $values = $raw[$pluralKey] ?? [];
        if (! is_array($values)) {
            $values = [];
        }

        // 配列側が空でも単数キーに値があるエンジン出力を取りこぼさない
        if ($values === [] && ! empty($raw[$singularKey])) {
            $values = [$raw[$singularKey]];
        }

        return collect($values)
            ->map(fn ($item) => is_scalar($item) ? trim((string) $item) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 目的: 値プロファイル表記を内部表記へ揃える。
     * 機能: エンジンごとの表記差を既知の5種へ寄せる。
     * 入力: $profile はエンジンが返した表記。
     * 出力: typ / range / max_only / min_only / triple のいずれか。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function normalizeProfile(string $profile): string
    {
        $normalized = strtolower(trim($profile));

        return match ($normalized) {
            'range' => 'range',
            'max', 'max_only' => 'max_only',
            'min', 'min_only' => 'min_only',
            'triple' => 'triple',
            default => 'typ',
        };
    }

    /**
     * 目的: 範囲値の単一文字列を最小値と最大値へ分解する。
     * 機能: 全角と半角のチルダ、`to` を区切りとして2分割する。
     * 入力: $value は `-40〜125` のような文字列、$currentMin と $currentMax は既に取れている値。
     * 出力: [最小値, 最大値]。分解できない場合は入力値をそのまま返す。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array{0: string, 1: string}
     */
    private function splitRangeFallback(string $value, string $currentMin, string $currentMax): array
    {
        if ($value === '') {
            return [$currentMin, $currentMax];
        }

        $parts = preg_split('/\s*(?:〜|~|～|to)\s*/iu', $value, 2) ?: [];
        // 2分割できないものは範囲表記ではないと判断し、書き換えない
        if (count($parts) !== 2) {
            return [$currentMin, $currentMax];
        }

        return [
            $currentMin !== '' ? $currentMin : trim($parts[0]),
            $currentMax !== '' ? $currentMax : trim($parts[1]),
        ];
    }

    /**
     * 目的: min/typ/max の単一文字列を3値へ分解する。
     * 機能: スラッシュまたは縦棒を区切りとして3分割する。
     * 入力: $value は `1.2/1.5/1.8` のような文字列、$currentMin と $currentTyp と $currentMax は既に取れている値。
     * 出力: [最小値, typ値, 最大値]。分解できない場合は入力値をそのまま返す。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitTripleFallback(string $value, string $currentMin, string $currentTyp, string $currentMax): array
    {
        if ($value === '') {
            return [$currentMin, $currentTyp, $currentMax];
        }

        $parts = preg_split('/\s*(?:\/|／|\|)\s*/u', $value, 3) ?: [];
        // 3分割できないものは3値表記ではないと判断し、書き換えない
        if (count($parts) !== 3) {
            return [$currentMin, $currentTyp, $currentMax];
        }

        return [
            $currentMin !== '' ? $currentMin : trim($parts[0]),
            $currentTyp !== '' ? $currentTyp : trim($parts[1]),
            $currentMax !== '' ? $currentMax : trim($parts[2]),
        ];
    }
}

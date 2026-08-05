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
    /** 完全一致の基礎点。部分一致より確実な根拠なので高く置く */
    private const EXACT_MATCH_SCORE = 10;

    /** 部分一致の基礎点。完全一致より弱い根拠として扱う */
    private const PARTIAL_MATCH_SCORE = 4;

    /** 最も信頼する入力語（名前）に与える重み */
    private const MAX_NAME_WEIGHT = 4;

    /** 記号など情報量の乏しい入力語に与える重みの下限 */
    private const MIN_NAME_WEIGHT = 1;

    /**
     * 部分一致を根拠として認める、種別側の語の最小文字数。
     * 「C」「V」のような1〜2文字の記号は型番や単位記号に偶然含まれるため、
     * これ未満の語では部分一致を採用しない。
     */
    private const MIN_PARTIAL_MATCH_LENGTH = 3;

    /**
     * 対応づけを採用する最低点。
     * 記号のみの部分一致（4 × 1 = 4）では届かず、
     * 記号の完全一致（10 × 1 = 10）以上を要求する。
     */
    private const MIN_ACCEPTABLE_SCORE = 10;

    /**
     * 目的: Spec Type Matchingのmatchを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $specs。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     *
     * @param  array<int, array<string, mixed>>  $specs
     * @return array<int, array<string, mixed>>
     */
    public function match(array $specs): array
    {
        if (empty($specs)) {
            return [];
        }

        // DB から全 spec_type を取得（アーカイブ済みは除外）
        // base_unit は単位整合の判定に使うため必ず取得する
        $specTypes = SpecType::query()
            ->with('aliases')
            ->select('id', 'name', 'name_ja', 'name_en', 'symbol', 'base_unit')
            ->get();

        // specType ごとの正規化名を事前計算してキャッシュ（matchOne 内で繰り返し再構築しない）
        $normalizedNamesCache = $specTypes->mapWithKeys(fn ($st) => [$st->id => $this->normalizedNames($st)]
        )->all();

        return array_map(fn ($spec) => $this->matchOne($spec, $specTypes, $normalizedNamesCache), $specs);
    }

    /**
     * 目的: Spec Type Matchingのmatchoneを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $spec, $specTypes, $normalizedNamesCache。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     *
     * @param  array<int|string, array<int, string>>  $normalizedNamesCache
     */
    private function matchOne(array $spec, Collection $specTypes, array $normalizedNamesCache): array
    {
        // 照合に使う入力語。名前が最も情報量が多く、記号は最後の手掛かりとして扱う
        $names = array_filter([
            $spec['name'] ?? '',
            $spec['name_ja'] ?? '',
            $spec['name_en'] ?? '',
            $spec['symbol'] ?? '',
        ], fn ($value) => trim((string) $value) !== '');

        // 単位が判明していれば、単位の合わない種別は候補から外す。
        // 「電圧(V)」が「容量(F)」へ化けるような、物理的にありえない対応を防ぐ
        $unit = $this->normalizeUnit((string) ($spec['unit'] ?? ''));
        $candidates = $specTypes;
        if ($unit !== '') {
            $sameUnit = $specTypes->filter(fn ($st) => $this->normalizeUnit((string) ($st->base_unit ?? '')) === $unit);
            // 単位の合う種別が1件でもあれば、その中だけで選ぶ
            if ($sameUnit->isNotEmpty()) {
                $candidates = $sameUnit;
            }
        }

        $matched = $this->selectBestMatch($names, $candidates, $normalizedNamesCache);

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
     * 目的: 入力語と候補種別を突き合わせ、最も根拠の強い種別を1つ選ぶ。
     * 機能: 候補ごとに一致の強さを点数化し、最高点の候補を返す。同点は先に定義された種別を優先する。
     * 入力: $names は入力側の呼称（名前・和名・英名・記号の順）、$candidates は単位で絞り込み済みの候補、$normalizedNamesCache は種別ごとの正規化済み呼称。
     * 出力: 採用した SpecType。基準に満たない場合は null。
     * 動作条件: $normalizedNamesCache が $candidates の全 id を含むこと。
     * 副作用: なし。
     *
     * @param  array<int, mixed>  $names
     * @param  array<int|string, array<int, string>>  $normalizedNamesCache
     */
    private function selectBestMatch(array $names, Collection $candidates, array $normalizedNamesCache): ?SpecType
    {
        $best = null;
        $bestScore = 0;

        foreach ($candidates as $specType) {
            $score = $this->scoreCandidate($names, $normalizedNamesCache[$specType->id] ?? []);

            // 同点のときは先に定義された種別を採る（マスタの並び順が優先度を表す）
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $specType;
            }
        }

        // 根拠が弱すぎる対応は採用しない。誤った種別を自信ありげに出すより未分類のほうが害が小さい
        return $bestScore >= self::MIN_ACCEPTABLE_SCORE ? $best : null;
    }

    /**
     * 目的: 入力語と1つの種別との一致の強さを点数にする。
     * 機能: 完全一致を最も高く、語の包含をその次に評価し、記号のみの短い一致は加点しない。
     * 入力: $names は入力側の呼称（先頭ほど信頼度が高い）、$typeNames は種別側の正規化済み呼称。
     * 出力: 一致の強さ。0 は一致なし。
     * 動作条件: なし。
     * 副作用: なし。
     *
     * @param  array<int, mixed>  $names
     * @param  array<int, string>  $typeNames
     */
    private function scoreCandidate(array $names, array $typeNames): int
    {
        $score = 0;
        // 入力語の並び順は信頼度の高い順。後ろの語ほど加点を減らし、名前による一致を優先する
        $rank = 0;

        foreach ($names as $name) {
            $rank++;
            // 4番目以降（記号など）は情報量が乏しいので減点幅を一定にする
            $weight = max(self::MIN_NAME_WEIGHT, self::MAX_NAME_WEIGHT - ($rank - 1));

            foreach ($this->normalizedInputCandidates((string) $name) as $normalized) {
                foreach ($typeNames as $candidate) {
                    if ($candidate === '') {
                        continue;
                    }

                    // 完全一致。最も確実な根拠
                    if ($normalized === $candidate) {
                        $score = max($score, self::EXACT_MATCH_SCORE * $weight);

                        continue;
                    }

                    // 部分一致は短い語ほど偶然当たりやすい。
                    // 「2SC1213」の C が「容量(C)」に当たるような事故を防ぐため、
                    // 種別側の語が十分に長いときだけ根拠として認める
                    if (mb_strlen($candidate) >= self::MIN_PARTIAL_MATCH_LENGTH && str_contains($normalized, $candidate)) {
                        $score = max($score, self::PARTIAL_MATCH_SCORE * $weight);
                    }
                }
            }
        }

        return $score;
    }

    /**
     * 目的: 単位表記の揺れを吸収し、比較できる形にする。
     * 機能: 大文字小文字と記号の差、オーム・度の別表記をひとつに寄せる。
     * 入力: $unit は単位文字列。
     * 出力: 比較用に正規化した単位。判定に使えない場合は空文字。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function normalizeUnit(string $unit): string
    {
        $normalized = trim($unit);
        if ($normalized === '') {
            return '';
        }

        // オームと度は環境により表記が分かれるため代表表記へ寄せる
        $replacements = [
            'Ω' => 'ohm', 'Ω' => 'ohm', 'ohms' => 'ohm',
            '℃' => 'degc', '°C' => 'degc', '度' => 'degc',
        ];
        $normalized = strtr($normalized, $replacements);

        // 単位記号以外の装飾（空白・括弧）を落として比較しやすくする
        $normalized = strtolower(preg_replace('/[\s\(\)\[\]]/u', '', $normalized) ?? $normalized);

        // 接頭辞付き単位（mV, kΩ, uF など）は基本単位へ寄せる。
        // 電圧が mV で来ても V の種別と対応づけられるようにする
        $baseUnits = ['ohm', 'degc', 'hz', 'v', 'a', 'f', 'w', 'h', 'b', 's', 'm'];
        foreach (['p', 'n', 'u', 'μ', 'm', 'k', 'meg', 'g', 't'] as $prefix) {
            foreach ($baseUnits as $base) {
                if ($normalized === $prefix.$base) {
                    return $base;
                }
            }
        }

        return $normalized;
    }

    /**
     * 目的: Spec Type Matchingのnormalized名称を担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $specType。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     *
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
            ->flatMap(fn ($value) => $this->normalizedInputCandidates((string) $value))
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
     *
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

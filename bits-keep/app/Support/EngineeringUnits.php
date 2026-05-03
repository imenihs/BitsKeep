<?php

namespace App\Support;

/**
 * 電気・情報量スペックで使う接頭語と単位表記の正規化規則を集約する。
 */
final class EngineeringUnits
{
    public const SI_PREFIX_FACTORS = [
        'Y' => 1e24,
        'Z' => 1e21,
        'E' => 1e18,
        'P' => 1e15,
        'T' => 1e12,
        'G' => 1e9,
        'M' => 1e6,
        'k' => 1e3,
        '' => 1.0,
        'm' => 1e-3,
        'u' => 1e-6,
        'µ' => 1e-6,
        'μ' => 1e-6,
        'n' => 1e-9,
        'p' => 1e-12,
        'f' => 1e-15,
    ];

    public const IEC_PREFIX_FACTORS = [
        'Ti' => 1099511627776,
        'Gi' => 1073741824,
        'Mi' => 1048576,
        'Ki' => 1024,
    ];

    public const PREFIX_FACTORS = self::SI_PREFIX_FACTORS + self::IEC_PREFIX_FACTORS;

    public const ENGINEERING_VALUE_PREFIX_FACTORS = self::PREFIX_FACTORS + ['K' => 1e3, 'meg' => 1e6];

    public const HUMAN_PREFIX_ORDER = ['Y', 'Z', 'E', 'P', 'T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'];

    public const UNIVERSAL_PREFIX_ORDER = ['Y', 'Z', 'E', 'P', 'Ti', 'Gi', 'Mi', 'Ki', 'T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'];

    public const BYTE_BIT_PREFIX_ORDER = ['T', 'G', 'M', 'k', ''];

    public const DISPLAY_PREFIXES = [
        ['prefix' => 'Y', 'factor' => 1e24],
        ['prefix' => 'Z', 'factor' => 1e21],
        ['prefix' => 'E', 'factor' => 1e18],
        ['prefix' => 'P', 'factor' => 1e15],
        ['prefix' => 'T', 'factor' => 1e12],
        ['prefix' => 'G', 'factor' => 1e9],
        ['prefix' => 'M', 'factor' => 1e6],
        ['prefix' => 'k', 'factor' => 1e3],
        ['prefix' => '', 'factor' => 1.0],
        ['prefix' => 'm', 'factor' => 1e-3],
        ['prefix' => 'u', 'factor' => 1e-6],
        ['prefix' => 'n', 'factor' => 1e-9],
        ['prefix' => 'p', 'factor' => 1e-12],
        ['prefix' => 'f', 'factor' => 1e-15],
    ];

    public const BINARY_IEC_PREFIXES = ['Ti', 'Gi', 'Mi', 'Ki'];

    public const DECIMAL_NON_FRACTIONAL_PREFIXES = ['T', 'G', 'M', 'k'];

    public const DECIMAL_FRACTIONAL_PREFIXES = ['m', 'u', 'n', 'p', 'f'];

    public const BYTE_BIT_ALLOWED_PREFIXES = ['T', 'G', 'M', 'k', '', 'Ti', 'Gi', 'Mi', 'Ki'];

    public const BYTE_BIT_BASE_UNITS = ['B', 'bit', 'bps'];

    public const UNIT_PREFIXES = ['Ti', 'Gi', 'Mi', 'Ki', 'Y', 'Z', 'E', 'P', 'T', 'G', 'M', 'k', 'm', 'u', 'n', 'p', 'f'];

    public const BASE_UNIT_SUFFIXES = ['ppm/℃', 'bit', 'bps', 'Ω', 'F', 'A', 'V', 'H', 's', 'Hz', 'W', 'J', 'C', 'B', 'm', 'g', 'K', 'N', 'Pa', 'bar', '%', '℃'];

    /**
     * 目的: Engineering Unitsの正規化unitlabelを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $unit。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     */
    public static function normalizeUnitLabel(string $unit): string
    {
        $normalized = trim(str_replace(['μ', 'µ', 'Ω'], ['u', 'u', 'Ω'], $unit));
        $normalized = preg_replace('/\bohms?\b/iu', 'Ω', $normalized) ?? $normalized;
        $normalized = preg_replace('/^meg(?=[A-Za-zΩ])/iu', 'M', $normalized) ?? $normalized;

        return preg_replace('/^K(?!i)(?=[A-Za-zΩ])/u', 'k', $normalized) ?? $normalized;
    }

    /**
     * 目的: Engineering Unitsの正規化接頭語を担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $prefix。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     */
    public static function normalizePrefix(mixed $prefix): string
    {
        $normalized = $prefix === null ? '' : trim((string) $prefix);
        if ($normalized === 'K') {
            return 'k';
        }
        if ($normalized === 'µ' || $normalized === 'μ') {
            return 'u';
        }
        if (preg_match('/^meg$/iu', $normalized)) {
            return 'meg';
        }

        return $normalized;
    }

    /**
     * 目的: Engineering Unitsの正規化接頭語一覧を担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $prefixes, $allowedPrefixes。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     * @return array<int, string>
     */
    public static function normalizePrefixList(mixed $prefixes, ?array $allowedPrefixes = null): array
    {
        if (! is_array($prefixes)) {
            return [];
        }

        $allowed = $allowedPrefixes === null
            ? array_keys(self::ENGINEERING_VALUE_PREFIX_FACTORS)
            : $allowedPrefixes;

        return array_values(array_unique(array_filter(
            array_map( fn ($prefix) => self::normalizePrefix($prefix), $prefixes), fn (string $prefix) => in_array($prefix, $allowed, true)
        )));
    }

    /**
     * 目的: Engineering Unitsのinvalidprefixesを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $prefixes, $allowedPrefixes。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     * @return array<int, string>
     */
    public static function invalidPrefixes(mixed $prefixes, ?array $allowedPrefixes = null): array
    {
        if (! is_array($prefixes)) {
            return [];
        }

        $allowed = $allowedPrefixes === null
            ? array_keys(self::ENGINEERING_VALUE_PREFIX_FACTORS)
            : $allowedPrefixes;

        return array_values(array_unique(array_filter(
            array_map( fn ($prefix) => self::normalizePrefix($prefix), $prefixes), fn (string $prefix) => ! in_array($prefix, $allowed, true)
        )));
    }

    /**
     * 目的: Engineering Unitsのisbytebitunitを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $unit。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function isByteBitUnit(string $unit): bool
    {
        return in_array(self::normalizeUnitLabel($unit), self::BYTE_BIT_BASE_UNITS, true);
    }

    /**
     * 目的: Engineering Unitsの正規化baseunit入力を担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $unit。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     * @return array{unit: string, prefix: string}
     */
    public static function normalizeBaseUnitInput(string $unit): array
    {
        $normalized = self::normalizeUnitLabel($unit);
        if ($normalized === '') {
            return ['unit' => '', 'prefix' => ''];
        }

        foreach (self::BASE_UNIT_SUFFIXES as $baseUnit) {
            if (! str_ends_with($normalized, $baseUnit) || $normalized === $baseUnit) {
                continue;
            }

            $prefix = substr($normalized, 0, -strlen($baseUnit));
            if (in_array($prefix, self::UNIT_PREFIXES, true)) {
                return ['unit' => $baseUnit, 'prefix' => $prefix];
            }
        }

        return ['unit' => $normalized, 'prefix' => ''];
    }
}

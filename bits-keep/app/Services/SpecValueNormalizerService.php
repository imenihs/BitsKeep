<?php

namespace App\Services;

use App\Models\SpecType;

class SpecValueNormalizerService
{
    private const PREFIX_FACTORS = [
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

    // PREFIX_FACTORS に大文字 K (非標準だが実務頻出) を追加した値パーサ専用テーブル
    private const ENGINEERING_VALUE_PREFIX_FACTORS = self::PREFIX_FACTORS + ['K' => 1e3];

    private const HUMAN_PREFIX_ORDER = ['Y', 'Z', 'E', 'P', 'T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'];

    private const RANGE_SPLIT_PATTERN = '/\s*(?:〜|~|～|to)\s*/iu';

    private const TRIPLE_SPLIT_PATTERN = '/\s*(?:\/|／|\|)\s*/u';

    private const VALID_PROFILES = ['typ', 'range', 'max_only', 'min_only', 'triple'];

    private const PROFILE_ALIASES = [
        'single' => 'typ',
        'range' => 'range',
        'max' => 'max_only',
        'min' => 'min_only',
        'triple' => 'triple',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeSpecPayload(?SpecType $specType, array $payload): array
    {
        $profile = $this->inferProfile($payload);
        $rawUnit = $this->normalizeUnitLabel((string) ($payload['unit'] ?? ''));
        $baseUnit = trim((string) ($specType?->base_unit ?? ''));

        $rawTyp = trim((string) ($payload['value_typ'] ?? ''));
        $rawMin = trim((string) ($payload['value_min'] ?? ''));
        $rawMax = trim((string) ($payload['value_max'] ?? ''));
        $rawValue = trim((string) ($payload['value'] ?? ''));

        if ($profile === 'typ' && $rawTyp === '') {
            $rawTyp = $rawValue;
        }
        if ($profile === 'max_only' && $rawMax === '') {
            $rawMax = $rawValue;
        }
        if ($profile === 'min_only' && $rawMin === '') {
            $rawMin = $rawValue;
        }
        if ($profile === 'range' && ($rawMin === '' || $rawMax === '') && $rawValue !== '' && $this->looksLikeRange($rawValue)) {
            [$parsedMin, $parsedMax] = $this->splitRange($rawValue);
            $rawMin = $rawMin !== '' ? $rawMin : $parsedMin;
            $rawMax = $rawMax !== '' ? $rawMax : $parsedMax;
        }
        if ($profile === 'triple' && ($rawMin === '' || $rawTyp === '' || $rawMax === '') && $rawValue !== '' && $this->looksLikeTriple($rawValue)) {
            [$parsedMin, $parsedTyp, $parsedMax] = $this->splitTriple($rawValue);
            $rawMin = $rawMin !== '' ? $rawMin : $parsedMin;
            $rawTyp = $rawTyp !== '' ? $rawTyp : $parsedTyp;
            $rawMax = $rawMax !== '' ? $rawMax : $parsedMax;
        }

        [$rawTyp, $rawMin, $rawMax, $rawUnit] = $this->extractInlineUnits($profile, $rawTyp, $rawMin, $rawMax, $rawUnit);

        $resolvedUnit = $this->normalizeUnitLabel($rawUnit);
        $normalizedUnit = $baseUnit !== '' ? $baseUnit : ($resolvedUnit !== '' ? $resolvedUnit : null);
        $factor = $this->resolveFactor($specType, $resolvedUnit, $baseUnit);

        return match ($profile) {
            'range' => $this->normalizeRangePayload($rawMin, $rawMax, $resolvedUnit, $normalizedUnit, $factor),
            'max_only' => $this->normalizeSinglePayload('max_only', $rawMax, $resolvedUnit, $normalizedUnit, $factor),
            'min_only' => $this->normalizeSinglePayload('min_only', $rawMin, $resolvedUnit, $normalizedUnit, $factor),
            'triple' => $this->normalizeTriplePayload($rawMin, $rawTyp, $rawMax, $resolvedUnit, $normalizedUnit, $factor),
            default => $this->normalizeSinglePayload('typ', $rawTyp, $resolvedUnit, $normalizedUnit, $factor),
        };
    }

    public function normalizeSearchBound(?SpecType $specType, mixed $value, ?string $unit): ?float
    {
        $parsedValue = $this->parseEngineeringNumber($value);
        if ($parsedValue === null) {
            return null;
        }

        $normalizedUnit = $this->normalizeUnitLabel((string) ($unit ?? ''));
        $baseUnit = trim((string) ($specType?->base_unit ?? ''));
        $factor = $this->resolveFactor($specType, $normalizedUnit, $baseUnit);

        return $parsedValue['value'] * $parsedValue['factor'] * $factor;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function inferProfile(array $payload): string
    {
        $explicit = strtolower(trim((string) ($payload['value_profile'] ?? $payload['profile'] ?? $payload['value_mode'] ?? '')));
        if (in_array($explicit, self::VALID_PROFILES, true)) {
            return $explicit;
        }
        if (array_key_exists($explicit, self::PROFILE_ALIASES)) {
            return self::PROFILE_ALIASES[$explicit];
        }

        $hasTyp = trim((string) ($payload['value_typ'] ?? $payload['typ'] ?? '')) !== '';
        $hasMin = trim((string) ($payload['value_min'] ?? $payload['min'] ?? '')) !== '';
        $hasMax = trim((string) ($payload['value_max'] ?? $payload['max'] ?? '')) !== '';

        if ($hasMin && $hasTyp && $hasMax) {
            return 'triple';
        }
        if ($hasMin && $hasMax) {
            return 'range';
        }
        if ($hasMax) {
            return 'max_only';
        }
        if ($hasMin) {
            return 'min_only';
        }

        $rawValue = trim((string) ($payload['value'] ?? ''));
        if ($rawValue !== '' && $this->looksLikeTriple($rawValue)) {
            return 'triple';
        }
        if ($rawValue !== '' && $this->looksLikeRange($rawValue)) {
            return 'range';
        }

        return 'typ';
    }

    private function normalizeSinglePayload(string $profile, string $rawValue, string $resolvedUnit, ?string $normalizedUnit, float $factor): array
    {
        $parsed = $this->parseEngineeringNumber($rawValue);
        if ($parsed === null) {
            return [
                'value_profile' => $profile,
                'value' => trim($rawValue),
                'unit' => $resolvedUnit,
                'value_mode' => 'single',
                'value_numeric' => null,
                'value_numeric_typ' => null,
                'value_numeric_min' => null,
                'value_numeric_max' => null,
                'normalized_unit' => $normalizedUnit,
            ];
        }

        $canonical = $parsed['value'] * $parsed['factor'] * $factor;
        [$displayValue, $displayUnit] = $this->humanizeSingle($canonical, $normalizedUnit, $resolvedUnit);

        return [
            'value_profile' => $profile,
            'value' => $displayValue,
            'unit' => $displayUnit,
            'value_mode' => 'single',
            'value_numeric' => $profile === 'typ' ? $this->formatDecimal($canonical, 10) : null,
            'value_numeric_typ' => $profile === 'typ' ? $this->formatDecimal($canonical, 15) : null,
            'value_numeric_min' => $profile === 'min_only' ? $this->formatDecimal($canonical, 15) : null,
            'value_numeric_max' => $profile === 'max_only' ? $this->formatDecimal($canonical, 15) : null,
            'normalized_unit' => $normalizedUnit,
        ];
    }

    private function normalizeRangePayload(string $rawMin, string $rawMax, string $resolvedUnit, ?string $normalizedUnit, float $factor): array
    {
        $min = $this->parseEngineeringNumber($rawMin);
        $max = $this->parseEngineeringNumber($rawMax);

        if ($min === null || $max === null) {
            return [
                'value_profile' => 'range',
                'value' => $this->buildRangeLabel($rawMin, $rawMax),
                'unit' => $resolvedUnit,
                'value_mode' => 'range',
                'value_numeric' => null,
                'value_numeric_typ' => null,
                'value_numeric_min' => null,
                'value_numeric_max' => null,
                'normalized_unit' => $normalizedUnit,
            ];
        }

        $canonicalMin = $min['value'] * $min['factor'] * $factor;
        $canonicalMax = $max['value'] * $max['factor'] * $factor;
        if ($canonicalMin > $canonicalMax) {
            [$canonicalMin, $canonicalMax] = [$canonicalMax, $canonicalMin];
        }

        [$displayMin, $displayMax, $displayUnit] = $this->humanizeRange($canonicalMin, $canonicalMax, $normalizedUnit, $resolvedUnit);

        return [
            'value_profile' => 'range',
            'value' => $this->buildRangeLabel($displayMin, $displayMax),
            'unit' => $displayUnit,
            'value_mode' => 'range',
            'value_numeric' => null,
            'value_numeric_typ' => null,
            'value_numeric_min' => $this->formatDecimal($canonicalMin, 15),
            'value_numeric_max' => $this->formatDecimal($canonicalMax, 15),
            'normalized_unit' => $normalizedUnit,
        ];
    }

    private function normalizeTriplePayload(string $rawMin, string $rawTyp, string $rawMax, string $resolvedUnit, ?string $normalizedUnit, float $factor): array
    {
        $min = $this->parseEngineeringNumber($rawMin);
        $typ = $this->parseEngineeringNumber($rawTyp);
        $max = $this->parseEngineeringNumber($rawMax);

        if ($min === null && $typ === null && $max === null) {
            return [
                'value_profile' => 'triple',
                'value' => $this->buildTripleLabel($rawMin, $rawTyp, $rawMax),
                'unit' => $resolvedUnit,
                'value_mode' => 'range',
                'value_numeric' => null,
                'value_numeric_typ' => null,
                'value_numeric_min' => null,
                'value_numeric_max' => null,
                'normalized_unit' => $normalizedUnit,
            ];
        }

        $canonicalMin = $min === null ? null : $min['value'] * $min['factor'] * $factor;
        $canonicalTyp = $typ === null ? null : $typ['value'] * $typ['factor'] * $factor;
        $canonicalMax = $max === null ? null : $max['value'] * $max['factor'] * $factor;
        if ($canonicalMin !== null && $canonicalMax !== null && $canonicalMin > $canonicalMax) {
            [$canonicalMin, $canonicalMax] = [$canonicalMax, $canonicalMin];
        }
        $humanized = $this->humanizeValues([
            'min' => $canonicalMin,
            'typ' => $canonicalTyp,
            'max' => $canonicalMax,
        ], $normalizedUnit, $resolvedUnit);

        return [
            'value_profile' => 'triple',
            'value' => $this->buildTripleLabel($humanized['min'], $humanized['typ'], $humanized['max']),
            'unit' => $humanized['unit'],
            'value_mode' => 'range',
            'value_numeric' => $canonicalTyp === null ? null : $this->formatDecimal($canonicalTyp, 10),
            'value_numeric_typ' => $canonicalTyp === null ? null : $this->formatDecimal($canonicalTyp, 15),
            'value_numeric_min' => $canonicalMin === null ? null : $this->formatDecimal($canonicalMin, 15),
            'value_numeric_max' => $canonicalMax === null ? null : $this->formatDecimal($canonicalMax, 15),
            'normalized_unit' => $normalizedUnit,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function extractInlineUnits(string $profile, string $rawTyp, string $rawMin, string $rawMax, string $rawUnit): array
    {
        if ($rawUnit !== '') {
            return [$rawTyp, $rawMin, $rawMax, $rawUnit];
        }

        if ($profile === 'typ') {
            [$rawTyp, $rawUnit] = $this->extractInlineUnit($rawTyp);

            return [$rawTyp, $rawMin, $rawMax, $rawUnit];
        }

        if ($profile === 'max_only') {
            [$rawMax, $rawUnit] = $this->extractInlineUnit($rawMax);

            return [$rawTyp, $rawMin, $rawMax, $rawUnit];
        }

        if ($profile === 'min_only') {
            [$rawMin, $rawUnit] = $this->extractInlineUnit($rawMin);

            return [$rawTyp, $rawMin, $rawMax, $rawUnit];
        }

        [$rawMin, $minUnit] = $this->extractInlineUnit($rawMin);
        [$rawTyp, $typUnit] = $this->extractInlineUnit($rawTyp);
        [$rawMax, $maxUnit] = $this->extractInlineUnit($rawMax);

        $candidateUnits = array_values(array_filter([$minUnit, $typUnit, $maxUnit]));
        $resolvedUnit = count(array_unique($candidateUnits)) === 1 ? $candidateUnits[0] : '';

        return [$rawTyp, $rawMin, $rawMax, $resolvedUnit];
    }

    private function resolveFactor(?SpecType $specType, string $unit, string $baseUnit): float
    {
        if ($unit === '') {
            return 1.0;
        }

        $units = null;
        if ($specType) {
            $units = $specType->relationLoaded('units')
                ? $specType->units
                : $specType->units()->get();
        }
        $exact = $units?->first(fn ($item) => $this->normalizeUnitLabel((string) $item->unit) === $unit);
        if ($exact && is_numeric($exact->factor)) {
            return (float) $exact->factor;
        }

        if ($baseUnit !== '' && $unit === $baseUnit) {
            return 1.0;
        }

        if ($baseUnit !== '' && str_ends_with($unit, $baseUnit)) {
            $prefix = substr($unit, 0, strlen($unit) - strlen($baseUnit));
            if (array_key_exists($prefix, self::PREFIX_FACTORS)) {
                return self::PREFIX_FACTORS[$prefix];
            }
        }

        return 1.0;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function humanizeSingle(float $canonicalValue, ?string $normalizedUnit, string $fallbackUnit): array
    {
        if (! $this->canHumanize($normalizedUnit)) {
            return [$this->formatDisplayNumber($canonicalValue), $fallbackUnit !== '' ? $fallbackUnit : (string) $normalizedUnit];
        }

        $prefix = $this->choosePrefix($canonicalValue);
        $factor = self::PREFIX_FACTORS[$prefix] ?? 1.0;

        return [$this->formatDisplayNumber($canonicalValue / $factor), $prefix.$normalizedUnit];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function humanizeRange(float $canonicalMin, float $canonicalMax, ?string $normalizedUnit, string $fallbackUnit): array
    {
        if (! $this->canHumanize($normalizedUnit)) {
            return [
                $this->formatDisplayNumber($canonicalMin),
                $this->formatDisplayNumber($canonicalMax),
                $fallbackUnit !== '' ? $fallbackUnit : (string) $normalizedUnit,
            ];
        }

        $target = max(abs($canonicalMin), abs($canonicalMax));
        $prefix = $this->choosePrefix($target);
        $factor = self::PREFIX_FACTORS[$prefix] ?? 1.0;

        return [
            $this->formatDisplayNumber($canonicalMin / $factor),
            $this->formatDisplayNumber($canonicalMax / $factor),
            $prefix.$normalizedUnit,
        ];
    }

    /**
     * @param  array{min: ?float, typ: ?float, max: ?float}  $values
     * @return array{min: string, typ: string, max: string, unit: string}
     */
    private function humanizeValues(array $values, ?string $normalizedUnit, string $fallbackUnit): array
    {
        $presentValues = array_values(array_filter($values, fn ($value) => $value !== null));
        $displayUnit = $fallbackUnit !== '' ? $fallbackUnit : (string) $normalizedUnit;

        if (! $presentValues) {
            return ['min' => '', 'typ' => '', 'max' => '', 'unit' => $displayUnit];
        }

        if (! $this->canHumanize($normalizedUnit)) {
            return [
                'min' => $values['min'] === null ? '' : $this->formatDisplayNumber($values['min']),
                'typ' => $values['typ'] === null ? '' : $this->formatDisplayNumber($values['typ']),
                'max' => $values['max'] === null ? '' : $this->formatDisplayNumber($values['max']),
                'unit' => $displayUnit,
            ];
        }

        $target = max(array_map(fn ($value) => abs($value), $presentValues));
        $prefix = $this->choosePrefix($target);
        $factor = self::PREFIX_FACTORS[$prefix] ?? 1.0;

        return [
            'min' => $values['min'] === null ? '' : $this->formatDisplayNumber($values['min'] / $factor),
            'typ' => $values['typ'] === null ? '' : $this->formatDisplayNumber($values['typ'] / $factor),
            'max' => $values['max'] === null ? '' : $this->formatDisplayNumber($values['max'] / $factor),
            'unit' => $prefix.$normalizedUnit,
        ];
    }

    private function choosePrefix(float $value): string
    {
        if ($value == 0.0) {
            return '';
        }

        $abs = abs($value);
        foreach (self::HUMAN_PREFIX_ORDER as $prefix) {
            $factor = self::PREFIX_FACTORS[$prefix];
            $scaled = $abs / $factor;
            if ($scaled >= 1 && $scaled < 1000) {
                return $prefix;
            }
        }

        return $abs >= 1 ? '' : 'f';
    }

    private function canHumanize(?string $normalizedUnit): bool
    {
        if (! $normalizedUnit) {
            return false;
        }

        return ! in_array($normalizedUnit, ['%', 'dB', '°C', '°F'], true)
            && preg_match('/^[A-Za-zΩΩ]+$/u', $normalizedUnit) === 1;
    }

    private function looksLikeRange(string $value): bool
    {
        return preg_match(self::RANGE_SPLIT_PATTERN, $value) === 1;
    }

    private function looksLikeTriple(string $value): bool
    {
        return preg_match(self::TRIPLE_SPLIT_PATTERN, $value) === 1 && ! $this->looksLikeRange($value);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRange(string $value): array
    {
        $parts = preg_split(self::RANGE_SPLIT_PATTERN, $value, 2) ?: [];
        if (count($parts) !== 2) {
            return [$value, ''];
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitTriple(string $value): array
    {
        $parts = preg_split(self::TRIPLE_SPLIT_PATTERN, $value, 3) ?: [];
        if (count($parts) !== 3) {
            return [$value, '', ''];
        }

        return [trim($parts[0]), trim($parts[1]), trim($parts[2])];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractInlineUnit(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return ['', ''];
        }

        if (preg_match('/^(.*?)([A-Za-zΩΩµμ%°\/][A-Za-z0-9ΩΩµμ%°\/]*)$/u', $trimmed, $matches) === 1) {
            $candidateValue = trim((string) ($matches[1] ?? ''));
            $candidateUnit = trim((string) ($matches[2] ?? ''));
            if ($candidateValue !== '' && $candidateUnit !== '') {
                return [$candidateValue, $this->normalizeUnitLabel($candidateUnit)];
            }
        }

        return [$trimmed, ''];
    }

    private function parseNumber(mixed $value): ?float
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['，', ',', '−', '–', '—'], ['', '', '-', '-', '-'], $normalized);
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        if (preg_match('/^[+-]?(?:(?:\d+(?:\.\d*)?)|(?:\.\d+))(?:e[+-]?\d+)?$/i', $normalized) !== 1) {
            return null;
        }

        $parsed = (float) $normalized;

        return is_finite($parsed) ? $parsed : null;
    }

    /**
     * @return array{value: float, factor: float}|null
     */
    private function parseEngineeringNumber(mixed $value): ?array
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['，', ',', '−', '–', '—'], ['', '', '-', '-', '-'], $normalized);
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        if (preg_match('/^([+-]?(?:(?:\d+(?:\.\d*)?)|(?:\.\d+))(?:e[+-]?\d+)?)([YZEPTGMkKmunpfµμ]?)$/u', $normalized, $matches) !== 1) {
            return null;
        }

        $parsed = (float) $matches[1];
        if (! is_finite($parsed)) {
            return null;
        }

        $prefix = (string) ($matches[2] ?? '');
        if (! array_key_exists($prefix, self::ENGINEERING_VALUE_PREFIX_FACTORS)) {
            return null;
        }

        return [
            'value' => $parsed,
            'factor' => self::ENGINEERING_VALUE_PREFIX_FACTORS[$prefix],
        ];
    }

    private function normalizeUnitLabel(string $unit): string
    {
        $normalized = trim(str_replace(['μ', 'µ', 'Ω'], ['u', 'u', 'Ω'], $unit));
        $normalized = preg_replace('/\bohms?\b/iu', 'Ω', $normalized) ?? $normalized;

        return preg_replace('/^K(?=[A-Za-zΩ])/u', 'k', $normalized) ?? $normalized;
    }

    private function formatDecimal(float $value, int $scale): string
    {
        $formatted = number_format($value, $scale, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    private function formatDisplayNumber(float $value): string
    {
        if ($value == 0.0) {
            return '0';
        }

        $abs = abs($value);
        if ($abs >= 1000 || $abs < 0.001) {
            $scientific = sprintf('%.6e', $value);
            [$mantissa, $exp] = explode('e', $scientific);
            $mantissa = rtrim(rtrim($mantissa, '0'), '.');
            $exp = (int) $exp;

            return $mantissa.'e'.($exp >= 0 ? '+' : '').$exp;
        }

        return $this->formatDecimal($value, 6);
    }

    private function buildRangeLabel(string $min, string $max): string
    {
        $left = trim($min);
        $right = trim($max);

        if ($left === '' && $right === '') {
            return '';
        }
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return "{$left} 〜 {$right}";
    }

    private function buildTripleLabel(string $min, string $typ, string $max): string
    {
        return implode(' / ', array_values(array_filter([trim($min), trim($typ), trim($max)], fn ($value) => $value !== '')));
    }
}

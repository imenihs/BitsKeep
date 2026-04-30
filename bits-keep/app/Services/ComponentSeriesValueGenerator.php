<?php

namespace App\Services;

use App\Models\ComponentSeriesValuePolicy;

class ComponentSeriesValueGenerator
{
    public const E_SERIES = [
        'E6' => [1.0, 1.5, 2.2, 3.3, 4.7, 6.8],
        'E12' => [1.0, 1.2, 1.5, 1.8, 2.2, 2.7, 3.3, 3.9, 4.7, 5.6, 6.8, 8.2],
        'E24' => [1.0, 1.1, 1.2, 1.3, 1.5, 1.6, 1.8, 2.0, 2.2, 2.4, 2.7, 3.0, 3.3, 3.6, 3.9, 4.3, 4.7, 5.1, 5.6, 6.2, 6.8, 7.5, 8.2, 9.1],
        'E48' => [1.00, 1.05, 1.10, 1.15, 1.21, 1.27, 1.33, 1.40, 1.47, 1.54, 1.62, 1.69, 1.78, 1.87, 1.96, 2.05, 2.15, 2.26, 2.37, 2.49, 2.61, 2.74, 2.87, 3.01, 3.16, 3.32, 3.48, 3.65, 3.83, 4.02, 4.22, 4.42, 4.64, 4.87, 5.11, 5.36, 5.62, 5.90, 6.19, 6.49, 6.81, 7.15, 7.50, 7.87, 8.25, 8.66, 9.09, 9.53],
        'E96' => [1.00, 1.02, 1.05, 1.07, 1.10, 1.13, 1.15, 1.18, 1.21, 1.24, 1.27, 1.30, 1.33, 1.37, 1.40, 1.43, 1.47, 1.50, 1.54, 1.58, 1.62, 1.65, 1.69, 1.74, 1.78, 1.82, 1.87, 1.91, 1.96, 2.00, 2.05, 2.10, 2.15, 2.21, 2.26, 2.32, 2.37, 2.43, 2.49, 2.55, 2.61, 2.67, 2.74, 2.80, 2.87, 2.94, 3.01, 3.09, 3.16, 3.24, 3.32, 3.40, 3.48, 3.57, 3.65, 3.74, 3.83, 3.92, 4.02, 4.12, 4.22, 4.32, 4.42, 4.53, 4.64, 4.75, 4.87, 4.99, 5.11, 5.23, 5.36, 5.49, 5.62, 5.76, 5.90, 6.04, 6.19, 6.34, 6.49, 6.65, 6.81, 6.98, 7.15, 7.32, 7.50, 7.68, 7.87, 8.06, 8.25, 8.45, 8.66, 8.87, 9.09, 9.31, 9.53, 9.76],
    ];

    private const PREFIX_FACTORS = [
        'Y' => 1e24,
        'Z' => 1e21,
        'E' => 1e18,
        'P' => 1e15,
        'Ti' => 1099511627776,
        'Gi' => 1073741824,
        'Mi' => 1048576,
        'Ki' => 1024,
        'T' => 1e12,
        'G' => 1e9,
        'M' => 1e6,
        'k' => 1e3,
        'K' => 1e3,
        '' => 1.0,
        'm' => 1e-3,
        'u' => 1e-6,
        'µ' => 1e-6,
        'μ' => 1e-6,
        'n' => 1e-9,
        'p' => 1e-12,
        'f' => 1e-15,
    ];

    private const DISPLAY_PREFIXES = [
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

    /**
     * @param  ComponentSeriesValuePolicy|array<string, mixed>  $policy
     * @return array<int, array<string, mixed>>
     */
    public function generate(ComponentSeriesValuePolicy|array $policy): array
    {
        $payload = $policy instanceof ComponentSeriesValuePolicy ? $policy->toArray() : $policy;
        $type = (string) ($payload['value_set_type'] ?? 'none');
        $unit = trim((string) ($payload['unit'] ?? ''));
        $roundingDigits = max(0, min(15, (int) ($payload['rounding_digits'] ?? 15)));
        $inputPrefixes = $this->prefixListFromPolicy($payload, 'input_prefixes');
        $displayPrefixes = $this->prefixListFromPolicy($payload, 'display_prefixes');
        $items = [];

        if (in_array($type, ['e_series', 'hybrid_series'], true)) {
            $primary = (string) ($payload['primary_series'] ?? 'E24');
            $this->appendESeries($items, $primary, $payload, $unit, 'primary_generated', $roundingDigits, $inputPrefixes, $displayPrefixes);
            if ($this->includeZero($payload)) {
                $this->appendNumeric($items, 0.0, $unit, 'included_zero', null, $roundingDigits, $displayPrefixes);
            }
        }

        if ($type === 'hybrid_series') {
            foreach ($this->normalizeStringList($payload['extra_series'] ?? []) as $seriesName) {
                $this->appendESeries($items, $seriesName, $payload, $unit, 'extra_series', $roundingDigits, $inputPrefixes, $displayPrefixes);
            }
            $this->appendValues($items, $payload['extra_values'] ?? [], $unit, 'manual', null, $roundingDigits, $inputPrefixes, $displayPrefixes);
        }

        if ($type === 'custom_list') {
            $this->appendValues($items, $payload['custom_values'] ?? [], $unit, 'custom_list', null, $roundingDigits, $inputPrefixes, $displayPrefixes);
        }

        if ($type === 'range_step') {
            $this->appendRange($items, $payload, $unit, $roundingDigits, $inputPrefixes, $displayPrefixes);
        }

        if (! in_array($type, ['none', ''], true)) {
            $this->applyExclusions($items, $payload['excluded_values'] ?? [], $unit, $roundingDigits, $inputPrefixes);
        }

        usort($items, fn (array $a, array $b) => [$a['value_numeric'] ?? INF, $a['value_text']] <=> [$b['value_numeric'] ?? INF, $b['value_text']]);

        return array_values(array_map(function (array $item, int $index) {
            $item['sort_order'] = ($index + 1) * 10;

            return $item;
        }, $items, array_keys($items)));
    }

    public function valueKey(float|string|null $value, ?string $unit = '', int $roundingDigits = 15): string
    {
        $numeric = is_numeric($value) ? (float) $value : $this->parseEngineeringNumber($value, null, (string) $unit);
        $numberPart = $numeric === null
            ? strtolower(trim((string) $value))
            : $this->normalizeDecimal(round($numeric, $roundingDigits));

        return strtolower(trim((string) $unit)).'|'.$numberPart;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $policy
     */
    private function appendESeries(array &$items, string $seriesName, array $policy, string $unit, string $origin, int $roundingDigits, ?array $inputPrefixes, ?array $displayPrefixes): void
    {
        $seriesName = strtoupper($seriesName);
        $baseValues = self::E_SERIES[$seriesName] ?? null;
        if ($baseValues === null) {
            return;
        }

        $decadeMin = (int) ($policy['decade_min'] ?? 0);
        $decadeMax = (int) ($policy['decade_max'] ?? 6);
        $rangeMin = $this->parseEngineeringNumber($policy['range_min'] ?? null, $inputPrefixes, $unit);
        $rangeMax = $this->parseEngineeringNumber($policy['range_max'] ?? null, $inputPrefixes, $unit);
        if ($decadeMin > $decadeMax) {
            [$decadeMin, $decadeMax] = [$decadeMax, $decadeMin];
        }
        if ($rangeMin !== null && $rangeMax !== null && $rangeMin > $rangeMax) {
            [$rangeMin, $rangeMax] = [$rangeMax, $rangeMin];
        }
        if ($rangeMin !== null && $rangeMax !== null && $rangeMin > 0 && $rangeMax > 0) {
            $decadeMin = (int) floor(log10($rangeMin));
            $decadeMax = (int) floor(log10($rangeMax));
        }

        for ($decade = $decadeMin; $decade <= $decadeMax; $decade++) {
            $factor = 10 ** $decade;
            foreach ($baseValues as $baseValue) {
                $value = $baseValue * $factor;
                if ($rangeMin !== null && $value < $rangeMin - max(abs($rangeMin), 1) * 1e-12) {
                    continue;
                }
                if ($rangeMax !== null && $value > $rangeMax + max(abs($rangeMax), 1) * 1e-12) {
                    continue;
                }
                $this->appendNumeric($items, $value, $unit, $origin, $seriesName, $roundingDigits, $displayPrefixes);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function appendValues(array &$items, mixed $values, string $unit, string $origin, ?string $sourceSeries, int $roundingDigits, ?array $inputPrefixes, ?array $displayPrefixes): void
    {
        foreach ($this->normalizeValueList($values) as $value) {
            $parsed = $this->parseEngineeringNumber($value, $inputPrefixes, $unit);
            if ($parsed === null) {
                continue;
            }
            $this->appendNumeric($items, $parsed, $unit, $origin, $sourceSeries, $roundingDigits, $displayPrefixes);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $policy
     */
    private function appendRange(array &$items, array $policy, string $unit, int $roundingDigits, ?array $inputPrefixes, ?array $displayPrefixes): void
    {
        $min = $this->parseEngineeringNumber($policy['range_min'] ?? null, $inputPrefixes, $unit);
        $max = $this->parseEngineeringNumber($policy['range_max'] ?? null, $inputPrefixes, $unit);
        $step = $this->parseEngineeringNumber($policy['range_step'] ?? null, $inputPrefixes, $unit);
        if ($min === null || $max === null || $step === null || $step <= 0) {
            return;
        }
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        $guard = 0;
        for ($value = $min; $value <= $max + ($step / 1000); $value += $step) {
            $this->appendNumeric($items, $value, $unit, 'range_step', null, $roundingDigits, $displayPrefixes);
            $guard++;
            if ($guard >= 1000) {
                break;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function appendNumeric(array &$items, float $value, string $unit, string $origin, ?string $sourceSeries, int $roundingDigits, ?array $displayPrefixes = null): void
    {
        $rounded = round($value, $roundingDigits);
        $key = $this->valueKey($rounded, $unit, $roundingDigits);
        if (isset($items[$key])) {
            if ($items[$key]['origin'] !== 'primary_generated' && $origin === 'primary_generated') {
                $items[$key]['origin'] = $origin;
                $items[$key]['source_series'] = $sourceSeries;
            }

            return;
        }

        $items[$key] = [
            'value_text' => $this->formatValueText($rounded, $unit, $displayPrefixes),
            'value_key' => $key,
            'value_numeric' => $rounded,
            'unit' => $unit,
            'origin' => $origin,
            'source_series' => $sourceSeries,
            'is_enabled' => true,
            'is_stocked' => false,
            'note' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function applyExclusions(array &$items, mixed $excludedValues, string $unit, int $roundingDigits, ?array $inputPrefixes): void
    {
        foreach ($this->normalizeValueList($excludedValues) as $excluded) {
            $numeric = $this->parseEngineeringNumber($excluded, $inputPrefixes, $unit);
            if ($numeric === null) {
                continue;
            }
            $key = $this->valueKey($numeric, $unit, $roundingDigits);
            if (isset($items[$key])) {
                $items[$key]['is_enabled'] = false;
                $items[$key]['origin'] = 'excluded';
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $values): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            is_array($values) ? $values : preg_split('/[\s,]+/', (string) $values)
        )));
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeValueList(mixed $values): array
    {
        if (is_array($values)) {
            return array_values($values);
        }

        return array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            preg_split('/[\s,;]+/', (string) $values)
        ), fn ($value) => $value !== ''));
    }

    public function parseEngineeringNumber(mixed $value, ?array $allowedPrefixes = null, string $unit = ''): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = $this->normalizeEngineeringText($value, $unit);
        if ($text === '') {
            return null;
        }

        if (! preg_match('/^([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?)(.*)$/u', $text, $matches)) {
            return null;
        }

        $suffix = (string) ($matches[2] ?? '');
        $prefix = $this->resolvePrefixFromSuffix($suffix, $allowedPrefixes, trim($unit) === '');
        if ($prefix === null) {
            return null;
        }
        $factor = self::PREFIX_FACTORS[$prefix] ?? 1.0;

        return (float) $matches[1] * $factor;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function includeZero(array $payload): bool
    {
        $settings = $payload['generation_settings'] ?? [];
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) && filter_var($settings['include_zero'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function formatValueText(float $value, string $unit, ?array $displayPrefixes = null): string
    {
        if ($unit === '') {
            return $this->normalizeDecimal($value);
        }
        if ((float) $value === 0.0) {
            return '0'.$unit;
        }

        $abs = abs($value);
        $prefixes = $this->displayPrefixRows($displayPrefixes);
        $fallback = $prefixes[array_key_last($prefixes)] ?? ['prefix' => '', 'factor' => 1.0];
        foreach ($prefixes as $prefix) {
            if ($abs >= $prefix['factor'] || $prefix['prefix'] === $fallback['prefix']) {
                $scaled = $value / $prefix['factor'];
                if (abs($scaled) >= 1 || $prefix['prefix'] === $fallback['prefix']) {
                    return $this->normalizeDecimal($scaled).$prefix['prefix'].$unit;
                }
            }
        }

        return $this->normalizeDecimal($value).$unit;
    }

    private function normalizeDecimal(float $value): string
    {
        if (abs($value) >= 0.001) {
            $rounded = round($value, 12);
            if (abs($rounded - $value) <= max(abs($value), 1.0) * 1e-12) {
                $value = $rounded;
            }

            $text = json_encode($value);
            if (is_string($text) && ! str_contains(strtolower($text), 'e')) {
                if (str_contains($text, '.')) {
                    $text = rtrim(rtrim($text, '0'), '.');
                }

                return $text === '-0' || $text === '' ? '0' : $text;
            }
        }

        $text = rtrim(rtrim(sprintf('%.15F', $value), '0'), '.');

        return $text === '-0' || $text === '' ? '0' : $text;
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<int, string>|null
     */
    private function prefixListFromPolicy(array $policy, string $key): ?array
    {
        $settings = $policy['generation_settings'] ?? [];
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $this->normalizePrefixList($settings[$key] ?? null) : null;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizePrefixList(mixed $prefixes): ?array
    {
        if (! is_array($prefixes) || $prefixes === []) {
            return null;
        }

        $normalized = [];
        foreach ($prefixes as $prefix) {
            $token = $this->normalizePrefixToken($prefix);
            if (array_key_exists($token, self::PREFIX_FACTORS) && ! in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    private function normalizePrefixToken(mixed $prefix): string
    {
        $token = trim((string) ($prefix ?? ''));
        if ($token === 'K') {
            return 'k';
        }
        if ($token === 'µ' || $token === 'μ') {
            return 'u';
        }

        return $token;
    }

    private function normalizeEngineeringText(mixed $value, string $unit = ''): string
    {
        $text = str_replace([',', ' ', '　'], '', trim((string) $value));
        $text = str_replace(['µ', 'μ', 'Ω'], ['u', 'u', 'Ω'], $text);
        $normalizedUnit = str_replace(['µ', 'μ', 'Ω'], ['u', 'u', 'Ω'], trim($unit));

        if ($normalizedUnit !== '' && str_ends_with($text, $normalizedUnit)) {
            return substr($text, 0, -strlen($normalizedUnit));
        }

        return $text;
    }

    /**
     * @return string|null
     */
    private function resolvePrefixFromSuffix(string $suffix, ?array $allowedPrefixes, bool $allowTrailingUnit = false): ?string
    {
        $allowed = $this->normalizePrefixList($allowedPrefixes);
        $candidates = $allowed ?? array_keys(self::PREFIX_FACTORS);
        usort($candidates, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($candidates as $prefix) {
            if ($prefix === '') {
                continue;
            }
            if ($suffix === $prefix || ($allowTrailingUnit && str_starts_with($suffix, $prefix))) {
                return $prefix;
            }
        }

        if ($suffix === '' || ($allowTrailingUnit && ($allowed === null || in_array('', $allowed, true)))) {
            return '';
        }

        return null;
    }

    /**
     * @return array<int, array{prefix: string, factor: float|int}>
     */
    private function displayPrefixRows(?array $displayPrefixes): array
    {
        $prefixes = $this->normalizePrefixList($displayPrefixes);
        if ($prefixes === null) {
            return self::DISPLAY_PREFIXES;
        }

        $rows = array_values(array_filter(
            self::DISPLAY_PREFIXES,
            fn (array $row) => in_array($row['prefix'], $prefixes, true)
        ));

        return $rows !== [] ? $rows : self::DISPLAY_PREFIXES;
    }
}

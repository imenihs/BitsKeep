<?php

namespace App\Services;

use App\Models\Component;

class NetworkSearchService
{
    private const RETURN_LIMIT = 80;

    private const POOL_LIMIT = 32;

    private const COUNT4_POOL_LIMIT = 12;

    private const MAX_EVALUATIONS = 60000;

    private const E_SERIES = [
        'E6' => [1.0, 1.5, 2.2, 3.3, 4.7, 6.8],
        'E12' => [1.0, 1.2, 1.5, 1.8, 2.2, 2.7, 3.3, 3.9, 4.7, 5.6, 6.8, 8.2],
        'E24' => [1.0, 1.1, 1.2, 1.3, 1.5, 1.6, 1.8, 2.0, 2.2, 2.4, 2.7, 3.0, 3.3, 3.6, 3.9, 4.3, 4.7, 5.1, 5.6, 6.2, 6.8, 7.5, 8.2, 9.1],
        'E48' => [1.00, 1.05, 1.10, 1.15, 1.21, 1.27, 1.33, 1.40, 1.47, 1.54, 1.62, 1.69, 1.78, 1.87, 1.96, 2.05, 2.15, 2.26, 2.37, 2.49, 2.61, 2.74, 2.87, 3.01, 3.16, 3.32, 3.48, 3.65, 3.83, 4.02, 4.22, 4.42, 4.64, 4.87, 5.11, 5.36, 5.62, 5.90, 6.19, 6.49, 6.81, 7.15, 7.50, 7.87, 8.25, 8.66, 9.09, 9.53],
        'E96' => [1.00, 1.02, 1.05, 1.07, 1.10, 1.13, 1.15, 1.18, 1.21, 1.24, 1.27, 1.30, 1.33, 1.37, 1.40, 1.43, 1.47, 1.50, 1.54, 1.58, 1.62, 1.65, 1.69, 1.74, 1.78, 1.82, 1.87, 1.91, 1.96, 2.00, 2.05, 2.10, 2.15, 2.21, 2.26, 2.32, 2.37, 2.43, 2.49, 2.55, 2.61, 2.67, 2.74, 2.80, 2.87, 2.94, 3.01, 3.09, 3.16, 3.24, 3.32, 3.40, 3.48, 3.57, 3.65, 3.74, 3.83, 3.92, 4.02, 4.12, 4.22, 4.32, 4.42, 4.53, 4.64, 4.75, 4.87, 4.99, 5.11, 5.23, 5.36, 5.49, 5.62, 5.76, 5.90, 6.04, 6.19, 6.34, 6.49, 6.65, 6.81, 6.98, 7.15, 7.32, 7.50, 7.68, 7.87, 8.06, 8.25, 8.45, 8.66, 8.87, 9.09, 9.31, 9.53, 9.76],
    ];

    private const DECADES_R = [0.01, 0.1, 1, 10, 100, 1000, 10000, 100000, 1000000, 10000000];

    private const DECADES_C = [1e-12, 1e-11, 1e-10, 1e-9, 1e-8, 1e-7, 1e-6, 1e-5, 1e-4, 1e-3];

    public function search(array $params): array
    {
        $started = microtime(true);
        $partType = $params['part_type'] ?? 'R';
        $target = (float) $params['target'];
        $tolPct = (float) ($params['tolerance_pct'] ?? 5.0);
        $elementTolPct = max(0.0, min(100.0, (float) ($params['element_tolerance_pct'] ?? 0.0)));
        $minElements = max(1, (int) ($params['min_elements'] ?? 1));
        $maxElements = min(4, (int) ($params['max_elements'] ?? 3));
        $maxElements = max($minElements, $maxElements);
        $circuitTypes = array_values(array_unique($params['circuit_types'] ?? ['series', 'parallel']));

        if ($partType === 'divider') {
            $circuitTypes = ['divider'];
            $minElements = 2;
            $maxElements = 2;
        }

        $rawValues = $this->buildValueSet(
            $params['series'] ?? 'E24',
            $params['custom_values'] ?? [],
            $partType,
            ! empty($params['inventory_only'])
        );
        $candidates = [];
        $maxPoolCount = 0;
        $evaluationCount = 0;
        $evaluationLimited = false;

        if ($partType === 'divider') {
            $maxPoolCount = count($rawValues);
            $this->searchDivider($rawValues, $params, $candidates, $evaluationCount, $evaluationLimited);
        } else {
            for ($count = $minElements; $count <= $maxElements; $count++) {
                $values = $this->valuesForElementCount($rawValues, $target, $partType, $count);
                $maxPoolCount = max($maxPoolCount, count($values));
                $comboSource = $this->comboSource($values, $target, $partType, $count, $circuitTypes);
                foreach ($comboSource as $combo) {
                    foreach ($this->evaluateCombo($combo, $target, $partType, $circuitTypes, $tolPct, $elementTolPct, $evaluationCount, $evaluationLimited) as $candidate) {
                        if (! $this->hasSufficientInventory($candidate['parts'])) {
                            continue;
                        }
                        $candidates[] = $candidate;
                    }
                    if ($evaluationLimited) {
                        break 2;
                    }
                }
            }
        }

        usort($candidates, fn ($a, $b) => [
            $a['rss_max_target_deviation_pct'] ?? $a['max_target_deviation_pct'] ?? $a['error_pct'],
            $a['error_pct'],
            $a['elements_count'],
            $a['parts_total_value'],
        ] <=> [
            $b['rss_max_target_deviation_pct'] ?? $b['max_target_deviation_pct'] ?? $b['error_pct'],
            $b['error_pct'],
            $b['elements_count'],
            $b['parts_total_value'],
        ]);
        $unique = $this->uniqueCandidates($candidates);

        return [
            'target_value' => $target,
            'target_display' => $partType === 'divider' ? $this->dividerTargetDisplay($target, $params) : $this->formatValue($target, $partType),
            'part_type' => $partType,
            'input_voltage' => $params['input_voltage'] ?? null,
            'output_voltage' => $params['output_voltage'] ?? null,
            'candidate_pool_count' => $maxPoolCount,
            'raw_pool_count' => count($rawValues),
            'return_limit' => self::RETURN_LIMIT,
            'candidates' => array_slice($unique, 0, self::RETURN_LIMIT),
            'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
            'truncated' => count($unique) > self::RETURN_LIMIT || $evaluationLimited,
            'evaluation_limited' => $evaluationLimited,
        ];
    }

    private function evaluateCombo(array $combo, float $target, string $partType, array $allowed, float $tolPct, float $elementTolPct, int &$evaluationCount, bool &$evaluationLimited): array
    {
        $count = count($combo);
        $patterns = [];

        if (in_array('series', $allowed, true)) {
            $patterns[] = $this->pattern(
                'series',
                $partType === 'C' ? '容量直列' : '抵抗直列',
                fn ($v) => $this->seriesEquivalent($v, $partType),
                fn ($v) => implode(' + ', array_map(fn ($i) => $i['label'], $v))
            );
        }
        if (in_array('parallel', $allowed, true)) {
            $patterns[] = $this->pattern(
                'parallel',
                $partType === 'C' ? '容量並列' : '抵抗並列',
                fn ($v) => $this->parallelEquivalent($v, $partType),
                fn ($v) => implode(' ∥ ', array_map(fn ($i) => $i['label'], $v))
            );
        }
        if (in_array('mixed', $allowed, true) && $count >= 3) {
            $patterns = array_merge($patterns, $this->mixedPatterns($combo, $partType));
        }

        $results = [];
        foreach ($patterns as $pattern) {
            if ($evaluationCount >= self::MAX_EVALUATIONS) {
                $evaluationLimited = true;
                break;
            }
            $evaluationCount++;

            $actual = $pattern['eval']($combo);
            if ($actual <= 0 || ! is_finite($actual)) {
                continue;
            }
            $errPct = abs($actual - $target) / $target * 100;
            if ($errPct > $tolPct) {
                continue;
            }
            $results[] = $this->candidatePayload($combo, $actual, $target, $errPct, $partType, $pattern, $elementTolPct);
        }

        return $results;
    }

    private function mixedPatterns(array $combo, string $partType): array
    {
        $p = [];

        if (count($combo) === 3) {
            foreach ([[0, 1, 2], [0, 2, 1], [1, 2, 0]] as [$a, $b, $c]) {
                $p[] = $this->pattern(
                    'mixed',
                    '直列枝を並列',
                    fn ($v) => $this->parallelEquivalent([$this->node($this->seriesEquivalent([$v[$a], $v[$b]], $partType)), $v[$c]], $partType),
                    fn ($v) => "({$v[$a]['label']} + {$v[$b]['label']}) ∥ {$v[$c]['label']}"
                );
                $p[] = $this->pattern(
                    'mixed',
                    '並列枝を直列',
                    fn ($v) => $this->seriesEquivalent([$this->node($this->parallelEquivalent([$v[$a], $v[$b]], $partType)), $v[$c]], $partType),
                    fn ($v) => "({$v[$a]['label']} ∥ {$v[$b]['label']}) + {$v[$c]['label']}"
                );
            }
        }

        if (count($combo) === 4) {
            foreach ([[0, 1, 2, 3], [0, 2, 1, 3], [0, 3, 1, 2]] as [$a, $b, $c, $d]) {
                $p[] = $this->pattern(
                    'mixed',
                    '直列2枝を並列',
                    fn ($v) => $this->parallelEquivalent([
                        $this->node($this->seriesEquivalent([$v[$a], $v[$b]], $partType)),
                        $this->node($this->seriesEquivalent([$v[$c], $v[$d]], $partType)),
                    ], $partType),
                    fn ($v) => "({$v[$a]['label']} + {$v[$b]['label']}) ∥ ({$v[$c]['label']} + {$v[$d]['label']})"
                );
                $p[] = $this->pattern(
                    'mixed',
                    '並列2枝を直列',
                    fn ($v) => $this->seriesEquivalent([
                        $this->node($this->parallelEquivalent([$v[$a], $v[$b]], $partType)),
                        $this->node($this->parallelEquivalent([$v[$c], $v[$d]], $partType)),
                    ], $partType),
                    fn ($v) => "({$v[$a]['label']} ∥ {$v[$b]['label']}) + ({$v[$c]['label']} ∥ {$v[$d]['label']})"
                );
            }
            foreach ([[0, 1, 2, 3], [0, 2, 1, 3], [1, 2, 0, 3], [0, 3, 1, 2]] as [$a, $b, $c, $d]) {
                $p[] = $this->pattern(
                    'mixed',
                    '直列枝と単体の並列を直列',
                    fn ($v) => $this->seriesEquivalent([
                        $this->node($this->parallelEquivalent([$this->node($this->seriesEquivalent([$v[$a], $v[$b]], $partType)), $v[$c]], $partType)),
                        $v[$d],
                    ], $partType),
                    fn ($v) => '(('.$v[$a]['label'].' + '.$v[$b]['label'].') ∥ '.$v[$c]['label'].') + '.$v[$d]['label']
                );
                $p[] = $this->pattern(
                    'mixed',
                    '並列枝と単体の直列を並列',
                    fn ($v) => $this->parallelEquivalent([
                        $this->node($this->seriesEquivalent([$this->node($this->parallelEquivalent([$v[$a], $v[$b]], $partType)), $v[$c]], $partType)),
                        $v[$d],
                    ], $partType),
                    fn ($v) => '(('.$v[$a]['label'].' ∥ '.$v[$b]['label'].') + '.$v[$c]['label'].') ∥ '.$v[$d]['label']
                );
            }
        }

        return $p;
    }

    private function searchDivider(array $values, array $params, array &$candidates, int &$evaluationCount, bool &$evaluationLimited): void
    {
        $ratio = (float) $params['target'];
        $tolPct = (float) ($params['tolerance_pct'] ?? 5.0);
        $totalMin = (float) ($params['total_res_min'] ?? 0);
        $totalMax = array_key_exists('total_res_max', $params) && $params['total_res_max'] !== null
            ? (float) $params['total_res_max']
            : INF;

        foreach ($values as $r1) {
            foreach ($values as $r2) {
                $total = $r1['value'] + $r2['value'];
                if ($total < $totalMin || $total > $totalMax) {
                    continue;
                }
                if ($evaluationCount >= self::MAX_EVALUATIONS) {
                    $evaluationLimited = true;
                    return;
                }
                $evaluationCount++;

                $loaded = $this->dividerLoadedResult((float) $r1['value'], (float) $r2['value'], $params);
                $actual = $loaded['ratio'];
                if ($actual <= 0 || ! is_finite($actual)) {
                    continue;
                }
                $errPct = abs($actual - $ratio) / $ratio * 100;
                if ($errPct > $tolPct) {
                    continue;
                }
                $candidate = [
                    'expression' => "R1上側={$r1['label']} / R2下側={$r2['label']}",
                    'elements_count' => 2,
                    'error_pct' => round($errPct, 4),
                    'error_display' => $this->formatPercent($errPct),
                    'error_abs_value' => abs($actual - $ratio),
                    'error_abs_display' => $this->formatRatio(abs($actual - $ratio)),
                    'circuit_type' => 'divider',
                    'topology_label' => '分圧回路',
                    'actual_value' => $actual,
                    'actual_display' => $this->formatRatio($actual),
                    'target_display' => $this->dividerTargetDisplay($ratio, $params),
                    'total_value' => $total,
                    'total_display' => $this->formatValue($total, 'R'),
                    'load_type' => $loaded['load_type'],
                    'load_display' => $loaded['load_display'],
                    ...$this->dividerElectricalCandidateFields($loaded),
                    ...$this->dividerTolerancePayload((float) $r1['value'], (float) $r2['value'], $ratio, $params, $actual),
                    'parts_total_value' => $total,
                    'parts' => [
                        $this->partPayload($r1, 'R1上側'),
                        $this->partPayload($r2, 'R2下側'),
                    ],
                    'from_inventory' => ! empty($r1['component_id']) || ! empty($r2['component_id']),
                ];
                if (isset($params['input_voltage'])) {
                    $inputVoltage = (float) $params['input_voltage'];
                    $targetVoltage = $ratio * $inputVoltage;
                    $actualVoltage = $loaded['output_voltage'] ?? ($actual * $inputVoltage);
                    $candidate = [
                        ...$candidate,
                        'input_voltage' => $inputVoltage,
                        'input_voltage_display' => $this->formatVoltage($inputVoltage),
                        'target_output_voltage' => $targetVoltage,
                        'target_output_display' => $this->formatVoltage($targetVoltage),
                        'actual_output_voltage' => $actualVoltage,
                        'actual_output_display' => $this->formatVoltage($actualVoltage),
                        'output_error_value' => $actualVoltage - $targetVoltage,
                        'output_error_display' => $this->formatSignedVoltage($actualVoltage - $targetVoltage),
                    ];
                }
                if (! $this->hasSufficientInventory($candidate['parts'])) {
                    continue;
                }
                $candidates[] = $candidate;
            }
        }
    }

    private function buildValueSet(string $series, array $custom, string $partType, bool $inventoryOnly): array
    {
        $values = $inventoryOnly
            ? $this->buildInventoryValueSet($partType)
            : ($series === 'custom' && $custom !== []
                ? array_map(fn ($value) => ['value' => (float) $value, 'label' => $this->formatValue((float) $value, $partType), 'source' => 'custom'], $custom)
                : ($series === 'custom' ? [] : $this->buildESeriesValueSet($series, $partType)));

        return collect($values)
            ->filter(fn ($item) => is_finite((float) ($item['value'] ?? 0)) && (float) ($item['value'] ?? 0) > 0)
            ->map(fn ($item) => [
                ...$item,
                'value' => (float) $item['value'],
                'label' => $item['label'] ?? $this->formatValue((float) $item['value'], $partType),
            ])
            ->unique(fn ($item) => $this->valueKey($item['value']).'|'.($item['component_id'] ?? '').'|'.($item['label'] ?? ''))
            ->sortBy('value')
            ->values()
            ->all();
    }

    private function buildESeriesValueSet(string $series, string $partType): array
    {
        $values = [];
        $multipliers = self::E_SERIES[$series] ?? self::E_SERIES['E24'];
        $decades = $partType === 'C' ? self::DECADES_C : self::DECADES_R;
        foreach ($decades as $decade) {
            foreach ($multipliers as $multiplier) {
                $value = round($multiplier * $decade, 15);
                $values[] = [
                    'value' => $value,
                    'label' => $this->formatValue($value, $partType),
                    'source' => $series,
                ];
            }
        }

        return $values;
    }

    private function buildInventoryValueSet(string $partType): array
    {
        if ($partType === 'divider') {
            $partType = 'R';
        }
        $categoryKeyword = $partType === 'C' ? 'コンデンサ' : '抵抗';

        return Component::query()
            ->whereHas('categories', fn ($q) => $q->where('name', 'like', "%{$categoryKeyword}%"))
            ->whereHas('inventoryBlocks', fn ($q) => $q->where('quantity', '>', 0))
            ->with(['specs.specType', 'inventoryBlocks'])
            ->get()
            ->flatMap(function (Component $component) use ($partType) {
                $spec = $this->findValueSpec($component, $partType);
                $value = (float) ($spec?->value_numeric_typ ?? $spec?->value_numeric ?? 0);
                if ($value <= 0) {
                    return [];
                }
                $stock = (int) $component->inventoryBlocks->sum('quantity');
                $name = $component->common_name ?: $component->part_number;

                return [[
                    'value' => $value,
                    'label' => "{$name} ({$this->formatValue($value, $partType)})",
                    'component_id' => $component->id,
                    'stock_quantity' => $stock,
                    'source' => 'inventory',
                ]];
            })
            ->values()
            ->all();
    }

    private function findValueSpec(Component $component, string $partType)
    {
        $unitNeedles = $partType === 'C'
            ? ['f']
            : ['ω', 'ohm'];
        $nameNeedles = $partType === 'C'
            ? ['容量', '静電容量', 'capacitance', 'cap']
            : ['抵抗', '抵抗値', 'resistance'];

        $scored = $component->specs
            ->filter(fn ($spec) => ($spec->value_numeric_typ ?? $spec->value_numeric ?? null) !== null)
            ->map(function ($spec) use ($unitNeedles, $nameNeedles) {
                $unit = strtolower(str_replace(['Ω', 'Ω'], ['ω', 'ω'], (string) ($spec->normalized_unit ?? $spec->unit ?? $spec->specType?->base_unit ?? '')));
                $name = strtolower((string) ($spec->display_name ?? $spec->specType?->name_ja ?? $spec->specType?->name ?? ''));
                $unitMatched = false;
                $nameMatched = false;
                $score = 0;
                foreach ($unitNeedles as $needle) {
                    if (str_contains($unit, $needle)) {
                        $score += 10;
                        $unitMatched = true;
                    }
                }
                foreach ($nameNeedles as $needle) {
                    if (str_contains($name, strtolower($needle))) {
                        $score += 5;
                        $nameMatched = true;
                    }
                }
                if (! $unitMatched && preg_match('/温度|temperature|tcr|係数|ppm|許容|tolerance/u', $name.$unit)) {
                    $score = 0;
                    $nameMatched = false;
                }

                return ['spec' => $spec, 'score' => $score, 'unitMatched' => $unitMatched, 'nameMatched' => $nameMatched];
            })
            ->sortByDesc('score')
            ->first();

        return ($scored && $scored['score'] > 0 && ($scored['unitMatched'] || $scored['nameMatched'])) ? $scored['spec'] : null;
    }

    private function valuesForElementCount(array $values, float $target, string $partType, int $count): array
    {
        if ($count <= 2 || count($values) <= self::POOL_LIMIT) {
            return $values;
        }

        return $this->limitPool($values, $target, $partType, $count >= 4 ? self::COUNT4_POOL_LIMIT : self::POOL_LIMIT, $count);
    }

    private function limitPool(array $values, float $target, string $partType, int $limit, int $maxElements): array
    {
        if (count($values) <= $limit) {
            return $values;
        }

        $anchors = [$target];
        for ($i = 2; $i <= max(2, $maxElements); $i++) {
            $anchors[] = $target / $i;
            $anchors[] = $target * $i;
        }
        if ($partType === 'R') {
            $anchors[] = $target * 10;
            $anchors[] = $target / 10;
        }

        usort($values, fn ($a, $b) => $this->distanceToAnchors((float) $a['value'], $anchors) <=> $this->distanceToAnchors((float) $b['value'], $anchors));
        $limited = array_slice($values, 0, $limit);
        usort($limited, fn ($a, $b) => $a['value'] <=> $b['value']);

        return $limited;
    }

    private function distanceToAnchors(float $value, array $anchors): float
    {
        $distances = array_map(function ($anchor) use ($value) {
            if ($anchor <= 0 || $value <= 0) {
                return INF;
            }

            return abs(log10($value) - log10((float) $anchor));
        }, $anchors);

        return min($distances);
    }

    private function combinationsWithReplacement(array $values, int $length, int $start = 0): iterable
    {
        if ($length === 0) {
            yield [];

            return;
        }
        for ($i = $start; $i < count($values); $i++) {
            foreach ($this->combinationsWithReplacement($values, $length - 1, $i) as $suffix) {
                yield array_merge([$values[$i]], $suffix);
            }
        }
    }

    private function comboSource(array $values, float $target, string $partType, int $count, array $circuitTypes): iterable
    {
        if ($count === 2 && $this->shouldUseTargetedPairSearch($values, $circuitTypes)) {
            yield from $this->targetedTwoElementCombos($values, $target, $partType, $circuitTypes);

            return;
        }

        if (in_array('series', $circuitTypes, true) && count($circuitTypes) === 1) {
            yield from $this->targetedSeriesCombos($values, $target, $partType, $count);

            return;
        }

        yield from $this->combinationsWithReplacement($values, $count);
    }

    private function shouldUseTargetedPairSearch(array $values, array $circuitTypes): bool
    {
        return count($values) > self::POOL_LIMIT * 10
            && array_intersect($circuitTypes, ['series', 'parallel']) !== [];
    }

    private function targetedTwoElementCombos(array $values, float $target, string $partType, array $circuitTypes): iterable
    {
        $allowed = array_values(array_intersect($circuitTypes, ['series', 'parallel']));
        if ($allowed === []) {
            yield from $this->combinationsWithReplacement($values, 2);

            return;
        }

        $seen = [];
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            $first = (float) $values[$i]['value'];
            foreach ($allowed as $circuitType) {
                $needed = $this->neededSecondValue($first, $target, $partType, $circuitType);
                if ($needed === null) {
                    continue;
                }
                foreach ($this->nearestValueWindow($values, $needed, $i) as $second) {
                    $key = $this->valueKey((float) $values[$i]['value']).'|'.$this->valueKey((float) $second['value']);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    yield [$values[$i], $second];
                }
            }
        }
    }

    private function neededSecondValue(float $first, float $target, string $partType, string $circuitType): ?float
    {
        $usesSum = ($partType !== 'C' && $circuitType === 'series')
            || ($partType === 'C' && $circuitType === 'parallel');

        if ($usesSum) {
            $needed = $target - $first;

            return $needed > 0 ? $needed : null;
        }

        if ($first <= $target) {
            return null;
        }

        $denominator = (1 / $target) - (1 / $first);

        return $denominator > 0 ? 1 / $denominator : null;
    }

    private function nearestValueWindow(array $values, float $target, int $minIndex = 0, int $radius = 2): array
    {
        if ($target <= 0 || ! is_finite($target)) {
            return [];
        }

        $bestIndex = null;
        $bestDistance = INF;
        for ($i = $minIndex; $i < count($values); $i++) {
            $distance = abs((float) $values[$i]['value'] - $target);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestIndex = $i;
            }
        }
        if ($bestIndex === null) {
            return [];
        }

        return array_slice($values, max($minIndex, $bestIndex - $radius), $radius * 2 + 1);
    }

    private function targetedSeriesCombos(array $values, float $target, string $partType, int $length): iterable
    {
        if ($partType !== 'R' || $length < 3 || $length > 4) {
            yield from $this->combinationsWithReplacement($values, $length);

            return;
        }

        if ($length === 3) {
            $count = count($values);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i; $j < $count; $j++) {
                    $needed = $target - (float) $values[$i]['value'] - (float) $values[$j]['value'];
                    if ($needed < (float) $values[$j]['value']) {
                        continue;
                    }
                    $match = $this->nearestValue($values, $needed, $j);
                    if ($match !== null) {
                        yield [$values[$i], $values[$j], $match];
                    }
                }
            }

            return;
        }

        yield from $this->combinationsWithReplacement($values, $length);
    }

    private function nearestValue(array $values, float $target, int $minIndex = 0): ?array
    {
        $best = null;
        $bestDistance = INF;
        for ($i = $minIndex; $i < count($values); $i++) {
            $distance = abs((float) $values[$i]['value'] - $target);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $values[$i];
            }
        }

        return $best;
    }

    private function candidatePayload(array $combo, float $actual, float $target, float $errPct, string $partType, array $pattern, float $elementTolPct): array
    {
        $payload = [
            'expression' => $pattern['expr']($combo),
            'elements_count' => count($combo),
            'error_pct' => round($errPct, 4),
            'error_display' => $this->formatPercent($errPct),
            'error_abs_value' => abs($actual - $target),
            'error_abs_display' => $this->formatValue(abs($actual - $target), $partType),
            'circuit_type' => $pattern['type'],
            'topology_label' => $pattern['label'],
            'actual_value' => $actual,
            'actual_display' => $this->formatValue($actual, $partType),
            'target_display' => $this->formatValue($target, $partType),
            'parts_total_value' => array_sum(array_map(fn ($item) => (float) $item['value'], $combo)),
            'parts' => array_map(fn ($item, $index) => $this->partPayload($item, 'P'.($index + 1)), $combo, array_keys($combo)),
            'from_inventory' => collect($combo)->contains(fn ($item) => ! empty($item['component_id'])),
        ];

        if ($elementTolPct > 0) {
            $payload = [
                ...$payload,
                ...$this->elementTolerancePayload($combo, $actual, $target, $partType, $pattern, $elementTolPct),
            ];
        }

        return $payload;
    }

    private function elementTolerancePayload(array $combo, float $actual, float $target, string $partType, array $pattern, float $elementTolPct): array
    {
        $factor = $elementTolPct / 100;
        $lowEquivalent = $pattern['eval']($this->scaledCombo($combo, max(0.0, 1 - $factor)));
        $highEquivalent = $pattern['eval']($this->scaledCombo($combo, 1 + $factor));

        if (! is_finite($lowEquivalent) || ! is_finite($highEquivalent)) {
            return [];
        }

        $low = min($lowEquivalent, $highEquivalent);
        $high = max($lowEquivalent, $highEquivalent);
        $lowDeviation = $low - $target;
        $highDeviation = $high - $target;
        $lowDeviationPct = $target > 0 ? ($lowDeviation / $target) * 100 : INF;
        $highDeviationPct = $target > 0 ? ($highDeviation / $target) * 100 : INF;
        $maxDeviation = max(abs($lowDeviation), abs($highDeviation));
        $maxDeviationPct = $target > 0 ? ($maxDeviation / $target) * 100 : INF;

        return [
            'element_tolerance_pct' => round($elementTolPct, 4),
            'element_tolerance_display' => $this->formatPercent($elementTolPct),
            ...$this->rssTolerancePayload($combo, $actual, $target, $partType, $pattern, $elementTolPct),
            'low_equivalent_value' => $low,
            'low_equivalent_display' => $this->formatValue($low, $partType),
            'high_equivalent_value' => $high,
            'high_equivalent_display' => $this->formatValue($high, $partType),
            'tolerance_range_display' => $this->formatValue($low, $partType).' 〜 '.$this->formatValue($high, $partType),
            'corner_range_display' => $this->formatValue($low, $partType).' 〜 '.$this->formatValue($high, $partType),
            'low_target_deviation_value' => $lowDeviation,
            'low_target_deviation_display' => $this->formatSignedValue($lowDeviation, $partType),
            'low_target_deviation_pct' => round($lowDeviationPct, 4),
            'low_target_deviation_pct_display' => $this->formatSignedPercent($lowDeviationPct),
            'high_target_deviation_value' => $highDeviation,
            'high_target_deviation_display' => $this->formatSignedValue($highDeviation, $partType),
            'high_target_deviation_pct' => round($highDeviationPct, 4),
            'high_target_deviation_pct_display' => $this->formatSignedPercent($highDeviationPct),
            'max_target_deviation_value' => $maxDeviation,
            'max_target_deviation_display_value' => $this->formatValue($maxDeviation, $partType),
            'max_target_deviation_pct' => round($maxDeviationPct, 4),
            'max_target_deviation_display' => $this->formatPercent($maxDeviationPct),
        ];
    }

    private function rssTolerancePayload(array $combo, float $actual, float $target, string $partType, array $pattern, float $elementTolPct): array
    {
        if ($actual <= 0 || ! is_finite($actual)) {
            return [];
        }

        $sumSquares = 0.0;
        foreach (array_keys($combo) as $index) {
            $sensitivity = $this->normalizedSensitivity($combo, (int) $index, $actual, $pattern);
            if (! is_finite($sensitivity)) {
                return [];
            }
            $sumSquares += $sensitivity ** 2;
        }

        $spread = $actual * ($elementTolPct / 100) * sqrt($sumSquares);
        $low = max(0.0, $actual - $spread);
        $high = $actual + $spread;
        $lowDeviation = $low - $target;
        $highDeviation = $high - $target;
        $maxDeviation = max(abs($lowDeviation), abs($highDeviation));
        $spreadPct = $target > 0 ? ($spread / $target) * 100 : INF;
        $maxDeviationPct = $target > 0 ? ($maxDeviation / $target) * 100 : INF;

        return [
            'rss_equivalent_spread_value' => $spread,
            'rss_equivalent_spread_display' => $this->formatValue($spread, $partType),
            'rss_equivalent_spread_pct' => round($spreadPct, 4),
            'rss_equivalent_spread_pct_display' => $this->formatPercent($spreadPct),
            'rss_low_equivalent_value' => $low,
            'rss_low_equivalent_display' => $this->formatValue($low, $partType),
            'rss_high_equivalent_value' => $high,
            'rss_high_equivalent_display' => $this->formatValue($high, $partType),
            'rss_range_display' => $this->formatValue($low, $partType).' 〜 '.$this->formatValue($high, $partType),
            'rss_low_target_deviation_value' => $lowDeviation,
            'rss_low_target_deviation_display' => $this->formatSignedValue($lowDeviation, $partType),
            'rss_high_target_deviation_value' => $highDeviation,
            'rss_high_target_deviation_display' => $this->formatSignedValue($highDeviation, $partType),
            'rss_max_target_deviation_value' => $maxDeviation,
            'rss_max_target_deviation_display_value' => $this->formatValue($maxDeviation, $partType),
            'rss_max_target_deviation_pct' => round($maxDeviationPct, 4),
            'rss_max_target_deviation_display' => $this->formatPercent($maxDeviationPct),
        ];
    }

    private function normalizedSensitivity(array $combo, int $index, float $actual, array $pattern): float
    {
        $value = (float) ($combo[$index]['value'] ?? 0);
        if ($value <= 0 || $actual <= 0) {
            return NAN;
        }

        $epsilon = 1e-6;
        $up = $combo;
        $down = $combo;
        $up[$index]['value'] = $value * (1 + $epsilon);
        $down[$index]['value'] = $value * (1 - $epsilon);

        $upActual = $pattern['eval']($up);
        $downActual = $pattern['eval']($down);
        if (! is_finite($upActual) || ! is_finite($downActual)) {
            return NAN;
        }

        $derivative = ($upActual - $downActual) / (2 * $value * $epsilon);

        return ($derivative * $value) / $actual;
    }

    private function scaledCombo(array $combo, float $factor): array
    {
        return array_map(fn ($item) => [
            ...$item,
            'value' => (float) $item['value'] * $factor,
        ], $combo);
    }

    private function dividerTolerancePayload(float $upper, float $lower, float $targetRatio, array $params, float $actualRatio): array
    {
        $upperTol = max(0.0, min(100.0, (float) ($params['divider_upper_tolerance_pct'] ?? 0.0)));
        $lowerTol = max(0.0, min(100.0, (float) ($params['divider_lower_tolerance_pct'] ?? 0.0)));
        if ($upperTol <= 0 && $lowerTol <= 0) {
            return [];
        }

        $values = [$upper, $lower];
        $tolerances = [$upperTol, $lowerTol];
        $evaluateRatio = fn (array $items) => $this->dividerLoadedResult((float) $items[0], (float) $items[1], $params)['ratio'];
        $rss = $this->numericToleranceRange($values, $tolerances, $actualRatio, $evaluateRatio);
        $corner = $this->cornerToleranceRange($values, $tolerances, $evaluateRatio);
        if ($rss === null || $corner === null) {
            return [];
        }

        $rssMaxErrorRatio = max(abs($rss['low'] - $targetRatio), abs($rss['high'] - $targetRatio));
        $cornerMaxErrorRatio = max(abs($corner['low'] - $targetRatio), abs($corner['high'] - $targetRatio));

        return [
            'divider_upper_tolerance_pct' => round($upperTol, 4),
            'divider_lower_tolerance_pct' => round($lowerTol, 4),
            'divider_tolerance_display' => 'R1 ±'.$this->formatPercent($upperTol).' / R2 ±'.$this->formatPercent($lowerTol),
            'divider_rss_low_ratio' => $rss['low'],
            'divider_rss_high_ratio' => $rss['high'],
            'divider_rss_ratio_range_display' => $this->formatRatio($rss['low']).' 〜 '.$this->formatRatio($rss['high']),
            'divider_rss_range_display' => $this->formatDividerOutput($rss['low'], $params).' 〜 '.$this->formatDividerOutput($rss['high'], $params),
            'divider_rss_spread_ratio' => $rss['spread'],
            'divider_rss_spread_display' => $this->formatDividerOutputDelta($rss['spread'], $params),
            'divider_rss_max_error_ratio' => $rssMaxErrorRatio,
            'divider_rss_max_error_display' => $this->formatDividerOutputDelta($rssMaxErrorRatio, $params),
            'divider_rss_max_error_pct' => round($targetRatio > 0 ? ($rssMaxErrorRatio / $targetRatio) * 100 : INF, 4),
            'divider_rss_max_error_pct_display' => $this->formatPercent($targetRatio > 0 ? ($rssMaxErrorRatio / $targetRatio) * 100 : INF),
            'divider_corner_low_ratio' => $corner['low'],
            'divider_corner_high_ratio' => $corner['high'],
            'divider_corner_ratio_range_display' => $this->formatRatio($corner['low']).' 〜 '.$this->formatRatio($corner['high']),
            'divider_corner_range_display' => $this->formatDividerOutput($corner['low'], $params).' 〜 '.$this->formatDividerOutput($corner['high'], $params),
            'divider_corner_max_error_ratio' => $cornerMaxErrorRatio,
            'divider_corner_max_error_display' => $this->formatDividerOutputDelta($cornerMaxErrorRatio, $params),
            'divider_corner_max_error_pct' => round($targetRatio > 0 ? ($cornerMaxErrorRatio / $targetRatio) * 100 : INF, 4),
            'divider_corner_max_error_pct_display' => $this->formatPercent($targetRatio > 0 ? ($cornerMaxErrorRatio / $targetRatio) * 100 : INF),
        ];
    }

    private function numericToleranceRange(array $values, array $tolerances, float $actual, callable $evaluate): ?array
    {
        if (! is_finite($actual)) {
            return null;
        }

        $epsilon = 1e-6;
        $sumSquares = 0.0;
        foreach ($values as $index => $value) {
            $value = (float) $value;
            $tol = max(0.0, (float) ($tolerances[$index] ?? 0.0)) / 100;
            if ($value <= 0 || $tol <= 0) {
                continue;
            }

            $up = $values;
            $down = $values;
            $up[$index] = $value * (1 + $epsilon);
            $down[$index] = $value * (1 - $epsilon);
            $upActual = $evaluate($up);
            $downActual = $evaluate($down);
            if (! is_finite($upActual) || ! is_finite($downActual)) {
                return null;
            }

            $derivative = ($upActual - $downActual) / (2 * $value * $epsilon);
            $sumSquares += ($derivative * $value * $tol) ** 2;
        }

        $spread = sqrt($sumSquares);

        return [
            'low' => $actual - $spread,
            'high' => $actual + $spread,
            'spread' => $spread,
        ];
    }

    private function cornerToleranceRange(array $values, array $tolerances, callable $evaluate): ?array
    {
        $corners = [[]];
        foreach ($values as $index => $value) {
            $tol = max(0.0, (float) ($tolerances[$index] ?? 0.0)) / 100;
            $options = $tol > 0
                ? [(float) $value * (1 - $tol), (float) $value * (1 + $tol)]
                : [(float) $value];
            $next = [];
            foreach ($corners as $corner) {
                foreach ($options as $option) {
                    $next[] = [...$corner, $option];
                }
            }
            $corners = $next;
        }

        $results = [];
        foreach ($corners as $corner) {
            $value = $evaluate($corner);
            if (is_finite($value)) {
                $results[] = $value;
            }
        }
        if ($results === []) {
            return null;
        }

        return [
            'low' => min($results),
            'high' => max($results),
        ];
    }

    private function hasSufficientInventory(array $parts): bool
    {
        $needs = [];
        foreach ($parts as $part) {
            if (empty($part['component_id'])) {
                continue;
            }
            $componentId = (int) $part['component_id'];
            $needs[$componentId] = ($needs[$componentId] ?? 0) + 1;
            if (($part['stock_quantity'] ?? null) !== null && $needs[$componentId] > (int) $part['stock_quantity']) {
                return false;
            }
        }

        return true;
    }

    private function partPayload(array $item, string $role): array
    {
        return [
            'role' => $role,
            'label' => $item['label'],
            'value' => $item['value'],
            'component_id' => $item['component_id'] ?? null,
            'url' => ! empty($item['component_id']) ? "/components/{$item['component_id']}" : null,
            'stock_quantity' => $item['stock_quantity'] ?? null,
            'source' => $item['source'] ?? 'series',
        ];
    }

    private function uniqueCandidates(array $candidates): array
    {
        $unique = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $key = $candidate['circuit_type'].'|'.$candidate['expression'].'|'.$this->valueKey((float) $candidate['actual_value']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $candidate;
        }

        return $unique;
    }

    private function seriesEquivalent(array $values, string $partType): float
    {
        if ($partType === 'C') {
            return $this->reciprocalEquivalent($values);
        }

        return $this->sumEquivalent($values);
    }

    private function parallelEquivalent(array $values, string $partType): float
    {
        if ($partType === 'C') {
            return $this->sumEquivalent($values);
        }

        return $this->reciprocalEquivalent($values);
    }

    private function dividerLoadedResult(float $upper, float $lower, array $params): array
    {
        $loadType = $params['load_type'] ?? 'resistance';
        $inputVoltage = isset($params['input_voltage']) ? (float) $params['input_voltage'] : null;

        if ($loadType === 'current') {
            $loadCurrent = max(0.0, (float) ($params['load_current'] ?? 0));
            $total = $upper + $lower;
            $noLoadRatio = $total > 0 ? $lower / $total : NAN;
            if ($inputVoltage === null || $inputVoltage <= 0) {
                return [
                    'ratio' => $noLoadRatio,
                    'output_voltage' => $inputVoltage !== null ? $noLoadRatio * $inputVoltage : null,
                    'load_type' => 'current',
                    'load_display' => $this->formatCurrent($loadCurrent),
                ];
            }

            $outputVoltage = $inputVoltage * $noLoadRatio;
            if ($loadCurrent > 0) {
                $theveninResistance = $this->parallelPair($upper, $lower);
                $outputVoltage -= $loadCurrent * $theveninResistance;
            }
            $ratio = $outputVoltage / $inputVoltage;

            return [
                'ratio' => $ratio,
                'output_voltage' => $outputVoltage,
                'load_type' => 'current',
                'load_display' => $this->formatCurrent($loadCurrent),
                ...$this->dividerElectricalMetrics($inputVoltage, $upper, $lower, $outputVoltage, $loadCurrent),
            ];
        }

        $loadResistance = $this->loadResistance($params);
        $loadedLower = $loadResistance === INF ? $lower : $this->parallelPair($lower, $loadResistance);
        $total = $upper + $loadedLower;
        $ratio = $total > 0 ? $loadedLower / $total : NAN;

        return [
            'ratio' => $ratio,
            'output_voltage' => $inputVoltage !== null ? $ratio * $inputVoltage : null,
            'load_type' => 'resistance',
            'load_display' => $loadResistance === INF ? '∞Ω' : $this->formatValue($loadResistance, 'R'),
            ...($inputVoltage !== null && $inputVoltage > 0
                ? $this->dividerElectricalMetrics($inputVoltage, $upper, $lower, $ratio * $inputVoltage, $loadResistance === INF ? 0.0 : (($ratio * $inputVoltage) / $loadResistance))
                : []),
        ];
    }

    private function dividerElectricalMetrics(float $inputVoltage, float $upper, float $lower, float $outputVoltage, float $loadCurrent): array
    {
        $sourceCurrent = $upper > 0 ? ($inputVoltage - $outputVoltage) / $upper : NAN;
        $lowerCurrent = $lower > 0 ? $outputVoltage / $lower : NAN;
        $upperPower = $upper > 0 ? (($inputVoltage - $outputVoltage) ** 2) / $upper : NAN;
        $lowerPower = $lower > 0 ? ($outputVoltage ** 2) / $lower : NAN;
        $loadPower = $outputVoltage * max(0.0, $loadCurrent);
        $resistorPower = $upperPower + $lowerPower;
        $totalPower = $resistorPower + $loadPower;

        return [
            'source_current' => $sourceCurrent,
            'source_current_display' => $this->formatCurrent($sourceCurrent),
            'lower_current' => $lowerCurrent,
            'lower_current_display' => $this->formatCurrent($lowerCurrent),
            'output_current' => max(0.0, $loadCurrent),
            'output_current_display' => $this->formatCurrent(max(0.0, $loadCurrent)),
            'upper_power' => $upperPower,
            'upper_power_display' => $this->formatPower($upperPower),
            'lower_power' => $lowerPower,
            'lower_power_display' => $this->formatPower($lowerPower),
            'resistor_power' => $resistorPower,
            'resistor_power_display' => $this->formatPower($resistorPower),
            'load_power' => $loadPower,
            'load_power_display' => $this->formatPower($loadPower),
            'total_power' => $totalPower,
            'total_power_display' => $this->formatPower($totalPower),
        ];
    }

    private function dividerElectricalCandidateFields(array $loaded): array
    {
        $keys = [
            'source_current',
            'source_current_display',
            'lower_current',
            'lower_current_display',
            'output_current',
            'output_current_display',
            'upper_power',
            'upper_power_display',
            'lower_power',
            'lower_power_display',
            'resistor_power',
            'resistor_power_display',
            'load_power',
            'load_power_display',
            'total_power',
            'total_power_display',
        ];

        return array_intersect_key($loaded, array_flip($keys));
    }

    private function loadResistance(array $params): float
    {
        if (! empty($params['load_resistance_infinite']) || ! array_key_exists('load_resistance', $params) || $params['load_resistance'] === null) {
            return INF;
        }

        return max(0.0, (float) $params['load_resistance']);
    }

    private function parallelPair(float $a, float $b): float
    {
        if ($a <= 0 || $b <= 0) {
            return 0.0;
        }
        if ($a === INF) {
            return $b;
        }
        if ($b === INF) {
            return $a;
        }

        return 1 / ((1 / $a) + (1 / $b));
    }

    private function sumEquivalent(array $values): float
    {
        return array_sum(array_map(fn ($item) => (float) $item['value'], $values));
    }

    private function reciprocalEquivalent(array $values): float
    {
        $sum = 0.0;
        foreach ($values as $item) {
            if (($item['value'] ?? 0) <= 0) {
                return 0;
            }
            $sum += 1 / (float) $item['value'];
        }

        return $sum > 0 ? 1 / $sum : 0;
    }

    private function node(float $value): array
    {
        return ['value' => $value, 'label' => $this->valueKey($value)];
    }

    private function pattern(string $type, string $label, callable $eval, callable $expr): array
    {
        return compact('type', 'label', 'eval', 'expr');
    }

    private function formatValue(float $value, string $partType): string
    {
        if ($value <= 0 || ! is_finite($value)) {
            return '0';
        }
        if ($partType === 'C') {
            if ($value < 1e-9) {
                return $this->trimNumber($value * 1e12).'pF';
            }
            if ($value < 1e-6) {
                return $this->trimNumber($value * 1e9).'nF';
            }
            if ($value < 1e-3) {
                return $this->trimNumber($value * 1e6).'μF';
            }

            return $this->trimNumber($value * 1e3).'mF';
        }
        if ($value < 1) {
            return $this->trimNumber($value * 1000).'mΩ';
        }
        if ($value < 1e3) {
            return $this->trimNumber($value).'Ω';
        }
        if ($value < 1e6) {
            return $this->trimNumber($value / 1e3).'kΩ';
        }

        return $this->trimNumber($value / 1e6).'MΩ';
    }

    private function formatRatio(float $ratio): string
    {
        return $this->trimNumber($ratio * 100, 4).'%';
    }

    private function dividerTargetDisplay(float $ratio, array $params): string
    {
        if (isset($params['input_voltage'], $params['output_voltage'])) {
            return $this->formatVoltage((float) $params['output_voltage'])
                .' / '
                .$this->formatVoltage((float) $params['input_voltage'])
                .' = '
                .$this->formatRatio($ratio);
        }

        return $this->formatRatio($ratio);
    }

    private function formatDividerOutput(float $ratio, array $params): string
    {
        if (isset($params['input_voltage']) && (float) $params['input_voltage'] > 0) {
            return $this->formatVoltage($ratio * (float) $params['input_voltage']);
        }

        return $this->formatRatio($ratio);
    }

    private function formatDividerOutputDelta(float $ratioDelta, array $params): string
    {
        if (isset($params['input_voltage']) && (float) $params['input_voltage'] > 0) {
            return $this->formatVoltage(abs($ratioDelta) * (float) $params['input_voltage']);
        }

        return $this->formatRatio(abs($ratioDelta));
    }

    private function formatVoltage(float $value): string
    {
        if (! is_finite($value)) {
            return '-';
        }
        $abs = abs($value);
        if ($abs > 0 && $abs < 1) {
            return $this->trimNumber($value * 1000).'mV';
        }
        if ($abs >= 1000) {
            return $this->trimNumber($value / 1000).'kV';
        }

        return $this->trimNumber($value).'V';
    }

    private function formatSignedValue(float $value, string $partType): string
    {
        if (! is_finite($value)) {
            return '-';
        }

        return ($value >= 0 ? '+' : '-').$this->formatValue(abs($value), $partType);
    }

    private function formatSignedVoltage(float $value): string
    {
        if (! is_finite($value)) {
            return '-';
        }

        return ($value >= 0 ? '+' : '-').$this->formatVoltage(abs($value));
    }

    private function formatCurrent(float $value): string
    {
        if (! is_finite($value)) {
            return '-';
        }
        $abs = abs($value);
        if ($abs == 0.0) {
            return '0A';
        }
        if ($abs < 1e-6) {
            return $this->trimNumber($value * 1e9).'nA';
        }
        if ($abs < 1e-3) {
            return $this->trimNumber($value * 1e6).'μA';
        }
        if ($abs < 1) {
            return $this->trimNumber($value * 1000).'mA';
        }

        return $this->trimNumber($value).'A';
    }

    private function formatPower(float $value): string
    {
        if (! is_finite($value)) {
            return '-';
        }
        $abs = abs($value);
        if ($abs == 0.0) {
            return '0W';
        }
        if ($abs < 1e-6) {
            return $this->trimNumber($value * 1e9).'nW';
        }
        if ($abs < 1e-3) {
            return $this->trimNumber($value * 1e6).'μW';
        }
        if ($abs < 1) {
            return $this->trimNumber($value * 1000).'mW';
        }

        return $this->trimNumber($value).'W';
    }

    private function formatPercent(float $value): string
    {
        return $this->trimNumber($value, 4).'%';
    }

    private function formatSignedPercent(float $value): string
    {
        if (! is_finite($value)) {
            return '-';
        }

        return ($value >= 0 ? '+' : '-').$this->formatPercent(abs($value));
    }

    private function trimNumber(float $value, int $decimals = 6): string
    {
        $text = number_format($value, $decimals, '.', '');

        return rtrim(rtrim($text, '0'), '.');
    }

    private function valueKey(float $value): string
    {
        return sprintf('%.12g', $value);
    }
}

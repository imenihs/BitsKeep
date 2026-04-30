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
                    foreach ($this->evaluateCombo($combo, $target, $partType, $circuitTypes, $tolPct, $evaluationCount, $evaluationLimited) as $candidate) {
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

        usort($candidates, fn ($a, $b) => [$a['error_pct'], $a['elements_count'], $a['parts_total_value']] <=> [$b['error_pct'], $b['elements_count'], $b['parts_total_value']]);
        $unique = $this->uniqueCandidates($candidates);

        return [
            'target_value' => $target,
            'target_display' => $partType === 'divider' ? $this->formatRatio($target) : $this->formatValue($target, $partType),
            'part_type' => $partType,
            'candidate_pool_count' => $maxPoolCount,
            'raw_pool_count' => count($rawValues),
            'return_limit' => self::RETURN_LIMIT,
            'candidates' => array_slice($unique, 0, self::RETURN_LIMIT),
            'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
            'truncated' => count($unique) > self::RETURN_LIMIT || $evaluationLimited,
            'evaluation_limited' => $evaluationLimited,
        ];
    }

    private function evaluateCombo(array $combo, float $target, string $partType, array $allowed, float $tolPct, int &$evaluationCount, bool &$evaluationLimited): array
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
            $results[] = $this->candidatePayload($combo, $actual, $target, $errPct, $partType, $pattern);
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

                $actual = $r2['value'] / $total;
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
                    'target_display' => $this->formatRatio($ratio),
                    'total_value' => $total,
                    'total_display' => $this->formatValue($total, 'R'),
                    'parts_total_value' => $total,
                    'parts' => [
                        $this->partPayload($r1, 'R1上側'),
                        $this->partPayload($r2, 'R2下側'),
                    ],
                    'from_inventory' => ! empty($r1['component_id']) || ! empty($r2['component_id']),
                ];
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

    private function candidatePayload(array $combo, float $actual, float $target, float $errPct, string $partType, array $pattern): array
    {
        return [
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

    private function formatPercent(float $value): string
    {
        return $this->trimNumber($value, 4).'%';
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

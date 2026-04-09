<?php

namespace App\Services;

use App\Models\Component;

class NetworkSearchService
{
    private const RETURN_LIMIT = 50;
    private const POOL_LIMIT = 18;

    private const E_SERIES = [
        'E6'  => [1.0, 1.5, 2.2, 3.3, 4.7, 6.8],
        'E12' => [1.0, 1.2, 1.5, 1.8, 2.2, 2.7, 3.3, 3.9, 4.7, 5.6, 6.8, 8.2],
        'E24' => [1.0, 1.1, 1.2, 1.3, 1.5, 1.6, 1.8, 2.0, 2.2, 2.4, 2.7, 3.0, 3.3, 3.6, 3.9, 4.3, 4.7, 5.1, 5.6, 6.2, 6.8, 7.5, 8.2, 9.1],
        'E48' => [1.00,1.05,1.10,1.15,1.21,1.27,1.33,1.40,1.47,1.54,1.62,1.69,1.78,1.87,1.96,2.05,2.15,2.26,2.37,2.49,2.61,2.74,2.87,3.01,3.16,3.32,3.48,3.65,3.83,4.02,4.22,4.42,4.64,4.87,5.11,5.36,5.62,5.90,6.19,6.49,6.81,7.15,7.50,7.87,8.25,8.66,9.09,9.53],
        'E96' => [1.00,1.02,1.05,1.07,1.10,1.13,1.15,1.18,1.21,1.24,1.27,1.30,1.33,1.37,1.40,1.43,1.47,1.50,1.54,1.58,1.62,1.65,1.69,1.74,1.78,1.82,1.87,1.91,1.96,2.00,2.05,2.10,2.15,2.21,2.26,2.32,2.37,2.43,2.49,2.55,2.61,2.67,2.74,2.80,2.87,2.94,3.01,3.09,3.16,3.24,3.32,3.40,3.48,3.57,3.65,3.74,3.83,3.92,4.02,4.12,4.22,4.32,4.42,4.53,4.64,4.75,4.87,4.99,5.11,5.23,5.36,5.49,5.62,5.76,5.90,6.04,6.19,6.34,6.49,6.65,6.81,6.98,7.15,7.32,7.50,7.68,7.87,8.06,8.25,8.45,8.66,8.87,9.09,9.31,9.53,9.76],
    ];

    private const DECADES_R = [1, 10, 100, 1000, 10000, 100000, 1000000];
    private const DECADES_C = [1e-12, 1e-11, 1e-10, 1e-9, 1e-8, 1e-7, 1e-6, 1e-5, 1e-4];

    public function search(array $params): array
    {
        $started = microtime(true);
        $partType = $params['part_type'] ?? 'R';
        $target = (float) $params['target'];
        $tolPct = (float) ($params['tolerance_pct'] ?? 5.0);
        $minElements = max(1, (int) ($params['min_elements'] ?? 1));
        $maxElements = min(4, (int) ($params['max_elements'] ?? 3));
        $circuitTypes = $params['circuit_types'] ?? ['series', 'parallel'];

        $values = $this->limitPool(
            $this->buildValueSet($params['series'] ?? 'E24', $params['custom_values'] ?? [], $partType, !empty($params['inventory_only'])),
            $target,
            $partType
        );

        $candidates = [];

        if ($partType === 'divider') {
            $this->searchDivider($values, $params, $candidates);
        } else {
            for ($count = $minElements; $count <= $maxElements; $count++) {
                foreach ($this->combinationsWithReplacement($values, $count) as $combo) {
                    foreach ($this->evaluateCombo($combo, $target, $partType, $circuitTypes) as $candidate) {
                        if ($candidate['error_pct'] <= $tolPct) {
                            $candidates[] = $candidate;
                        }
                    }
                }
            }
        }

        usort($candidates, fn($a, $b) => $a['error_pct'] <=> $b['error_pct']);
        $unique = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $key = $candidate['expression'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $candidate;
        }

        return [
            'candidates' => array_slice($unique, 0, self::RETURN_LIMIT),
            'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
            'truncated' => count($unique) > self::RETURN_LIMIT,
        ];
    }

    private function evaluateCombo(array $combo, float $target, string $partType, array $allowed): array
    {
        $count = count($combo);
        $patterns = [];

        if (in_array('series', $allowed, true)) {
            $patterns[] = ['type' => 'series', 'label' => '直列', 'eval' => fn($v) => $this->sumValues($v), 'expr' => fn($v) => implode(' + ', array_map(fn($i) => $i['label'], $v))];
        }
        if (in_array('parallel', $allowed, true)) {
            $patterns[] = ['type' => 'parallel', 'label' => '並列', 'eval' => fn($v) => $this->parallelValues($v), 'expr' => fn($v) => implode(' ∥ ', array_map(fn($i) => $i['label'], $v))];
        }
        if (in_array('mixed', $allowed, true) && $count >= 3) {
            $patterns = array_merge($patterns, $this->mixedPatterns($combo));
        }

        $results = [];
        foreach ($patterns as $pattern) {
            $actual = $pattern['eval']($combo);
            if ($actual <= 0 || !is_finite($actual)) {
                continue;
            }
            $errPct = abs($actual - $target) / $target * 100;
            $results[] = [
                'expression' => $pattern['expr']($combo),
                'elements_count' => $count,
                'error_pct' => round($errPct, 3),
                'circuit_type' => $pattern['type'],
                'topology_label' => $pattern['label'],
                'actual_value' => $actual,
                'actual_display' => $this->formatValue($actual, $partType),
                'parts' => array_map(fn($item) => [
                    'label' => $item['label'],
                    'component_id' => $item['component_id'] ?? null,
                    'url' => !empty($item['component_id']) ? "/components/{$item['component_id']}" : null,
                ], $combo),
                'from_inventory' => collect($combo)->contains(fn($item) => !empty($item['component_id'])),
            ];
        }

        return $results;
    }

    private function mixedPatterns(array $combo): array
    {
        $make = fn(string $type, string $label, callable $eval, callable $expr) => compact('type', 'label', 'eval', 'expr');
        $p = [];

        if (count($combo) === 3) {
            foreach ([[0,1,2],[0,2,1],[1,2,0]] as [$a,$b,$c]) {
                $p[] = $make('mixed', '直並列混在', fn($v) => $this->parallelValues([['value' => $v[$a]['value'] + $v[$b]['value']], $v[$c]]), fn($v) => "({$v[$a]['label']} + {$v[$b]['label']}) ∥ {$v[$c]['label']}");
                $p[] = $make('mixed', '並直列混在', fn($v) => $this->sumValues([['value' => $this->parallelValues([$v[$a], $v[$b]])], $v[$c]]), fn($v) => "({$v[$a]['label']} ∥ {$v[$b]['label']}) + {$v[$c]['label']}");
            }
        }

        if (count($combo) === 4) {
            foreach ([[0,1,2,3],[0,2,1,3],[0,3,1,2]] as [$a,$b,$c,$d]) {
                $p[] = $make('mixed', '複雑ネットワーク', fn($v) => $this->parallelValues([['value' => $v[$a]['value'] + $v[$b]['value']], ['value' => $v[$c]['value'] + $v[$d]['value']]]), fn($v) => "({$v[$a]['label']} + {$v[$b]['label']}) ∥ ({$v[$c]['label']} + {$v[$d]['label']})");
                $p[] = $make('mixed', '複雑ネットワーク', fn($v) => ($this->parallelValues([$v[$a], $v[$b]]) + $this->parallelValues([$v[$c], $v[$d]])), fn($v) => "({$v[$a]['label']} ∥ {$v[$b]['label']}) + ({$v[$c]['label']} ∥ {$v[$d]['label']})");
            }
            foreach ([[0,1,2,3],[0,2,1,3],[1,2,0,3],[0,3,1,2]] as [$a,$b,$c,$d]) {
                $p[] = $make('mixed', '複雑ネットワーク', fn($v) => $this->sumValues([['value' => $this->parallelValues([['value' => $v[$a]['value'] + $v[$b]['value']], $v[$c]])], $v[$d]]), fn($v) => "((" . $v[$a]['label'] . " + " . $v[$b]['label'] . ") ∥ " . $v[$c]['label'] . ") + " . $v[$d]['label']);
                $p[] = $make('mixed', '複雑ネットワーク', fn($v) => $this->parallelValues([['value' => $this->sumValues([['value' => $this->parallelValues([$v[$a], $v[$b]])], $v[$c]])], $v[$d]]), fn($v) => "((" . $v[$a]['label'] . " ∥ " . $v[$b]['label'] . ") + " . $v[$c]['label'] . ") ∥ " . $v[$d]['label']);
            }
        }

        return $p;
    }

    private function searchDivider(array $values, array $params, array &$candidates): void
    {
        $ratio = (float) $params['target'];
        $tolPct = (float) ($params['tolerance_pct'] ?? 5.0);
        $totalMin = (float) ($params['total_res_min'] ?? 0);
        $totalMax = (float) ($params['total_res_max'] ?? INF);

        foreach ($values as $r1) {
            foreach ($values as $r2) {
                $total = $r1['value'] + $r2['value'];
                if ($total < $totalMin || $total > $totalMax) {
                    continue;
                }
                $actual = $r2['value'] / $total;
                $errPct = abs($actual - $ratio) / $ratio * 100;
                if ($errPct > $tolPct) {
                    continue;
                }
                $candidates[] = [
                    'expression' => "R1={$r1['label']}, R2={$r2['label']}",
                    'elements_count' => 2,
                    'error_pct' => round($errPct, 3),
                    'circuit_type' => 'divider',
                    'topology_label' => '分圧回路',
                    'actual_value' => $actual,
                    'actual_display' => round($actual * 100, 4) . '%',
                    'total_value' => $total,
                    'total_display' => $this->formatValue($total, 'R'),
                    'parts' => [
                        ['label' => $r1['label'], 'component_id' => $r1['component_id'] ?? null, 'url' => !empty($r1['component_id']) ? "/components/{$r1['component_id']}" : null],
                        ['label' => $r2['label'], 'component_id' => $r2['component_id'] ?? null, 'url' => !empty($r2['component_id']) ? "/components/{$r2['component_id']}" : null],
                    ],
                    'from_inventory' => !empty($r1['component_id']) || !empty($r2['component_id']),
                ];
            }
        }
    }

    private function buildValueSet(string $series, array $custom, string $partType, bool $inventoryOnly): array
    {
        if ($inventoryOnly) {
            return $this->buildInventoryValueSet($partType);
        }
        if ($series === 'custom' && !empty($custom)) {
            return array_map(fn($value) => ['value' => (float) $value, 'label' => $this->formatValue((float) $value, $partType)], $custom);
        }
        $values = [];
        $multipliers = self::E_SERIES[$series] ?? self::E_SERIES['E24'];
        $decades = $partType === 'C' ? self::DECADES_C : self::DECADES_R;
        foreach ($decades as $decade) {
            foreach ($multipliers as $multiplier) {
                $value = round($multiplier * $decade, 15);
                $values[] = ['value' => $value, 'label' => $this->formatValue($value, $partType)];
            }
        }
        return $values;
    }

    private function buildInventoryValueSet(string $partType): array
    {
        $categoryKeyword = $partType === 'C' ? 'コンデンサ' : '抵抗';
        return Component::whereHas('categories', fn($q) => $q->where('name', 'like', "%{$categoryKeyword}%"))
            ->whereHas('inventoryBlocks', fn($q) => $q->where('quantity', '>', 0))
            ->with(['specs', 'categories'])
            ->get()
            ->flatMap(function ($component) {
                $spec = $component->specs->firstWhere('value_numeric', '!=', null);
                if (! $spec?->value_numeric) {
                    return [];
                }
                return [[
                    'value' => (float) $spec->value_numeric,
                    'label' => $component->common_name ?: $component->part_number,
                    'component_id' => $component->id,
                ]];
            })
            ->unique('component_id')
            ->values()
            ->all();
    }

    private function limitPool(array $values, float $target, string $partType): array
    {
        if (count($values) <= self::POOL_LIMIT || $partType === 'divider') {
            return array_slice($values, 0, max(self::POOL_LIMIT * 2, count($values)));
        }
        usort($values, fn($a, $b) => abs($a['value'] - $target) <=> abs($b['value'] - $target));
        return array_slice($values, 0, self::POOL_LIMIT);
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

    private function sumValues(array $values): float
    {
        return array_sum(array_map(fn($item) => $item['value'], $values));
    }

    private function parallelValues(array $values): float
    {
        $sum = 0.0;
        foreach ($values as $item) {
            if (($item['value'] ?? 0) <= 0) {
                return 0;
            }
            $sum += 1 / $item['value'];
        }
        return $sum > 0 ? 1 / $sum : 0;
    }

    private function formatValue(float $value, string $partType): string
    {
        if ($partType === 'C') {
            if ($value < 1e-9) return round($value * 1e12, 3) . 'pF';
            if ($value < 1e-6) return round($value * 1e9, 3) . 'nF';
            if ($value < 1e-3) return round($value * 1e6, 3) . 'μF';
            return round($value * 1e3, 3) . 'mF';
        }
        if ($value < 1e3) return round($value, 3) . 'Ω';
        if ($value < 1e6) return round($value / 1e3, 3) . 'kΩ';
        return round($value / 1e6, 3) . 'MΩ';
    }
}

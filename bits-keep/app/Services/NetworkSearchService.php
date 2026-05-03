<?php

namespace App\Services;

use App\Services\NetworkSearch\BuildsNetworkValuePools;
use App\Services\NetworkSearch\FormatsNetworkValues;

class NetworkSearchService
{
    use BuildsNetworkValuePools;
    use FormatsNetworkValues;

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

    /**
     * 目的: 回路設計で目標値に近い抵抗/容量ネットワーク候補を返す。
     * 機能: 入力条件から値候補を作り、直列/並列/混在/分圧を評価して誤差順に整列する。
     * 入力: part_type、target、許容差、素子数、回路種別、在庫限定などの検索条件配列。
     * 出力: 表示値、候補件数、打ち切り状態、候補一覧を含むAPI返却用配列。
     * 動作条件: target は正の数値で、分圧時は target が出力比率として渡される前提。
     * 副作用: 在庫限定時のみ部品・在庫・スペックをDBから参照し、DB内容は変更しない。
     */
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

    /**
     * 目的: 1つの素子組み合わせを、許可された回路トポロジごとに評価する。
     * 機能: 直列/並列/混在パターンを生成し、目標誤差と素子許容差情報を候補payloadへ変換する。
     * 入力: 値候補combo、目標値、部品種別、許可トポロジ、目標許容差、素子許容差、評価数参照。
     * 出力: 目標許容差内に入った候補payload配列。
     * 動作条件: combo の各要素は value と label を持ち、target は0より大きい。
     * 副作用: 評価数と評価打ち切りフラグを参照渡しで更新する。
     */
    private function evaluateCombo(array $combo, float $target, string $partType, array $allowed, float $tolPct, float $elementTolPct, int &$evaluationCount, bool &$evaluationLimited): array
    {
        $count = count($combo);
        $patterns = [];

        if (in_array('series', $allowed, true)) {
            $patterns[] = $this->pattern(
                'series',
                $partType === 'C' ? '容量直列' : '抵抗直列', fn ($v) => $this->seriesEquivalent($v, $partType), fn ($v) => implode(' + ', array_map(fn ($i) => $i['label'], $v))
            );
        }
        if (in_array('parallel', $allowed, true)) {
            $patterns[] = $this->pattern(
                'parallel',
                $partType === 'C' ? '容量並列' : '抵抗並列', fn ($v) => $this->parallelEquivalent($v, $partType), fn ($v) => implode(' ∥ ', array_map(fn ($i) => $i['label'], $v))
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

    /**
     * 目的: 3素子/4素子の混在回路パターンを列挙する。
     * 機能: 直列枝と並列枝を入れ替えた評価式と表示式を作り、探索漏れを抑える。
     * 入力: 値候補comboと、抵抗/容量の直並列規則を切り替える部品種別。
     * 出力: pattern() で作った評価式定義の配列。
     * 動作条件: combo は3個または4個の候補を想定し、それ以外では空配列を返す。
     * 副作用: なし。
     */
    private function mixedPatterns(array $combo, string $partType): array
    {
        $p = [];

        if (count($combo) === 3) {
            foreach ([[0, 1, 2], [0, 2, 1], [1, 2, 0]] as [$a, $b, $c]) {
                $p[] = $this->pattern(
                    'mixed',
                    '直列枝を並列', fn ($v) => $this->parallelEquivalent([$this->node($this->seriesEquivalent([$v[$a], $v[$b]], $partType)), $v[$c]], $partType), fn ($v) => "({$v[$a]['label']} + {$v[$b]['label']}) ∥ {$v[$c]['label']}"
                );
                $p[] = $this->pattern(
                    'mixed',
                    '並列枝を直列', fn ($v) => $this->seriesEquivalent([$this->node($this->parallelEquivalent([$v[$a], $v[$b]], $partType)), $v[$c]], $partType), fn ($v) => "({$v[$a]['label']} ∥ {$v[$b]['label']}) + {$v[$c]['label']}"
                );
            }
        }

        if (count($combo) === 4) {
            foreach ([[0, 1, 2, 3], [0, 2, 1, 3], [0, 3, 1, 2]] as [$a, $b, $c, $d]) {
                $p[] = $this->pattern(
                    'mixed',
                    '直列2枝を並列', fn ($v) => $this->parallelEquivalent([
                        $this->node($this->seriesEquivalent([$v[$a], $v[$b]], $partType)),
                        $this->node($this->seriesEquivalent([$v[$c], $v[$d]], $partType)),
                    ], $partType), fn ($v) => "({$v[$a]['label']} + {$v[$b]['label']}) ∥ ({$v[$c]['label']} + {$v[$d]['label']})"
                );
                $p[] = $this->pattern(
                    'mixed',
                    '並列2枝を直列', fn ($v) => $this->seriesEquivalent([
                        $this->node($this->parallelEquivalent([$v[$a], $v[$b]], $partType)),
                        $this->node($this->parallelEquivalent([$v[$c], $v[$d]], $partType)),
                    ], $partType), fn ($v) => "({$v[$a]['label']} ∥ {$v[$b]['label']}) + ({$v[$c]['label']} ∥ {$v[$d]['label']})"
                );
            }
            foreach ([[0, 1, 2, 3], [0, 2, 1, 3], [1, 2, 0, 3], [0, 3, 1, 2]] as [$a, $b, $c, $d]) {
                $p[] = $this->pattern(
                    'mixed',
                    '直列枝と単体の並列を直列', fn ($v) => $this->seriesEquivalent([
                        $this->node($this->parallelEquivalent([$this->node($this->seriesEquivalent([$v[$a], $v[$b]], $partType)), $v[$c]], $partType)),
                        $v[$d],
                    ], $partType), fn ($v) => '(('.$v[$a]['label'].' + '.$v[$b]['label'].') ∥ '.$v[$c]['label'].') + '.$v[$d]['label']
                );
                $p[] = $this->pattern(
                    'mixed',
                    '並列枝と単体の直列を並列', fn ($v) => $this->parallelEquivalent([
                        $this->node($this->seriesEquivalent([$this->node($this->parallelEquivalent([$v[$a], $v[$b]], $partType)), $v[$c]], $partType)),
                        $v[$d],
                    ], $partType), fn ($v) => '(('.$v[$a]['label'].' ∥ '.$v[$b]['label'].') + '.$v[$c]['label'].') ∥ '.$v[$d]['label']
                );
            }
        }

        return $p;
    }

    /**
     * 目的: 分圧専用条件でR1/R2候補を探索する。
     * 機能: 総抵抗、負荷条件、出力比率、入力電圧時の誤差を評価して候補へ追加する。
     * 入力: 抵抗値候補、分圧検索条件、候補配列参照、評価数参照、打ち切りフラグ参照。
     * 出力: 戻り値なし。条件を満たす候補を candidates へ追加する。
     * 動作条件: values は抵抗値候補で、params target は Vout/Vin の比率。
     * 副作用: candidates、evaluationCount、evaluationLimited を参照渡しで更新する。
     */
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

    /**
     * 目的: 評価済みの回路1件をAPIで扱う候補形式へ整形する。
     * 機能: 回路式、誤差、表示値、採用部品、在庫由来フラグ、許容差レンジをまとめる。
     * 入力: 素子combo、実効値、目標値、誤差率、部品種別、評価パターン、素子許容差。
     * 出力: 候補1件分の連想配列。
     * 動作条件: pattern は eval/expr/type/label を持つ。
     * 副作用: なし。
     */
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

    /**
     * 目的: 全素子が同じ許容差を持つ前提で、最悪方向の等価値範囲を示す。
     * 機能: 素子値を一括で下限/上限へ振り、目標値からの最大偏差を表示用に計算する。
     * 入力: 素子combo、実効値、目標値、部品種別、評価パターン、素子許容差[%]。
     * 出力: コーナー範囲とRSS目安を含む候補追加フィールド。
     * 動作条件: pattern の eval が下限/上限comboで有限値を返す。
     * 副作用: なし。
     */
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

    /**
     * 目的: 素子誤差が独立してばらつく場合のRSS目安を候補へ付与する。
     * 機能: 各素子の正規化感度を数値微分で求め、二乗和平方根から等価値範囲を推定する。
     * 入力: 素子combo、実効値、目標値、部品種別、評価パターン、素子許容差[%]。
     * 出力: RSS低側/高側/最大偏差の表示用フィールド。計算不能時は空配列。
     * 動作条件: actual は正の有限値で、pattern eval が微小変化に対して有限値を返す。
     * 副作用: なし。
     */
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

    /**
     * 目的: 1素子の値変化が回路全体の等価値へ与える相対感度を求める。
     * 機能: 対象素子を微小に上下へ振り、中心差分で正規化感度を計算する。
     * 入力: 素子combo、対象index、元の実効値、評価パターン。
     * 出力: 正規化感度。無効条件では NAN。
     * 動作条件: 対象素子値と actual が正であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 候補組み合わせを指定倍率でスケールする。
     * 機能: 各候補のvalueだけを倍率適用し、ラベルやsourceは維持する。
     * 入力: 候補組み合わせと倍率。
     * 出力: スケール後の候補組み合わせ。
     * 動作条件: factorが有限数であること。
     * 副作用: なし。
     */
    private function scaledCombo(array $combo, float $factor): array
    {
        return array_map(fn ($item) => [
            ...$item,
            'value' => (float) $item['value'] * $factor,
        ], $combo);
    }

    /**
     * 目的: 分圧回路のR1/R2個別許容差による出力比率範囲を返す。
     * 機能: RSS目安と全コーナーの両方を計算し、比率表示と電圧表示へ変換する。
     * 入力: 上側抵抗、下側抵抗、目標比率、分圧条件、実比率。
     * 出力: 分圧許容差フィールド。許容差未指定または計算不能時は空配列。
     * 動作条件: params に divider_upper_tolerance_pct / divider_lower_tolerance_pct が任意で入る。
     * 副作用: なし。
     */
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

    /**
     * 目的: 任意の評価式に対して、独立誤差のRSS範囲を数値的に求める。
     * 機能: 各入力値の微小変化から感度を推定し、許容差幅を二乗和で合成する。
     * 入力: 元値配列、許容差[%]配列、現在の評価値、評価関数。
     * 出力: low/high/spread を持つ配列。計算不能時は null。
     * 動作条件: evaluate は values と同じ順序の配列を受けて有限値を返す。
     * 副作用: なし。
     */
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

    /**
     * 目的: 指定許容差の全コーナーを評価し、最悪範囲を求める。
     * 機能: 各入力の下限/上限組み合わせを全列挙し、評価値の最小/最大を返す。
     * 入力: 元値配列、許容差[%]配列、評価関数。
     * 出力: low/high を持つ配列。有限値が得られない場合は null。
     * 動作条件: 評価対象が少数で、全コーナー列挙が現実的であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 候補組み合わせが在庫数を超えていないか判定する。
     * 機能: 同一component_idの使用回数を数え、stock_quantityと比較する。
     * 入力: 候補組み合わせ。
     * 出力: 在庫充足の真偽値。
     * 動作条件: 在庫候補にcomponent_id/stock_quantityが含まれること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 探索候補の素子1個をAPI出力形式へ変換する。
     * 機能: 値、表示名、在庫由来情報をフロントが扱うキーへ詰め替える。
     * 入力: 候補素子。
     * 出力: 素子payload配列。
     * 動作条件: 候補にvalueが含まれること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 探索候補から重複回路を除外する。
     * 機能: 値・トポロジ・誤差のキーで重複を潰し、上位候補だけを残す。
     * 入力: 候補配列。
     * 出力: 重複除去済み候補配列。
     * 動作条件: 候補が配列であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 直列接続の合成値を計算する。
     * 機能: 候補valueを合計する。
     * 入力: 候補組み合わせ。
     * 出力: 合成値。
     * 動作条件: 各候補valueが数値であること。
     * 副作用: なし。
     */
    private function seriesEquivalent(array $values, string $partType): float
    {
        if ($partType === 'C') {
            return $this->reciprocalEquivalent($values);
        }

        return $this->sumEquivalent($values);
    }

    /**
     * 目的: 並列接続の合成値を計算する。
     * 機能: 逆数和から合成抵抗/容量を返す。
     * 入力: 候補組み合わせ。
     * 出力: 合成値。
     * 動作条件: 各候補valueが正であること。
     * 副作用: なし。
     */
    private function parallelEquivalent(array $values, string $partType): float
    {
        if ($partType === 'C') {
            return $this->sumEquivalent($values);
        }

        return $this->reciprocalEquivalent($values);
    }

    /**
     * 目的: 負荷を含む分圧出力比率を計算する。
     * 機能: 下側抵抗と負荷の並列値から出力比率を求める。
     * 入力: 上側抵抗、下側抵抗、負荷条件。
     * 出力: 出力比率。
     * 動作条件: 抵抗値が正であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 分圧回路の電流と消費電力を計算する。
     * 機能: 入力電圧と負荷条件から各抵抗/負荷の電気量を求める。
     * 入力: 上側抵抗、下側抵抗、分圧条件。
     * 出力: 電流/電力metrics配列。
     * 動作条件: 入力電圧が正の場合に有効値を返す。
     * 副作用: なし。
     */
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

    /**
     * 目的: 分圧候補へ電気量表示フィールドを付与する。
     * 機能: dividerElectricalMetricsの結果を候補payload用キーへ整形する。
     * 入力: 上側抵抗、下側抵抗、分圧条件。
     * 出力: 候補追加フィールド配列。
     * 動作条件: 入力電圧と抵抗値が計算可能であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 分圧負荷条件から等価抵抗を決定する。
     * 機能: 抵抗負荷、電流負荷、無限大指定を正規化する。
     * 入力: 分圧条件と出力電圧。
     * 出力: 等価負荷抵抗またはnull。
     * 動作条件: paramsにload_typeが任意で入ること。
     * 副作用: なし。
     */
    private function loadResistance(array $params): float
    {
        if (! empty($params['load_resistance_infinite']) || ! array_key_exists('load_resistance', $params) || $params['load_resistance'] === null) {
            return INF;
        }

        return max(0.0, (float) $params['load_resistance']);
    }

    /**
     * 目的: 2つの抵抗値の並列合成を計算する。
     * 機能: 無限大負荷を含む組み合わせを安全に処理する。
     * 入力: 2つの抵抗値。
     * 出力: 並列合成値。
     * 動作条件: 少なくとも一方が正または無限大であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 候補ノードの直列相当値を計算する。
     * 機能: ノード配列または値を合計する。
     * 入力: ノード配列。
     * 出力: 直列相当値。
     * 動作条件: 各ノードが値または子ノードを持つこと。
     * 副作用: なし。
     */
    private function sumEquivalent(array $values): float
    {
        return array_sum(array_map(fn ($item) => (float) $item['value'], $values));
    }

    /**
     * 目的: 候補ノードの並列相当値を計算する。
     * 機能: ノード配列の逆数和から合成値を返す。
     * 入力: ノード配列。
     * 出力: 並列相当値。
     * 動作条件: 各ノード値が正であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 回路木ノードを生成する。
     * 機能: 値、式、構成要素をまとめて探索候補の中間表現にする。
     * 入力: 値、式、子ノード。
     * 出力: 回路ノード配列。
     * 動作条件: valueが有限数であること。
     * 副作用: なし。
     */
    private function node(float $value): array
    {
        return ['value' => $value, 'label' => $this->valueKey($value)];
    }

    /**
     * 目的: 混在回路探索のパターン定義を生成する。
     * 機能: ラベル、評価関数、式生成関数をまとめる。
     * 入力: ラベル、評価関数、式生成関数。
     * 出力: パターン配列。
     * 動作条件: 評価関数が候補組み合わせを受け取れること。
     * 副作用: なし。
     */
    private function pattern(string $type, string $label, callable $eval, callable $expr): array
    {
        return compact('type', 'label', 'eval', 'expr');
    }
}

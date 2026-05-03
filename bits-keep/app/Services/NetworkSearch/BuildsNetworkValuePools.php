<?php

namespace App\Services\NetworkSearch;

use App\Models\Component;

trait BuildsNetworkValuePools
{
    /**
     * 目的: 探索で使う素子値候補を、E系列・カスタム入力・在庫のいずれかから構築する。
     * 機能: 値の正規化、表示ラベル補完、重複除去、昇順整列を一括で行う。
     * 入力: 系列名、カスタム値配列、部品種別、在庫限定フラグ。
     * 出力: value/label/source を持つ候補配列。
     * 動作条件: partType は R/C/divider のいずれかで、custom は数値化可能な値を含む。
     * 副作用: inventoryOnly が true の場合のみDBを読み取る。
     */
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

    /**
     * 目的: E系列名から抵抗/容量の全桁候補を展開する。
     * 機能: 系列倍率と対象部品のdecadeを掛け合わせ、表示ラベル付き候補を作る。
     * 入力: 系列名と部品種別。
     * 出力: 候補配列。
     * 動作条件: seriesがE系列キーで、partTypeがR/C/dividerのいずれかであること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 実在庫からネットワーク探索に使える抵抗/容量値を取り出す。
     * 機能: 部品分類、在庫数量、スペック単位/名称を照合し、在庫数付き候補へ変換する。
     * 入力: 部品種別。divider は抵抗探索として扱う。
     * 出力: component_id と stock_quantity を含む候補配列。
     * 動作条件: 部品に数値スペックと正の在庫ブロックが登録されている。
     * 副作用: components、specs、inventoryBlocks をDBから読み取る。
     */
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

    /**
     * 目的: 在庫部品の複数スペックから、抵抗値または容量値として使う1件を選ぶ。
     * 機能: 単位と表示名をスコア化し、温度係数や許容差など値そのものではない項目を除外する。
     * 入力: スペック読込済みの部品モデルと部品種別。
     * 出力: 探索値に使うスペックモデル。見つからない場合は null。
     * 動作条件: component->specs が参照可能で、数値化済み値が入っている。
     * 副作用: なし。渡されたモデルのリレーションだけを読む。
     */
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

    /**
     * 目的: 素子数が増えた時だけ探索母集団を絞り、評価爆発を避ける。
     * 機能: 1〜2素子または小規模候補は全件を残し、3素子以上は目標値近傍へ制限する。
     * 入力: 値候補、目標値、部品種別、評価する素子数。
     * 出力: 評価対象に使う値候補配列。
     * 動作条件: values は value を持つ候補配列。
     * 副作用: なし。
     */
    private function valuesForElementCount(array $values, float $target, string $partType, int $count): array
    {
        if ($count <= 2 || count($values) <= self::POOL_LIMIT) {
            return $values;
        }

        return $this->limitPool($values, $target, $partType, $count >= 4 ? self::COUNT4_POOL_LIMIT : self::POOL_LIMIT, $count);
    }

    /**
     * 目的: 多素子探索で有望な値だけを残し、処理時間と候補品質のバランスを取る。
     * 機能: 目標値、目標値の整数分割、整数倍、抵抗の1桁違いをアンカーにして近い値を選ぶ。
     * 入力: 値候補、目標値、部品種別、残す件数、最大素子数。
     * 出力: value 昇順に戻した候補配列。
     * 動作条件: value は正の有限値であること。
     * 副作用: 内部で配列の並び替えを行うが、DBや外部状態は変更しない。
     */
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

    /**
     * 目的: 候補値が目標アンカー群にどれだけ近いかを対数距離で測る。
     * 機能: 抵抗・容量の桁違いを公平に比較するため、差分ではなく log10 の差を使う。
     * 入力: 評価する値とアンカー値配列。
     * 出力: 最も近いアンカーまでの距離。無効値だけなら INF。
     * 動作条件: value と anchor は正の値だけが有効。
     * 副作用: なし。
     */
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

    /**
     * 目的: 同じ値を複数回使える素子組み合わせを重複順序なしで列挙する。
     * 機能: 開始位置を引き継ぐ再帰で、A+B と B+A のような同一構成を片方にまとめる。
     * 入力: 値候補、必要素子数、再帰開始位置。
     * 出力: combo 配列を順次返す iterable。
     * 動作条件: length が0になった時点で1組の生成が完了する。
     * 副作用: なし。
     */
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

    /**
     * 目的: 素子数と回路種別に応じて、評価対象の組み合わせ列挙方法を選ぶ。
     * 機能: 2素子の大規模候補は目標値近傍探索、直列単独は和の近傍探索、それ以外は重複組み合わせを返す。
     * 入力: 値候補、目標値、部品種別、素子数、回路種別。
     * 出力: 評価対象comboを順次返す iterable。
     * 動作条件: values は value 昇順で整列済み。
     * 副作用: なし。
     */
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

    /**
     * 目的: 2素子探索で全組み合わせを避ける条件か判定する。
     * 機能: 候補数が多く、直列または並列のどちらかを評価する場合だけ近傍探索へ切り替える。
     * 入力: 値候補と回路種別配列。
     * 出力: 近傍探索を使うなら true。
     * 動作条件: mixed だけの探索では false。
     * 副作用: なし。
     */
    private function shouldUseTargetedPairSearch(array $values, array $circuitTypes): bool
    {
        return count($values) > self::POOL_LIMIT * 10
            && array_intersect($circuitTypes, ['series', 'parallel']) !== [];
    }

    /**
     * 目的: 2素子の相手値を逆算し、目標値に近い組み合わせだけを列挙する。
     * 機能: 1つ目の値ごとに必要な2つ目の値を求め、近傍窓から重複しない候補を返す。
     * 入力: 値候補、目標値、部品種別、回路種別。
     * 出力: 2素子comboを順次返す iterable。
     * 動作条件: values は value 昇順で、同じ素子値の再利用を許容する。
     * 副作用: なし。
     */
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

    /**
     * 目的: 直列または並列で目標値に届く2つ目の素子値を逆算する。
     * 機能: 抵抗直列/容量並列は和、抵抗並列/容量直列は逆数和として必要値を求める。
     * 入力: 1つ目の値、目標値、部品種別、回路種別。
     * 出力: 必要な2つ目の値。成立しない条件では null。
     * 動作条件: first と target は正の有限値。
     * 副作用: なし。
     */
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

    /**
     * 目的: 逆算した必要値の近くにある実候補を少数返す。
     * 機能: 二分探索で挿入位置を探し、指定半径分だけ前後の候補を切り出す。
     * 入力: 値候補、探す値、同一組み合わせ制御用の最小index、窓半径。
     * 出力: 近傍候補配列。
     * 動作条件: values は value 昇順。
     * 副作用: なし。
     */
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

    /**
     * 目的: 直列単独探索で、和が目標に近い候補だけを列挙する。
     * 機能: 残り素子数で必要な平均値を推定し、近傍値を再帰的に選ぶ。
     * 入力: 値候補、目標値、部品種別、残り素子数。
     * 出力: 直列comboを順次返す iterable。
     * 動作条件: values は value 昇順、length は1以上。
     * 副作用: なし。
     */
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

    /**
     * 目的: 基準値に最も近い候補値を返す。
     * 機能: 候補配列を距離順に評価し、探索の近傍候補選択に使う。
     * 入力: 候補配列と基準値。
     * 出力: 最寄り候補またはnull。
     * 動作条件: 候補valueが正の数値であること。
     * 副作用: なし。
     */
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
}

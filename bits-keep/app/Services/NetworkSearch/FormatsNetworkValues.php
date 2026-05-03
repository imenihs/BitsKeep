<?php

namespace App\Services\NetworkSearch;

trait FormatsNetworkValues
{
    /**
     * 目的: 探索結果の数値を、抵抗/容量の実務単位で読みやすく表示する。
     * 機能: 値の桁に応じて p/n/u/m/k/M などへ換算し、単位付き文字列を作る。
     * 入力: 正規化済み数値と部品種別。
     * 出力: 画面・APIでそのまま表示できる文字列。
     * 動作条件: value は有限数、partType は主に R または C。
     * 副作用: なし。
     */
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

    /**
     * 目的: 分圧比率を百分率表示へ変換する。
     * 機能: 内部比率を100倍し、桁を丸めて%を付ける。
     * 入力: 比率。
     * 出力: 百分率文字列。
     * 動作条件: ratioが有限数であること。
     * 副作用: なし。
     */
    private function formatRatio(float $ratio): string
    {
        return $this->trimNumber($ratio * 100, 4).'%';
    }

    /**
     * 目的: 分圧目標を比率または電圧比で表示する。
     * 機能: 入力/出力電圧がある場合はVout/Vin=比率の形式にする。
     * 入力: 比率と分圧条件。
     * 出力: 目標表示文字列。
     * 動作条件: paramsに電圧が任意で入ること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 分圧出力を電圧または比率で表示する。
     * 機能: 入力電圧がある場合は比率から電圧へ換算する。
     * 入力: 比率と分圧条件。
     * 出力: 出力表示文字列。
     * 動作条件: paramsにinput_voltageが任意で入ること。
     * 副作用: なし。
     */
    private function formatDividerOutput(float $ratio, array $params): string
    {
        if (isset($params['input_voltage']) && (float) $params['input_voltage'] > 0) {
            return $this->formatVoltage($ratio * (float) $params['input_voltage']);
        }

        return $this->formatRatio($ratio);
    }

    /**
     * 目的: 分圧誤差幅を電圧または比率で表示する。
     * 機能: 入力電圧がある場合は比率差から電圧差へ換算する。
     * 入力: 比率差と分圧条件。
     * 出力: 誤差表示文字列。
     * 動作条件: paramsにinput_voltageが任意で入ること。
     * 副作用: なし。
     */
    private function formatDividerOutputDelta(float $ratioDelta, array $params): string
    {
        if (isset($params['input_voltage']) && (float) $params['input_voltage'] > 0) {
            return $this->formatVoltage(abs($ratioDelta) * (float) $params['input_voltage']);
        }

        return $this->formatRatio(abs($ratioDelta));
    }

    /**
     * 目的: 電圧値をmV/V/kVの読みやすい単位へ整形する。
     * 機能: 桁に応じて単位を切り替え、有限でない値はハイフンにする。
     * 入力: 電圧値。
     * 出力: 電圧表示文字列。
     * 動作条件: valueが数値であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 符号付きの抵抗/容量差分を表示する。
     * 機能: 絶対値を部品種別表示へ変換し、正負符号を前置する。
     * 入力: 差分値と部品種別。
     * 出力: 符号付き表示文字列。
     * 動作条件: valueが数値であること。
     * 副作用: なし。
     */
    private function formatSignedValue(float $value, string $partType): string
    {
        if (! is_finite($value)) {
            return '-';
        }

        return ($value >= 0 ? '+' : '-').$this->formatValue(abs($value), $partType);
    }

    /**
     * 目的: 符号付き電圧差分を表示する。
     * 機能: 絶対値を電圧表示へ変換し、正負符号を前置する。
     * 入力: 電圧差分。
     * 出力: 符号付き電圧文字列。
     * 動作条件: valueが数値であること。
     * 副作用: なし。
     */
    private function formatSignedVoltage(float $value): string
    {
        if (! is_finite($value)) {
            return '-';
        }

        return ($value >= 0 ? '+' : '-').$this->formatVoltage(abs($value));
    }

    /**
     * 目的: 電流値をuA/mA/Aの読みやすい単位へ整形する。
     * 機能: 桁に応じて単位を切り替え、有限でない値はハイフンにする。
     * 入力: 電流値。
     * 出力: 電流表示文字列。
     * 動作条件: valueが数値であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 電力値をuW/mW/Wの読みやすい単位へ整形する。
     * 機能: 桁に応じて単位を切り替え、有限でない値はハイフンにする。
     * 入力: 電力値。
     * 出力: 電力表示文字列。
     * 動作条件: valueが数値であること。
     * 副作用: なし。
     */
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

    /**
     * 目的: 比率値を百分率表示へ変換する。
     * 機能: 内部比率を100倍し、有限でない値はハイフンにする。
     * 入力: 比率。
     * 出力: 百分率文字列。
     * 動作条件: valueが数値であること。
     * 副作用: なし。
     */
    private function formatPercent(float $value): string
    {
        return $this->trimNumber($value, 4).'%';
    }

    /**
     * 目的: 符号付き比率差を百分率表示へ変換する。
     * 機能: 絶対比率を%表示へ変換し、正負符号を前置する。
     * 入力: 比率差。
     * 出力: 符号付き百分率文字列。
     * 動作条件: valueが数値であること。
     * 副作用: なし。
     */
    private function formatSignedPercent(float $value): string
    {
        if (! is_finite($value)) {
            return '-';
        }

        return ($value >= 0 ? '+' : '-').$this->formatPercent(abs($value));
    }

    /**
     * 目的: 表示用数値の有効桁を整える。
     * 機能: toPrecision後に不要なゼロを落とし、有限でない値はハイフンにする。
     * 入力: 数値と有効桁数。
     * 出力: 丸め済み文字列。
     * 動作条件: digitsが正の整数であること。
     * 副作用: なし。
     */
    private function trimNumber(float $value, int $decimals = 6): string
    {
        $text = number_format($value, $decimals, '.', '');

        return rtrim(rtrim($text, '0'), '.');
    }

    /**
     * 目的: 候補値の重複判定キーを作る。
     * 機能: 数値を指数表記へ正規化し、float誤差による重複漏れを抑える。
     * 入力: 候補値。
     * 出力: 重複判定キー。
     * 動作条件: valueが数値であること。
     * 副作用: なし。
     */
    private function valueKey(float $value): string
    {
        return sprintf('%.12g', $value);
    }
}

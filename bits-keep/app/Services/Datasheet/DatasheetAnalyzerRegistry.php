<?php

namespace App\Services\Datasheet;

use App\Services\AppSettingService;

/**
 * 解析エンジンを識別子で引く。
 * どのエンジンを使うかは運用設定であり、利用者が解析ごとに選ぶ操作にはしないため、
 * 連携設定に保存された1つの値からエンジンを解決する。
 */
class DatasheetAnalyzerRegistry
{
    // 連携設定に保存するキー
    public const SETTING_KEY = 'datasheet.engine';

    /**
     * 目的: 選択可能なエンジンと設定サービスを受け取る。
     * 機能: エンジン実装を識別子で引ける形へ整える。
     * 入力: $analyzers は選択可能なエンジン実装、$settings は設定サービス。
     * 出力: インスタンス。
     * 動作条件: $analyzers が1件以上あること。
     * 副作用: なし。
     *
     * @param  array<int, DatasheetAnalyzer>  $analyzers
     */
    public function __construct(
        private array $analyzers,
        private AppSettingService $settings,
    ) {}

    /**
     * 目的: 現在有効なエンジンを返す。
     * 機能: 連携設定の値を優先し、未設定または不正なら既定エンジンへ落とす。
     * 入力: なし。
     * 出力: エンジン実装。
     * 動作条件: 既定エンジンが登録済みであること。
     * 副作用: DBを参照する。
     */
    public function active(): DatasheetAnalyzer
    {
        $configured = (string) $this->settings->get(self::SETTING_KEY, '');

        // 設定値が未登録、または実装が外された古い値の場合でも解析を止めない
        return $this->find($configured) ?? $this->fallback();
    }

    /**
     * 目的: 識別子からエンジンを引く。
     * 機能: 登録済みエンジンを識別子で突き合わせる。
     * 入力: $key はエンジン識別子。
     * 出力: 一致したエンジン実装、無ければ null。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function find(string $key): ?DatasheetAnalyzer
    {
        foreach ($this->analyzers as $analyzer) {
            if ($analyzer->key() === $key) {
                return $analyzer;
            }
        }

        return null;
    }

    /**
     * 目的: 選択可能なエンジンの一覧を返す。
     * 機能: 識別子、表示名、利用可否を並べる。
     * 入力: なし。
     * 出力: 連携設定の選択肢として使える配列。
     * 動作条件: なし。
     * 副作用: 各エンジンの利用可否確認により、ファイル確認や設定参照を行う。
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        $active = $this->active()->key();

        $options = [];
        foreach ($this->analyzers as $analyzer) {
            $availability = $analyzer->checkAvailability();
            $options[] = [
                'key' => $analyzer->key(),
                'label' => $analyzer->label(),
                'active' => $analyzer->key() === $active,
                'available' => (bool) ($availability['available'] ?? false),
                'message' => $availability['message'] ?? null,
            ];
        }

        return $options;
    }

    /**
     * 目的: 使用するエンジンを保存する。
     * 機能: 登録済みエンジンであることを確認して設定値へ書く。
     * 入力: $key はエンジン識別子。
     * 出力: 保存後に有効となるエンジン実装。
     * 動作条件: $key が登録済みエンジンであること。
     * 副作用: DBの設定値を更新する。
     *
     * @throws \InvalidArgumentException 未登録の識別子を渡した場合
     */
    public function setActive(string $key): DatasheetAnalyzer
    {
        $analyzer = $this->find($key);
        if ($analyzer === null) {
            throw new \InvalidArgumentException('選択できない解析エンジンです。');
        }

        $this->settings->set(self::SETTING_KEY, $analyzer->key());

        return $analyzer;
    }

    /**
     * 目的: 設定値が使えないときのエンジンを返す。
     * 機能: 設定ファイルの既定エンジンを引き、無ければ先頭の登録エンジンへ落とす。
     * 入力: なし。
     * 出力: エンジン実装。
     * 動作条件: エンジンが1件以上登録済みであること。
     * 副作用: なし。
     */
    private function fallback(): DatasheetAnalyzer
    {
        $default = (string) config('datasheet.default_engine');
        $analyzer = $this->find($default);
        if ($analyzer !== null) {
            return $analyzer;
        }

        // 既定エンジンの指定が誤っていても解析経路を失わせない
        return $this->analyzers[array_key_first($this->analyzers)];
    }
}

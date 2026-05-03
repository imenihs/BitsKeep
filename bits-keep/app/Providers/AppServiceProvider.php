<?php

namespace App\Providers;

use App\Models\Component;
use App\Models\InventoryBlock;
use App\Models\Transaction;
use App\Observers\AuditObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 目的: App Service Providerのregisterを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function register(): void {}
    /**
     * 目的: App Service Providerのbootを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function boot(): void
    {
        // 監査ログ自動記録: コア操作対象モデルを登録
        Component::observe(AuditObserver::class);
        InventoryBlock::observe(AuditObserver::class);
        Transaction::observe(AuditObserver::class);
    }
}

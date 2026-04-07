<?php

namespace App\Providers;

use App\Models\Component;
use App\Models\InventoryBlock;
use App\Models\Transaction;
use App\Observers\AuditObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // 監査ログ自動記録: コア操作対象モデルを登録
        Component::observe(AuditObserver::class);
        InventoryBlock::observe(AuditObserver::class);
        Transaction::observe(AuditObserver::class);
    }
}

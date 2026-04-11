<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('components.index'));

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn() => view('app.dashboard'))->name('dashboard');

    // 部品管理
    Route::get('/components',         fn() => view('app.components-list'))->name('components.index');
    Route::get('/components/create',  fn() => view('app.component-create'))->name('components.create');
    Route::get('/components/{id}',    fn($id) => view('app.component-detail', compact('id')))->name('components.show');
    Route::get('/components/{id}/edit', fn($id) => view('app.component-create', compact('id')))->name('components.edit');

    // マスタ管理
    Route::get('/master', fn() => view('app.master-list'))->name('master.index');

    // 在庫・棚・商社
    Route::get('/locations',   fn() => view('app.location-list'))->name('locations.index');
    Route::get('/stock-alert', fn() => view('app.stock-alert'))->name('stock.alert');
    Route::get('/stock-in', fn() => view('app.stock-in'))->name('stock.in');
    Route::get('/suppliers',   fn() => view('app.supplier-list'))->name('suppliers.index');

    // 案件管理
    Route::get('/projects', fn() => view('app.project-list'))->name('projects.index');
    Route::get('/settings/integrations', fn() => view('app.integration-settings'))->name('settings.integrations');
    Route::get('/settings/home', fn() => view('app.home-settings'))->name('settings.home');
    Route::get('/functions', fn() => view('app.function-catalog'))->name('functions.index');

    // 比較
    Route::get('/component-compare', fn() => view('app.component-compare'))->name('components.compare');

    // 設計ツール
    Route::get('/tools/calc',     fn() => view('app.engineering-calc'))->name('tools.calc');
    Route::get('/tools/design',   fn() => view('app.design-tools'))->name('tools.design');
    Route::get('/tools/network',  fn() => view('app.resistance-calc'))->name('tools.network');

    // 管理機能（admin）
    Route::middleware('role:admin')->group(function () {
        Route::get('/users',       fn() => view('app.user-list'))->name('users.index');
        Route::get('/audit-logs',  fn() => view('app.audit-log'))->name('audit.index');
        Route::get('/csv-import',  fn() => view('app.csv-import'))->name('csv.import');
        Route::get('/altium',      fn() => view('app.altium-link'))->name('altium.index');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

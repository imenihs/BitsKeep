<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('components.index'));

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn() => view('dashboard'))->name('dashboard');

    // 部品管理
    Route::get('/components',         fn() => view('app.components-list'))->name('components.index');
    Route::get('/components/create',  fn() => view('app.component-create'))->name('components.create');
    Route::get('/components/{id}',    fn($id) => view('app.component-detail', compact('id')))->name('components.show');
    Route::get('/components/{id}/edit', fn($id) => view('app.component-create', compact('id')))->name('components.edit');

    // 比較・在庫警告
    Route::get('/component-compare', fn() => view('app.component-compare'))->name('components.compare');
    Route::get('/stock-alert',        fn() => view('app.stock-alert'))->name('stock.alert');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\SpecTypeController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/**
 * BitsKeep API ルート
 * 全エンドポイントに auth ミドルウェアを適用。
 * 書き込み系は各 FormRequest の authorize() でロール検証。
 */
Route::middleware('auth')->group(function () {

    // ── マスタ管理 ──────────────────────────────────────────
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('packages',   PackageController::class);
    Route::apiResource('spec-types', SpecTypeController::class);

    // ── 部品管理 ────────────────────────────────────────────
    Route::apiResource('components', ComponentController::class);

    // セクション別 PATCH（basic / specs / suppliers）
    Route::patch(
        'components/{component}/{section}',
        [ComponentController::class, 'updateSection']
    )->where('section', 'basic|specs|suppliers');

    // 入出庫
    Route::get( 'components/{component}/transactions', [TransactionController::class, 'index']);
    Route::post('components/{component}/stock-in',     [TransactionController::class, 'stockIn']);
    Route::post('components/{component}/stock-out',    [TransactionController::class, 'stockOut']);
});

<?php

use App\Http\Controllers\Api\AltiumLinkController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ComponentCompareController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\CsvImportController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\SpecTypeController;
use App\Http\Controllers\Api\StockAlertController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/**
 * BitsKeep API ルート
 * 全エンドポイントに auth ミドルウェアを適用。
 * 書き込み系は各 FormRequest の authorize() またはコントローラ内でロール検証。
 */
Route::middleware('auth')->group(function () {

    // ── マスタ管理 ──────────────────────────────────────────
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('packages',   PackageController::class);
    Route::apiResource('spec-types', SpecTypeController::class);

    // ── 在庫・棚・商社管理 ──────────────────────────────────
    Route::apiResource('suppliers', SupplierController::class);
    Route::post('locations/inventory', [LocationController::class, 'saveInventory']);
    Route::apiResource('locations', LocationController::class);
    Route::get('stock-alerts', [StockAlertController::class, 'index']);

    // ── 部品管理 ────────────────────────────────────────────
    Route::get('components/compare', [ComponentCompareController::class, 'compare']);
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

    // 類似部品検索
    Route::get('components/{component}/similar', [ComponentCompareController::class, 'similar']);

    // Altium連携
    Route::get(   'components/{component}/altium-link', [AltiumLinkController::class, 'show']);
    Route::put(   'components/{component}/altium-link', [AltiumLinkController::class, 'upsert']);
    Route::delete('components/{component}/altium-link', [AltiumLinkController::class, 'destroy']);

    // ── Altiumライブラリ管理 ─────────────────────────────────
    Route::get(   'altium/libraries',          [AltiumLinkController::class, 'libraries']);
    Route::post(  'altium/libraries',          [AltiumLinkController::class, 'storeLibrary']);
    Route::put(   'altium/libraries/{library}', [AltiumLinkController::class, 'updateLibrary']);
    Route::delete('altium/libraries/{library}', [AltiumLinkController::class, 'destroyLibrary']);

    // ── CSVインポート ─────────────────────────────────────────
    Route::post('import/csv/preview', [CsvImportController::class, 'preview']);
    Route::post('import/csv/commit',  [CsvImportController::class, 'commit']);

    // ── ユーザー管理（admin のみ） ────────────────────────────
    Route::get(  'users',                   [UserController::class, 'index']);
    Route::post( 'users/invite',            [UserController::class, 'invite']);
    Route::patch('users/{user}/role',       [UserController::class, 'updateRole']);
    Route::patch('users/{user}/active',     [UserController::class, 'updateActive']);

    // ── 操作ログ（admin のみ） ────────────────────────────────
    Route::get('audit-logs', [AuditLogController::class, 'index']);

    // ── 案件管理 ─────────────────────────────────────────────
    Route::get('projects/options', [ProjectController::class, 'options']);
    Route::apiResource('projects', ProjectController::class);
    Route::get(   'projects/{project}/components',                   [ProjectController::class, 'listComponents']);
    Route::post(  'projects/{project}/components',                   [ProjectController::class, 'addComponent']);
    Route::patch( 'projects/{project}/components/{component}',       [ProjectController::class, 'updateComponent']);
    Route::delete('projects/{project}/components/{component}',       [ProjectController::class, 'removeComponent']);
    Route::get(   'projects/{project}/cost',                         [ProjectController::class, 'cost']);
});

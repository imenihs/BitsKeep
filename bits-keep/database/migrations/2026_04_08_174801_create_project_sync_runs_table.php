<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 目的: 対象テーブルまたは列を追加してスキーマを進める。
     * 機能: Laravel Schema APIでDB構造を定義する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: DB接続先と既存スキーマ状態がLaravel migration順序と一致していること。
     * 副作用: スキーマを変更する。
     */
    public function up(): void
    {
        Schema::create('project_sync_runs', function (Blueprint $table) {
            $table->id();
            // 実行ユーザー（手動実行時）
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            // 同期ステータス
            $table->enum('status', ['running', 'success', 'error'])->default('running');
            // 同期件数
            $table->unsignedInteger('synced_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            // エラー詳細（JSON文字列）
            $table->text('error_detail')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
    /**
     * 目的: 追加したテーブルまたは列を戻してスキーマを巻き戻す。
     * 機能: Laravel Schema APIでDB構造を定義する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: DB接続先と既存スキーマ状態がLaravel migration順序と一致していること。
     * 副作用: スキーマを変更する。
     */
    public function down(): void
    {
        Schema::dropIfExists('project_sync_runs');
    }
};

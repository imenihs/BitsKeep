<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

    public function down(): void
    {
        Schema::dropIfExists('project_sync_runs');
    }
};

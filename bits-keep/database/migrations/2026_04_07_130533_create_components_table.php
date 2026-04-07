<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer')->nullable();     // メーカー
            $table->string('part_number');                  // 型番（必須）
            $table->string('common_name')->nullable();      // 通称
            $table->text('description')->nullable();        // 説明
            // 入手可否: active=量産中, eol=EOL, last_time=在庫限り, nrnd=新規設計非推奨
            $table->enum('procurement_status', ['active', 'eol', 'last_time', 'nrnd'])->default('active');
            // 在庫（在庫計算用サマリ。inventory_blocksの集計で更新）
            $table->integer('quantity_new')->default(0);
            $table->integer('quantity_used')->default(0);
            // 発注点（新品・中古それぞれ）
            $table->integer('threshold_new')->default(0);
            $table->integer('threshold_used')->default(0);
            // ファイルパス（DBには保存せず指定フォルダ管理）
            $table->string('image_path')->nullable();
            $table->string('datasheet_path')->nullable();
            // 操作者追跡
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};

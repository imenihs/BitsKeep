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
        // 入出庫履歴
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_block_id')->nullable()->constrained('inventory_blocks')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['in', 'out', 'adjust']); // 入庫/出庫/棚卸し調整
            $table->integer('quantity');                    // 変動数量（正=増加）
            $table->integer('quantity_before');             // 変動前在庫数
            $table->integer('quantity_after');              // 変動後在庫数
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete(); // 出庫時プロジェクト紐づけ
            $table->text('note')->nullable();               // 備考
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
        Schema::dropIfExists('transactions');
    }
};

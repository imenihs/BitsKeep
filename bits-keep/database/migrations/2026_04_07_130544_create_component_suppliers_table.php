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
        Schema::create('component_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_part_number')->nullable(); // 商社管理型番
            $table->string('product_url')->nullable();          // 商品ページURL
            $table->decimal('unit_price', 10, 4)->nullable();   // 単価（最新）
            $table->date('price_updated_at')->nullable();       // 価格更新日
            $table->boolean('is_preferred')->default(false);    // 優先仕入先
            $table->timestamps();
            $table->unique(['component_id', 'supplier_id']);
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
        Schema::dropIfExists('component_suppliers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 数量条件ごとの価格ブレーク（1個=100円、10個=80円 等）
        Schema::create('supplier_price_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_supplier_id')->constrained('component_suppliers')->cascadeOnDelete();
            $table->integer('min_qty');                     // 最小数量
            $table->decimal('unit_price', 10, 4);           // その数量での単価
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_breaks');
    }
};

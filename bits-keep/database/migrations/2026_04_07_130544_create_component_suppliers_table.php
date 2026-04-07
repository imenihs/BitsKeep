<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

    public function down(): void
    {
        Schema::dropIfExists('component_suppliers');
    }
};

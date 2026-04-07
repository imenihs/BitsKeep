<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();           // 商社名
            $table->string('url')->nullable();          // サイトURL
            $table->string('color', 7)->nullable();     // 識別カラー（#rrggbb）
            $table->integer('lead_days')->nullable();   // 標準納期（日）
            $table->decimal('free_shipping_threshold', 10, 2)->nullable(); // 送料無料閾値
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // キーバリュー属性のキー候補マスタ
        Schema::create('attribute_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();        // 属性キー名（例: 温度特性, 許容差）
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_keys');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // スペック種別定義（例: 抵抗値, 容量, 電圧）
        Schema::create('spec_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();           // 種別名（例: 抵抗値）
            $table->string('base_unit')->nullable();    // 基準単位（例: Ω）
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // スペック種別に紐づく単位候補と変換倍率
        Schema::create('spec_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spec_type_id')->constrained()->cascadeOnDelete();
            $table->string('unit');                     // 単位表示（例: kΩ, MΩ）
            $table->decimal('factor', 20, 10)->default(1); // 基準単位への変換倍率
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spec_units');
        Schema::dropIfExists('spec_types');
    }
};

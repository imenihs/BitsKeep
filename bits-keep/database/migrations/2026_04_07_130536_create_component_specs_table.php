<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spec_type_id')->constrained()->cascadeOnDelete();
            $table->string('value')->nullable();            // 表示値（例: "10k"）
            $table->string('unit')->nullable();             // 表示単位（例: "kΩ"）
            $table->decimal('value_numeric', 20, 10)->nullable(); // 基準単位換算値（数値範囲検索用）
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_specs');
    }
};

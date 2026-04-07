<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->string('key');      // 属性キー（attribute_keysのkeyを参照、FK強制はしない）
            $table->text('value');      // 属性値
            $table->timestamps();
            $table->index(['component_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_attributes');
    }
};

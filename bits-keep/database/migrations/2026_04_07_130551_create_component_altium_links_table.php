<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Altiumライブラリ登録テーブル（ライブラリパス・シンボル名）
        Schema::create('altium_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // ライブラリ名
            $table->enum('type', ['SchLib', 'PcbLib']);     // 種別
            $table->string('path');                         // ファイルパス
            $table->integer('component_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('component_altium_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sch_library_id')->nullable()->constrained('altium_libraries')->nullOnDelete();
            $table->string('sch_symbol')->nullable();       // シンボル名
            $table->foreignId('pcb_library_id')->nullable()->constrained('altium_libraries')->nullOnDelete();
            $table->string('pcb_footprint')->nullable();    // フットプリント名
            $table->timestamps();
            $table->unique('component_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_altium_links');
        Schema::dropIfExists('altium_libraries');
    }
};

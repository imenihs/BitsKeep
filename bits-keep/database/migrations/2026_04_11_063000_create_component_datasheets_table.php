<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_datasheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        DB::table('components')
            ->whereNotNull('datasheet_path')
            ->orderBy('id')
            ->chunkById(100, function ($components) {
                $rows = [];
                foreach ($components as $component) {
                    $rows[] = [
                        'component_id' => $component->id,
                        'file_path' => $component->datasheet_path,
                        'original_name' => basename($component->datasheet_path),
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($rows !== []) {
                    DB::table('component_datasheets')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_datasheets');
    }
};

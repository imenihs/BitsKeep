<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_project', function (Blueprint $table) {
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->integer('required_qty')->default(1);    // 必要数量
            $table->primary(['component_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_project');
    }
};

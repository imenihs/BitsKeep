<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('tool_id');
            $table->string('title');
            $table->string('verdict')->nullable();
            $table->text('summary')->nullable();
            $table->json('input_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->json('candidate_links')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('component_id')->nullable()->constrained('components')->nullOnDelete();
            $table->string('bom_line_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tool_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index(['component_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_sessions');
    }
};

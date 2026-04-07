<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // 操作種別: create / update / delete / stock_in / stock_out / adjust
            $table->string('action');
            // 対象リソース（例: components, inventory_blocks）
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            // 変更差分（update時: {"before":{...},"after":{...}}）
            $table->json('diff')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');    // updated_atは不要
            $table->index(['resource_type', 'resource_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

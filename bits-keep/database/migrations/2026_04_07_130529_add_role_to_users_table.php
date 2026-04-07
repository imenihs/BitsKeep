<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 権限ロール: admin / editor / viewer
            $table->enum('role', ['admin', 'editor', 'viewer'])->default('viewer')->after('email');
            // 招待・有効化管理
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('invited_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'invited_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 事業コード（例: 010, 011）・事業名（例: 綾電クリエイト）
            $table->string('business_code', 10)->nullable()->after('color');
            $table->string('business_name', 255)->nullable()->after('business_code');
            // 案件ソース種別: local=BitsKeep独自, notion=Notion由来（参照専用）
            $table->enum('source_type', ['local', 'notion'])->default('local')->after('business_name');
            // Notion由来: ページID / 独自案件: UUID。source_type+source_keyで一意
            $table->string('source_key', 255)->nullable()->unique()->after('source_type');
            // 外部案件番号（例: AYD-2025-014）
            $table->string('external_code', 100)->nullable()->after('source_key');
            // Notion ページURL
            $table->string('external_url', 500)->nullable()->after('external_code');
            // false=Notion由来（読み取り専用）、true=独自案件（編集可）
            $table->boolean('is_editable')->default(true)->after('external_url');
            // Notion同期状態
            $table->enum('sync_state', ['synced', 'pending', 'error'])->nullable()->after('is_editable');
            $table->timestamp('last_synced_at')->nullable()->after('sync_state');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'business_code', 'business_name', 'source_type', 'source_key',
                'external_code', 'external_url', 'is_editable', 'sync_state', 'last_synced_at',
            ]);
        });
    }
};

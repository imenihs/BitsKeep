<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spec_types', function (Blueprint $table) {
            // 入力候補接頭辞: ["G","M","k","","m","u","n","p"] のような配列。null は全汎用SI候補へフォールバック
            $table->jsonb('suggest_prefixes')->nullable()->after('symbol');
            // 表示接頭辞: 逆変換時に選択を制約する接頭辞配列。null は自動選択
            $table->jsonb('display_prefixes')->nullable()->after('suggest_prefixes');
        });
    }

    public function down(): void
    {
        Schema::table('spec_types', function (Blueprint $table) {
            $table->dropColumn(['suggest_prefixes', 'display_prefixes']);
        });
    }
};

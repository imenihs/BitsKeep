<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spec_types', function (Blueprint $table) {
            $table->string('spec_kind', 20)->default('normal')->index();
            $table->jsonb('tolerance_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('spec_types', function (Blueprint $table) {
            $table->dropIndex(['spec_kind']);
            $table->dropColumn(['spec_kind', 'tolerance_settings']);
        });
    }
};

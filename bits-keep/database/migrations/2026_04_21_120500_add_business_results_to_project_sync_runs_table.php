<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_sync_runs', function (Blueprint $table) {
            $table->json('business_results')->nullable()->after('error_detail');
        });
    }

    public function down(): void
    {
        Schema::table('project_sync_runs', function (Blueprint $table) {
            $table->dropColumn('business_results');
        });
    }
};

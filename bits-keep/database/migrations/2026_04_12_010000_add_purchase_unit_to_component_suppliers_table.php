<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('component_suppliers', function (Blueprint $table) {
            $table->string('purchase_unit', 50)->nullable()->after('product_url');
        });
    }

    public function down(): void
    {
        Schema::table('component_suppliers', function (Blueprint $table) {
            $table->dropColumn('purchase_unit');
        });
    }
};

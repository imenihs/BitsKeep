<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spec_types', function (Blueprint $table) {
            $table->string('name_ja', 120)->nullable()->after('name');
            $table->string('name_en', 160)->nullable()->after('name_ja');
            $table->string('symbol', 80)->nullable()->after('name_en');
        });

        DB::table('spec_types')
            ->whereNull('name_ja')
            ->update(['name_ja' => DB::raw('name')]);

        Schema::create('spec_type_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spec_type_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 160);
            $table->string('locale', 16)->nullable();
            $table->string('kind', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['spec_type_id', 'alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spec_type_aliases');

        Schema::table('spec_types', function (Blueprint $table) {
            $table->dropColumn(['name_ja', 'name_en', 'symbol']);
        });
    }
};

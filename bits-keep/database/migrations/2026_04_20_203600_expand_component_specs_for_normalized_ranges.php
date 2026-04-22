<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('component_specs', function (Blueprint $table) {
            $table->string('value_mode', 10)->default('single')->after('unit');
            $table->decimal('value_numeric_min', 30, 15)->nullable()->after('value_numeric');
            $table->decimal('value_numeric_max', 30, 15)->nullable()->after('value_numeric_min');
            $table->string('normalized_unit', 20)->nullable()->after('value_numeric_max');
        });

        $baseUnits = DB::table('spec_types')->pluck('base_unit', 'id');

        DB::table('component_specs')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($baseUnits) {
                foreach ($rows as $row) {
                    $normalizedUnit = $baseUnits[$row->spec_type_id] ?? ($row->unit ?: null);

                    DB::table('component_specs')
                        ->where('id', $row->id)
                        ->update([
                            'value_mode' => 'single',
                            'value_numeric_min' => $row->value_numeric,
                            'value_numeric_max' => $row->value_numeric,
                            'normalized_unit' => $normalizedUnit,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('component_specs', function (Blueprint $table) {
            $table->dropColumn(['value_mode', 'value_numeric_min', 'value_numeric_max', 'normalized_unit']);
        });
    }
};

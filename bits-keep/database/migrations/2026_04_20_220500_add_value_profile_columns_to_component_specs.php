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
            $table->string('value_profile', 20)->default('typ')->after('unit');
            $table->decimal('value_numeric_typ', 30, 15)->nullable()->after('value_numeric');
        });

        DB::table('component_specs')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $isLegacyRange = ($row->value_mode ?? null) === 'range'
                        && $row->value_numeric_min !== null
                        && $row->value_numeric_max !== null;

                    if ($isLegacyRange) {
                        DB::table('component_specs')
                            ->where('id', $row->id)
                            ->update([
                                'value_profile' => 'range',
                                'value_numeric_typ' => null,
                            ]);

                        continue;
                    }

                    $typValue = $row->value_numeric ?? $row->value_numeric_min ?? $row->value_numeric_max;

                    DB::table('component_specs')
                        ->where('id', $row->id)
                        ->update([
                            'value_profile' => 'typ',
                            'value_numeric_typ' => $typValue,
                            'value_numeric_min' => null,
                            'value_numeric_max' => null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('component_specs', function (Blueprint $table) {
            $table->dropColumn(['value_profile', 'value_numeric_typ']);
        });
    }
};

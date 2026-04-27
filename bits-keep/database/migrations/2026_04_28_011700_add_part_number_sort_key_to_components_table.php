<?php

use App\Models\Component;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->string('part_number_sort_key')->nullable()->after('part_number')->index();
        });

        Component::withTrashed()
            ->select(['id', 'part_number'])
            ->chunkById(200, function ($components) {
                foreach ($components as $component) {
                    DB::table('components')
                        ->where('id', $component->id)
                        ->update([
                            'part_number_sort_key' => Component::buildPartNumberSortKey($component->part_number),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropIndex(['part_number_sort_key']);
            $table->dropColumn('part_number_sort_key');
        });
    }
};

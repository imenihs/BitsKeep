<?php

use App\Models\Component;
use App\Models\Package;
use App\Models\PackageGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('package_group_id')->nullable()->after('id')->constrained('package_groups')->nullOnDelete();
        });

        Schema::table('components', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('primary_location_id')->constrained('packages')->nullOnDelete();
        });

        $defaultGroupId = PackageGroup::query()->create([
            'name' => '未分類',
            'description' => '旧データ移行時の一時分類',
            'sort_order' => 9999,
        ])->id;

        Package::query()->whereNull('package_group_id')->update(['package_group_id' => $defaultGroupId]);

        $pivotRows = DB::table('component_package')
            ->join('packages', 'packages.id', '=', 'component_package.package_id')
            ->select('component_package.component_id', 'component_package.package_id', 'packages.sort_order', 'packages.name')
            ->orderBy('component_package.component_id')
            ->orderBy('packages.sort_order')
            ->orderBy('packages.name')
            ->orderBy('packages.id')
            ->get()
            ->groupBy('component_id');

        foreach ($pivotRows as $componentId => $rows) {
            $packageId = $rows->first()->package_id ?? null;
            if ($packageId) {
                Component::query()->whereKey($componentId)->update(['package_id' => $packageId]);
            }
        }

        Schema::dropIfExists('component_package');
    }

    public function down(): void
    {
        Schema::create('component_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['component_id', 'package_id']);
        });

        $components = Component::query()->whereNotNull('package_id')->get(['id', 'package_id']);
        foreach ($components as $component) {
            DB::table('component_package')->insert([
                'component_id' => $component->id,
                'package_id' => $component->package_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_group_id');
        });

        Schema::dropIfExists('package_groups');
    }
};

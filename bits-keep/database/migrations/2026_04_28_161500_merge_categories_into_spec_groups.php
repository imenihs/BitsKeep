<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_spec_group', function (Blueprint $table) {
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spec_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['component_id', 'spec_group_id']);
        });

        $categoryToSpecGroupIds = [];

        if (Schema::hasTable('categories')) {
            $categories = DB::table('categories')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            foreach ($categories as $category) {
                $linkedGroupIds = Schema::hasTable('category_spec_group')
                    ? DB::table('category_spec_group')
                        ->where('category_id', $category->id)
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->pluck('spec_group_id')
                        ->map(fn ($id) => (int) $id)
                        ->filter()
                        ->values()
                        ->all()
                    : [];

                if ($linkedGroupIds === []) {
                    $existingGroup = DB::table('spec_groups')->where('name', $category->name)->first();
                    $groupId = $existingGroup?->id ?? DB::table('spec_groups')->insertGetId([
                        'name' => $category->name,
                        'description' => $category->description ?? null,
                        'sort_order' => $category->sort_order ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $linkedGroupIds = [(int) $groupId];
                }

                $categoryToSpecGroupIds[(int) $category->id] = $linkedGroupIds;
            }
        }

        if (Schema::hasTable('component_category')) {
            DB::table('component_category')
                ->orderBy('component_id')
                ->get()
                ->each(function ($row) use ($categoryToSpecGroupIds) {
                    foreach ($categoryToSpecGroupIds[(int) $row->category_id] ?? [] as $specGroupId) {
                        DB::table('component_spec_group')->updateOrInsert(
                            [
                                'component_id' => (int) $row->component_id,
                                'spec_group_id' => (int) $specGroupId,
                            ],
                            [
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                });
        }

        Schema::dropIfExists('category_spec_group');
        Schema::dropIfExists('component_category');
        Schema::dropIfExists('categories');
    }

    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 7)->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('component_category', function (Blueprint $table) {
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['component_id', 'category_id']);
        });

        Schema::create('category_spec_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spec_group_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['category_id', 'spec_group_id']);
        });

        $groupToCategoryId = [];
        DB::table('spec_groups')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function ($group) use (&$groupToCategoryId) {
                $categoryId = DB::table('categories')->insertGetId([
                    'name' => $group->name,
                    'description' => $group->description,
                    'sort_order' => $group->sort_order ?? 0,
                    'deleted_at' => $group->deleted_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $groupToCategoryId[(int) $group->id] = (int) $categoryId;

                DB::table('category_spec_group')->insert([
                    'category_id' => (int) $categoryId,
                    'spec_group_id' => (int) $group->id,
                    'sort_order' => 0,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        if (Schema::hasTable('component_spec_group')) {
            DB::table('component_spec_group')->get()->each(function ($row) use ($groupToCategoryId) {
                $categoryId = $groupToCategoryId[(int) $row->spec_group_id] ?? null;
                if (! $categoryId) {
                    return;
                }

                DB::table('component_category')->updateOrInsert([
                    'component_id' => (int) $row->component_id,
                    'category_id' => (int) $categoryId,
                ]);
            });
        }

        Schema::dropIfExists('component_spec_group');
    }
};

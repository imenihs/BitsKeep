<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $commonSpecNames = ['動作温度', '保存温度', '端子数', '端子ピッチ'];

    public function up(): void
    {
        Schema::table('spec_types', function (Blueprint $table) {
            $table->string('spec_scope', 32)->default('group_local')->after('display_prefixes');
            $table->foreignId('owner_spec_group_id')->nullable()->after('spec_scope')->constrained('spec_groups')->nullOnDelete();
            $table->index(['spec_scope', 'owner_spec_group_id']);
        });

        $this->markCommonSpecTypes();
        $this->assignOwnerGroups();
        $this->removeLegacyCommonGroup();
    }

    public function down(): void
    {
        $commonGroupId = $this->restoreLegacyCommonGroup();

        if ($commonGroupId !== null) {
            $commonSpecIds = DB::table('spec_types')
                ->where('spec_scope', 'common')
                ->pluck('id');

            foreach ($commonSpecIds as $index => $specTypeId) {
                DB::table('spec_group_spec_type')->updateOrInsert(
                    [
                        'spec_group_id' => $commonGroupId,
                        'spec_type_id' => $specTypeId,
                    ],
                    [
                        'sort_order' => ($index + 1) * 10,
                        'is_required' => $index < 2,
                        'is_recommended' => true,
                        'default_profile' => 'typ',
                        'default_unit' => DB::table('spec_types')->where('id', $specTypeId)->value('base_unit'),
                        'note' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        Schema::table('spec_types', function (Blueprint $table) {
            $table->dropIndex(['spec_scope', 'owner_spec_group_id']);
            $table->dropConstrainedForeignId('owner_spec_group_id');
            $table->dropColumn('spec_scope');
        });
    }

    private function markCommonSpecTypes(): void
    {
        $legacyCommonSpecTypeIds = DB::table('spec_group_spec_type')
            ->join('spec_groups', 'spec_groups.id', '=', 'spec_group_spec_type.spec_group_id')
            ->where('spec_groups.name', '共通')
            ->pluck('spec_group_spec_type.spec_type_id');

        if ($legacyCommonSpecTypeIds->isNotEmpty()) {
            DB::table('spec_types')
                ->whereIn('id', $legacyCommonSpecTypeIds)
                ->update([
                    'spec_scope' => 'common',
                    'owner_spec_group_id' => null,
                    'updated_at' => now(),
                ]);
        }

        DB::table('spec_types')
            ->whereIn('name', $this->commonSpecNames)
            ->update([
                'spec_scope' => 'common',
                'owner_spec_group_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function assignOwnerGroups(): void
    {
        $ownerRows = DB::table('spec_group_spec_type')
            ->join('spec_groups', 'spec_groups.id', '=', 'spec_group_spec_type.spec_group_id')
            ->where('spec_groups.name', '!=', '共通')
            ->whereNull('spec_groups.deleted_at')
            ->select('spec_group_spec_type.spec_type_id', DB::raw('MIN(spec_group_spec_type.spec_group_id) as owner_spec_group_id'))
            ->groupBy('spec_group_spec_type.spec_type_id')
            ->get();

        foreach ($ownerRows as $row) {
            DB::table('spec_types')
                ->where('id', $row->spec_type_id)
                ->where('spec_scope', '!=', 'common')
                ->whereNull('owner_spec_group_id')
                ->update([
                    'owner_spec_group_id' => $row->owner_spec_group_id,
                    'updated_at' => now(),
                ]);
        }
    }

    private function removeLegacyCommonGroup(): void
    {
        $commonGroupIds = DB::table('spec_groups')
            ->where('name', '共通')
            ->pluck('id');

        foreach ($commonGroupIds as $groupId) {
            DB::table('spec_templates')
                ->where('spec_group_id', $groupId)
                ->update(['spec_group_id' => null, 'updated_at' => now()]);

            DB::table('spec_group_spec_type')
                ->where('spec_group_id', $groupId)
                ->delete();

            DB::table('category_spec_group')
                ->where('spec_group_id', $groupId)
                ->delete();

            DB::table('spec_groups')
                ->where('id', $groupId)
                ->delete();
        }
    }

    private function restoreLegacyCommonGroup(): ?int
    {
        $existing = DB::table('spec_groups')->where('name', '共通')->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return DB::table('spec_groups')->insertGetId([
            'name' => '共通',
            'description' => '多くの部品分類で共通して使う基本スペック',
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};

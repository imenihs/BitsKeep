<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $legacyCommonSpecNames = ['電源電圧', '動作温度', '保存温度', '端子数', '端子ピッチ', '全損失'];

    public function up(): void
    {
        DB::table('spec_types')
            ->whereIn('name', $this->legacyCommonSpecNames)
            ->update([
                'spec_scope' => 'common',
                'owner_spec_group_id' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('spec_types')
            ->whereIn('name', $this->legacyCommonSpecNames)
            ->where('spec_scope', 'common')
            ->update([
                'spec_scope' => 'group_local',
                'updated_at' => now(),
            ]);
    }
};

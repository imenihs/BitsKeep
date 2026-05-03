<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $legacyCommonSpecNames = ['電源電圧', '動作温度', '保存温度', '端子数', '端子ピッチ', '全損失'];
    /**
     * 目的: 対象テーブルまたは列を追加してスキーマを進める。
     * 機能: Laravel Schema APIでDB構造を定義する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: DB接続先と既存スキーマ状態がLaravel migration順序と一致していること。
     * 副作用: スキーマを変更する。
     */
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
    /**
     * 目的: 追加したテーブルまたは列を戻してスキーマを巻き戻す。
     * 機能: Laravel Schema APIでDB構造を定義する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: DB接続先と既存スキーマ状態がLaravel migration順序と一致していること。
     * 副作用: スキーマを変更する。
     */
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

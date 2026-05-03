<?php

use App\Models\Component;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
        Schema::table('components', function (Blueprint $table) {
            $table->dropIndex(['part_number_sort_key']);
            $table->dropColumn('part_number_sort_key');
        });
    }
};

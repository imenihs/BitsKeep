<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        // 在庫物理単位（ロット・リール単位での管理）
        Schema::create('inventory_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            // 在庫区分: reel=リール, tape=テープ, tray=トレイ, loose=バラ, box=箱
            $table->enum('stock_type', ['reel', 'tape', 'tray', 'loose', 'box'])->default('loose');
            $table->enum('condition', ['new', 'used'])->default('new'); // 新品/中古
            $table->integer('quantity')->default(0);        // 数量
            $table->string('lot_number')->nullable();       // ロット番号
            $table->string('reel_code')->nullable();        // リール番号（stock_type=reelのみ）
            $table->text('note')->nullable();
            $table->timestamps();
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
        Schema::dropIfExists('inventory_blocks');
    }
};

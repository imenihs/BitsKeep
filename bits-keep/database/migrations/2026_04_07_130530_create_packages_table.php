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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();       // パッケージ名（例: 0402, SOT-23）
            $table->string('description')->nullable();
            $table->decimal('size_x', 8, 4)->nullable(); // 縦 mm
            $table->decimal('size_y', 8, 4)->nullable(); // 横 mm
            $table->decimal('size_z', 8, 4)->nullable(); // 高さ mm
            $table->string('image_path')->nullable();    // 外観画像
            $table->string('model_path')->nullable();    // 3Dモデルパス
            $table->string('pdf_path')->nullable();      // 寸法図PDF
            $table->integer('sort_order')->default(0);
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
        Schema::dropIfExists('packages');
    }
};

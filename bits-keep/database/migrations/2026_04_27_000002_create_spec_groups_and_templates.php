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
        Schema::create('spec_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('spec_group_spec_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spec_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spec_type_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_recommended')->default(true);
            $table->string('default_profile', 32)->nullable();
            $table->string('default_unit', 40)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['spec_group_id', 'spec_type_id']);
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

        Schema::create('spec_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spec_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('spec_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spec_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spec_type_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('default_profile', 32)->nullable();
            $table->string('default_unit', 40)->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['spec_template_id', 'spec_type_id']);
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
        Schema::dropIfExists('spec_template_items');
        Schema::dropIfExists('spec_templates');
        Schema::dropIfExists('category_spec_group');
        Schema::dropIfExists('spec_group_spec_type');
        Schema::dropIfExists('spec_groups');
    }
};

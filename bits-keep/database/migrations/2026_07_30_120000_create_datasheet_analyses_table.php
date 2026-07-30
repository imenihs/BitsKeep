<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 目的: データシート解析ジョブの状態と結果を保持するテーブルを追加する。
     * 機能: Laravel Schema APIでDB構造を定義する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: DB接続先と既存スキーマ状態がLaravel migration順序と一致していること。
     * 副作用: スキーマを変更する。
     */
    public function up(): void
    {
        Schema::create('datasheet_analyses', function (Blueprint $table) {
            $table->id();
            // 画面へ渡す外部識別子。連番を露出させないためUUIDで参照する
            $table->uuid('public_id')->unique();
            // 解析を開始した利用者。利用者が消えても解析履歴は残す
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // 解析対象の一時PDFトークン。TempDatasheetService の保管単位と対応する
            $table->string('temp_token', 64)->index();
            // 解析に使ったエンジン識別子（codex / gemini）
            $table->string('engine', 32);
            // 状態: queued / preparing / running / succeeded / failed
            $table->string('state', 16)->index();
            // 失敗種別。画面の案内文と再試行可否の判断に使う
            $table->string('failure_kind', 32)->nullable();
            // 利用者へ見せる失敗理由
            $table->text('failure_message')->nullable();
            // PDFの渡し方（text / image）と渡したページ数。所要時間の調査に使う
            $table->string('input_mode', 16)->nullable();
            $table->unsignedSmallInteger('input_page_count')->nullable();
            // 正規化済みの解析結果。スペック詳細照合と推薦まで通した状態で保持する
            $table->json('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // 画面の状態取得は「自分の直近の解析」を引くため、この組で引けるようにする
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * 目的: 追加したテーブルを戻してスキーマを巻き戻す。
     * 機能: Laravel Schema APIでDB構造を定義する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: DB接続先と既存スキーマ状態がLaravel migration順序と一致していること。
     * 副作用: スキーマを変更する。
     */
    public function down(): void
    {
        Schema::dropIfExists('datasheet_analyses');
    }
};

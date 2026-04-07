<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();           // 棚コード（例: A-1, B-3）
            $table->string('name')->nullable();         // 棚名称（任意）
            $table->string('group')->nullable();        // グループ（例: A棚, B棚）
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete(); // 階層構造
            $table->string('qr_code')->nullable();      // QRコード文字列
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};

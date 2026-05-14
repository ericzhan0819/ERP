<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            // 技術註解：第一階段僅建立最小租戶主體欄位，避免提前引入完整多租戶複雜度。
            $table->string('name');
            // 技術註解：以唯一代碼作為穩定識別，降低後續以名稱比對造成衝突的風險。
            $table->string('code')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};


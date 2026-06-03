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
        Schema::create('customer_number_sequences', function (Blueprint $table): void {
            $table->id();
            // 技術註解：客戶編號以公司為流水邊界，避免不同租戶共用序號導致資料可推測或混淆。
            $table->unsignedBigInteger('company_id');
            // 技術註解：period 固定為 YYYYMM，支援每月重置流水且維持可讀性。
            $table->string('period', 6);
            // 技術註解：next_number 必須搭配交易鎖遞增，避免併發建立客戶時產生重複編號。
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'period'], 'customer_sequences_company_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_number_sequences');
    }
};


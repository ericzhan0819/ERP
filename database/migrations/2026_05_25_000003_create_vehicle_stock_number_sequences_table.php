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
        Schema::create('vehicle_stock_number_sequences', function (Blueprint $table): void {
            $table->id();
            // 技術註解：以 company_id + period 作為流水號邊界，確保不同公司可各自從 0001 起算，避免跨租戶號碼污染。
            $table->unsignedBigInteger('company_id');
            // 技術註解：period 採 YYYYMM，對應需求中的月度流水號分段。
            $table->string('period', 6);
            // 技術註解：next_number 儲存下一個可用序號，透過交易鎖控制可避免併發重複配號。
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'period'], 'vehicle_stock_sequences_company_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_stock_number_sequences');
    }
};


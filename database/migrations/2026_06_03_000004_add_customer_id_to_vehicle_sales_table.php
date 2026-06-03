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
        Schema::table('vehicle_sales', function (Blueprint $table): void {
            // 技術註解：customer_id 僅作主檔關聯，仍保留交易當下快照欄位以避免客戶資料異動影響歷史銷售紀錄。
            $table->foreignId('customer_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('customers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};

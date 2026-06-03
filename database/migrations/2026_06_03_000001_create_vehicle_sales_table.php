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
        Schema::create('vehicle_sales', function (Blueprint $table) {
            $table->id();

            // 技術註解：銷售紀錄包含客戶與佣金敏感資訊，必須保留 company/branch 雙租戶邊界避免 IDOR。
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('vehicle_id');

            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 50)->nullable();
            // 技術註解：金額欄位使用 decimal 保持財務精度，避免 float 四捨五入誤差影響銷售紀錄。
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->string('sale_status', 32);
            $table->dateTime('sold_at')->nullable();
            $table->string('salesperson_name')->nullable();
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            // 技術註解：銷售紀錄需保留可追溯性，不因車輛刪除自動 cascade。
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();

            $table->index(['company_id', 'branch_id', 'vehicle_id'], 'vehicle_sales_company_branch_vehicle_index');
            $table->index(['company_id', 'sale_status'], 'vehicle_sales_company_status_index');
            $table->index(['company_id', 'sold_at'], 'vehicle_sales_company_sold_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_sales');
    }
};

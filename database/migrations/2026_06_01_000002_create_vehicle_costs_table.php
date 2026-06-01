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
        Schema::create('vehicle_costs', function (Blueprint $table) {
            $table->id();

            // 技術註解：成本資料屬於敏感財務資訊，必須保留 company/branch 雙租戶邊界，避免跨租戶 IDOR。
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('vehicle_id');

            // 技術註解：成本類型以後端白名單控管，避免前端任意值破壞統計口徑。
            $table->string('cost_type', 32);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('cost_date');
            $table->string('vendor_name', 120)->nullable();
            $table->string('payment_status', 32);
            $table->timestamp('paid_at')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // 技術註解：成本紀錄不可因車輛刪除自動 cascade，使用 restrict 保留財務可追溯性。
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();

            $table->index(['company_id', 'branch_id', 'vehicle_id'], 'vehicle_costs_company_branch_vehicle_index');
            $table->index(['company_id', 'cost_type'], 'vehicle_costs_company_cost_type_index');
            $table->index(['company_id', 'payment_status'], 'vehicle_costs_company_payment_status_index');
            $table->index(['company_id', 'cost_date'], 'vehicle_costs_company_cost_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_costs');
    }
};


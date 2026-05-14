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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            // 技術註解：租戶邊界核心欄位，所有查詢必須以 company_id 做第一層隔離以避免跨公司 IDOR。
            $table->unsignedBigInteger('company_id');
            // 技術註解：分店邊界欄位，供 branch scoped 使用者做第二層資料隔離。
            $table->unsignedBigInteger('branch_id');

            // 技術註解：庫存編號需在公司範圍內唯一，避免跨分店資料衝突與誤綁定。
            $table->string('stock_number');
            $table->string('vin', 32)->nullable();
            $table->string('license_plate', 32)->nullable();

            $table->string('brand');
            $table->string('model');
            $table->string('variant')->nullable();
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->string('exterior_color')->nullable();
            $table->string('interior_color')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();

            // 技術註解：生命週期狀態只承載車輛流程，不預先混入採購/會計/佣金語意。
            $table->string('lifecycle_status', 32)->default('draft');
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'stock_number'], 'vehicles_company_stock_unique');
            $table->index(['company_id', 'branch_id'], 'vehicles_company_branch_index');
            $table->index(['company_id', 'lifecycle_status'], 'vehicles_company_status_index');
            $table->index(['branch_id', 'lifecycle_status'], 'vehicles_branch_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};


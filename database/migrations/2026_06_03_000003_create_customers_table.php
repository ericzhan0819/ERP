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
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            // 技術註解：公司與分店是客戶主檔的租戶邊界，所有讀寫皆需後端 scoped 查詢防止 IDOR。
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            // 技術註解：客戶編號由後端服務產生並在公司範圍唯一，前端不可覆寫。
            $table->string('customer_number');
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('secondary_phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('line_id', 100)->nullable();
            // 技術註解：以下三欄為敏感個資，允許資料存在但所有 Controller payload 必須依個資權限白名單輸出。
            $table->string('id_number', 50)->nullable();
            $table->date('birthday')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('status', 32)->default('lead');
            $table->string('source', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'customer_number'], 'customers_company_number_unique');
            $table->index(['company_id', 'branch_id'], 'customers_company_branch_index');
            $table->index(['company_id', 'status'], 'customers_company_status_index');
            $table->index(['branch_id', 'status'], 'customers_branch_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};


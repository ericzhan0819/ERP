<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('vehicle_sale_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('payment_number');
            $table->string('payment_type', 32);
            $table->string('payment_method', 32);
            $table->decimal('amount', 12, 2);
            $table->date('paid_at')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('status', 32)->default('received');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
            $table->foreign('vehicle_sale_id')->references('id')->on('vehicle_sales')->restrictOnDelete();

            $table->index(['company_id', 'branch_id', 'vehicle_sale_id'], 'vsp_company_branch_sale_idx');
            $table->unique(['company_id', 'payment_number'], 'vsp_company_payment_number_unique');
            $table->index(['company_id', 'status'], 'vsp_company_status_idx');
            $table->index(['company_id', 'paid_at'], 'vsp_company_paid_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_sale_payments');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('type', 32);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->unique(['company_id', 'code'], 'accounting_accounts_company_code_unique');
            $table->index('company_id', 'accounting_accounts_company_idx');
            $table->index('branch_id', 'accounting_accounts_branch_idx');
            $table->index('type', 'accounting_accounts_type_idx');
            $table->index('is_active', 'accounting_accounts_is_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_accounts');
    }
};
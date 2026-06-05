<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('journal_number', 40);
            $table->date('entry_date');
            $table->string('summary')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->text('attachment')->nullable();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // 技術註解：company scoped unique journal number 可避免跨租戶共享流水號造成錯帳或 IDOR 推測。
            $table->unique(['company_id', 'journal_number'], 'accounting_journal_entries_company_number_unique');
            $table->index('company_id', 'accounting_journal_entries_company_idx');
            $table->index('branch_id', 'accounting_journal_entries_branch_idx');
            $table->index('status', 'accounting_journal_entries_status_idx');
            $table->index('entry_date', 'accounting_journal_entries_entry_date_idx');
            $table->index(['source_type', 'source_id'], 'accounting_journal_entries_source_idx');

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_entries');
    }
};
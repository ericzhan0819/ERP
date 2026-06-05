<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('memo')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // 技術註解：分錄索引需偏向 journal_entry_id 與 account_id，便於草稿編輯與科目關聯查詢。
            $table->index('journal_entry_id', 'accounting_journal_lines_entry_idx');
            $table->index('account_id', 'accounting_journal_lines_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_entry_lines');
    }
};
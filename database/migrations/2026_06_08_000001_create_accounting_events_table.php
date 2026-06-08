<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_number', 80)->nullable();
            $table->string('event_type', 80);
            $table->date('event_date');
            $table->string('status', 20)->default('pending');
            $table->string('currency', 3)->default('TWD');
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('payload')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            // 技術註解：本階段只建立可 scoped 查詢的 foundation；source_id 可為 null，避免跨資料庫 nullable unique 行為差異造成錯誤假設。
            $table->index('company_id', 'accounting_events_company_idx');
            $table->index('branch_id', 'accounting_events_branch_idx');
            $table->index(['source_type', 'source_id'], 'accounting_events_source_idx');
            $table->index('event_type', 'accounting_events_event_type_idx');
            $table->index('status', 'accounting_events_status_idx');
            $table->index('event_date', 'accounting_events_event_date_idx');
            $table->index('converted_journal_entry_id', 'accounting_events_converted_journal_idx');

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_events');
    }
};

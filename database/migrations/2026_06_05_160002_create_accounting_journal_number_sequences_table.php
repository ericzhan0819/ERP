<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journal_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('period', 6);
            $table->unsignedInteger('seq')->default(0);
            $table->timestamps();

            // 技術註解：以 company + period 維持單月單公司序號原子遞增，避免併發重號。
            $table->unique(['company_id', 'period'], 'accounting_journal_number_sequences_company_period_unique');
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_number_sequences');
    }
};
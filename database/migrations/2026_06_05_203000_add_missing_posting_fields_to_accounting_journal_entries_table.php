<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            // 技術註解：補齊舊環境已執行 migration 後缺漏的過帳與作廢欄位，避免修舊 migration 無法修復 schema drift。
            if (! Schema::hasColumn('accounting_journal_entries', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('accounting_journal_entries', 'posted_by')) {
                $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('accounting_journal_entries', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('posted_by');
            }

            if (! Schema::hasColumn('accounting_journal_entries', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('accounting_journal_entries', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_by');
            }

            if (! Schema::hasColumn('accounting_journal_entries', 'attachment')) {
                $table->text('attachment')->nullable()->after('void_reason');
            }

            if (! Schema::hasColumn('accounting_journal_entries', 'source_type')) {
                $table->string('source_type', 80)->nullable()->after('attachment');
            }

            if (! Schema::hasColumn('accounting_journal_entries', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('accounting_journal_entries', 'posted_by')) {
                $table->dropForeign(['posted_by']);
            }

            if (Schema::hasColumn('accounting_journal_entries', 'voided_by')) {
                $table->dropForeign(['voided_by']);
            }

            foreach ([
                'posted_at',
                'posted_by',
                'voided_at',
                'voided_by',
                'void_reason',
                'attachment',
                'source_type',
                'source_id',
            ] as $column) {
                if (Schema::hasColumn('accounting_journal_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

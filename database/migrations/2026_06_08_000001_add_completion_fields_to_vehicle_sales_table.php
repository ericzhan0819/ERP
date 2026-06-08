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
        Schema::table('vehicle_sales', function (Blueprint $table): void {
            // 技術註解：交易完成節點先只保存後端可控的完成時間與操作者，避免在 Phase 2 誤引入 delivery/accounting 狀態。
            $table->dateTime('completed_at')->nullable()->after('sold_at');
            $table->text('completion_note')->nullable()->after('notes');
            // 技術註解：completed_by 未來必須由後端 completion action 寫入，防止前端 payload 決定操作者造成權限提升。
            $table->foreignId('completed_by')
                ->nullable()
                ->after('updated_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['company_id', 'completed_at'], 'vehicle_sales_company_completed_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_sales', function (Blueprint $table): void {
            $table->dropIndex('vehicle_sales_company_completed_at_index');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn(['completed_at', 'completion_note']);
        });
    }
};

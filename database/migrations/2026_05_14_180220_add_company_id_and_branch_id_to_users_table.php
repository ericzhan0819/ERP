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
        Schema::table('users', function (Blueprint $table): void {
            // 技術註解：為避免既有 user 資料在部署時因缺值失敗，第一階段先採 nullable 漸進導入。
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            // 技術註解：branch 允許為 null，代表公司層級帳號可跨分店存取同公司資料。
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();

            $table->index('company_id');
            $table->index('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['company_id']);
            $table->dropIndex(['branch_id']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};


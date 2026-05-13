<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 技術註解：僅補上登入狀態欄位，不移除既有 account_status 以維持舊資料相容。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('account_status');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    /**
     * 技術註解：rollback 僅還原本 migration 新增欄位，避免影響使用者主資料。
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_login_at']);
        });
    }
};

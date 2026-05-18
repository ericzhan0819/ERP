<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            // 技術註解：Foundation Phase 1 補齊 Registry 欄位，全部採增量欄位以確保既有資料不被破壞。
            $table->string('section')->nullable()->after('label');
            $table->foreignId('parent_id')->nullable()->after('section')->constrained('modules')->nullOnDelete();
            $table->string('parent_key')->nullable()->after('parent_id')->index();
            $table->string('permission_prefix')->nullable()->after('route_name')->index();
            $table->string('icon_key')->nullable()->after('permission_prefix');
            $table->boolean('is_enabled')->default(true)->after('sort_order')->index();
            $table->json('active_patterns')->nullable()->after('is_enabled');
        });

        // 技術註解：向下相容初始化，將舊欄位值映射到新欄位，避免舊資料在升級後遺失可用性。
        DB::table('modules')->update([
            'permission_prefix' => DB::raw('COALESCE(permission_prefix, base_permission)'),
            'icon_key' => DB::raw('COALESCE(icon_key, icon)'),
            'is_enabled' => DB::raw('COALESCE(is_enabled, is_active, 1)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropIndex(['parent_key']);
            $table->dropIndex(['permission_prefix']);
            $table->dropIndex(['is_enabled']);
            $table->dropColumn([
                'section',
                'parent_key',
                'permission_prefix',
                'icon_key',
                'is_enabled',
                'active_patterns',
            ]);
        });
    }
};


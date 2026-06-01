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
        Schema::table('vehicles', function (Blueprint $table) {
            // 技術註解：價格欄位獨立為 nullable，避免舊資料回填時造成 migration 阻塞。
            $table->decimal('asking_price', 12, 2)->nullable()->after('internal_notes');
            // 技術註解：底價與開價分欄，供後端授權控管精準遮罩與審計追蹤。
            $table->decimal('floor_price', 12, 2)->nullable()->after('asking_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['asking_price', 'floor_price']);
        });
    }
};


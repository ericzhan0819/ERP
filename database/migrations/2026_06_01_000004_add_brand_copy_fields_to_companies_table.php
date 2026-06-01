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
        $columnsToAdd = [
            'brand_name',
            'brand_name_en',
            'brand_slogan',
            'brand_eyebrow',
        ];

        if (! Schema::hasTable('companies')) {
            return;
        }

        $missingColumns = array_values(array_filter(
            $columnsToAdd,
            static fn (string $column): bool => ! Schema::hasColumn('companies', $column)
        ));

        if ($missingColumns === []) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) use ($missingColumns): void {
            // 技術註解：僅補齊缺漏品牌文案欄位，避免重複新增既有欄位造成 migration 失敗。
            if (in_array('brand_name', $missingColumns, true)) {
                $table->string('brand_name')->nullable()->after('currency');
            }

            if (in_array('brand_name_en', $missingColumns, true)) {
                $table->string('brand_name_en')->nullable()->after('brand_name');
            }

            if (in_array('brand_slogan', $missingColumns, true)) {
                $table->string('brand_slogan')->nullable()->after('brand_subtitle');
            }

            if (in_array('brand_eyebrow', $missingColumns, true)) {
                $table->string('brand_eyebrow')->nullable()->after('brand_slogan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $columnsToDrop = array_values(array_filter(
            ['brand_name', 'brand_name_en', 'brand_slogan', 'brand_eyebrow'],
            static fn (string $column): bool => Schema::hasColumn('companies', $column)
        ));

        if ($columnsToDrop === []) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) use ($columnsToDrop): void {
            // 技術註解：僅移除目前存在欄位，避免 rollback 因不存在欄位中斷。
            $table->dropColumn($columnsToDrop);
        });
    }
};


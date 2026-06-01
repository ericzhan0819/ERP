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
        Schema::table('companies', function (Blueprint $table): void {
            // 技術註解：沿用既有 companies 作為單一來源，避免新增 settings table 導致資料分裂。
            $table->string('tax_id')->nullable()->after('code');
            $table->string('phone')->nullable()->after('tax_id');
            $table->string('email')->nullable()->after('phone');
            $table->string('address')->nullable()->after('email');
            $table->string('logo_url')->nullable()->after('address');
            $table->string('currency', 3)->default('TWD')->after('logo_url');
            $table->string('brand_name')->nullable()->after('currency');
            $table->string('brand_name_en')->nullable()->after('brand_name');
            $table->string('brand_subtitle')->nullable()->after('brand_name_en');
            $table->string('brand_slogan')->nullable()->after('brand_subtitle');
            $table->string('brand_eyebrow')->nullable()->after('brand_slogan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_id',
                'phone',
                'email',
                'address',
                'logo_url',
                'currency',
                'brand_name',
                'brand_name_en',
                'brand_subtitle',
                'brand_slogan',
                'brand_eyebrow',
            ]);
        });
    }
};

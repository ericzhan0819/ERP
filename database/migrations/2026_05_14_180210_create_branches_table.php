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
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            // 技術註解：分店必須綁定公司，避免後續 tenant 邊界判斷出現孤兒資料造成資料外洩風險。
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            // 技術註解：分店代碼只需在同公司內唯一，支援跨公司使用相同命名慣例。
            $table->string('code');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};


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
        Schema::create('vehicle_status_logs', function (Blueprint $table) {
            $table->id();

            // 技術註解：狀態歷程必須可回溯到主檔，提供流程稽核與爭議追查依據。
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();

            // 技術註解：即使透過 vehicle_id 可反查，仍冗餘保存租戶邊界以強化查詢與稽核過濾效率。
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vehicle_id', 'created_at'], 'vehicle_status_logs_vehicle_created_index');
            $table->index(['company_id', 'branch_id', 'created_at'], 'vehicle_status_logs_company_branch_created_index');
            $table->index(['company_id', 'to_status'], 'vehicle_status_logs_company_to_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_status_logs');
    }
};


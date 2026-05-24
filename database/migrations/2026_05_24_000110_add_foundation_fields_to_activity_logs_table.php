<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            $table->string('subject_type')->nullable()->after('target_user_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->string('event')->nullable()->after('action');
            $table->json('old_values')->nullable()->after('metadata');
            $table->json('new_values')->nullable()->after('old_values');

            // 技術註解：最小必要索引用於後續審計查詢，避免全表掃描造成後台查詢壓力。
            $table->index('company_id');
            $table->index('event');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex(['company_id']);
            $table->dropIndex(['event']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['created_at']);

            $table->dropColumn([
                'company_id',
                'branch_id',
                'subject_type',
                'subject_id',
                'event',
                'old_values',
                'new_values',
            ]);
        });
    }
};


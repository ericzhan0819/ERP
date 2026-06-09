<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_event_account_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('event_type', 80);
            $table->string('source_type', 80);
            $table->string('mapping_key', 120);
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->index('company_id', 'ae_account_mappings_company_idx');
            $table->index('branch_id', 'ae_account_mappings_branch_idx');
            $table->index('event_type', 'ae_account_mappings_event_type_idx');
            $table->index('source_type', 'ae_account_mappings_source_type_idx');
            $table->index('mapping_key', 'ae_account_mappings_mapping_key_idx');
            $table->index('is_active', 'ae_account_mappings_is_active_idx');
            $table->index(['company_id', 'event_type', 'mapping_key'], 'ae_account_mappings_company_event_key_idx');
            $table->index(['company_id', 'branch_id', 'event_type', 'mapping_key'], 'ae_account_mappings_scope_event_key_idx');
            $table->unique(['company_id', 'branch_id', 'event_type', 'mapping_key'], 'ae_account_mappings_unique_scope_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_event_account_mappings');
    }
};

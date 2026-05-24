<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->string('login_identifier')->nullable();
            $table->string('event')->index();
            $table->string('guard')->nullable()->default('web');
            $table->string('ip_address')->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};


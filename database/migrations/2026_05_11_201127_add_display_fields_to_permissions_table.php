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
        
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('group')->nullable()->after('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn([
                'label',
                'group',
            ]);
        });
    }
};
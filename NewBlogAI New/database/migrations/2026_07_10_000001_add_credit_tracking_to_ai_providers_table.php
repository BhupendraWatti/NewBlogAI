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
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->bigInteger('credits_total')->nullable()->after('is_enabled');
            $table->bigInteger('credits_remaining')->nullable()->after('credits_total');
            $table->timestamp('reset_at')->nullable()->after('credits_remaining');
            $table->text('last_error')->nullable()->after('reset_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn(['credits_total', 'credits_remaining', 'reset_at', 'last_error']);
        });
    }
};

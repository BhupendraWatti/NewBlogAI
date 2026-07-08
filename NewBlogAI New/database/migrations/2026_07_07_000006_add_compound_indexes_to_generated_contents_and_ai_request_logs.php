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
        Schema::table('generated_contents', function (Blueprint $table) {
            $table->index(['site_id', 'created_at']);
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->index(['site_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_contents', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'created_at']);
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'created_at']);
        });
    }
};

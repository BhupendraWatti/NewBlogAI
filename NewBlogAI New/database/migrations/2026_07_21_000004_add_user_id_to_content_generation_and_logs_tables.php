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
            $table->foreignId('user_id')->nullable()->after('topic_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('pipeline_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('site_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('generated_contents', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};

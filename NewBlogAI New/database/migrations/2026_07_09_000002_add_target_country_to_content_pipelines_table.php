<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add target_country to content_pipelines to allow per-pipeline national targeting.
     */
    public function up(): void
    {
        Schema::table('content_pipelines', function (Blueprint $table) {
            $table->string('target_country')->nullable()->after('news_category')
                  ->comment('Geographic/national focus for news discovery (e.g. India).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_pipelines', function (Blueprint $table) {
            $table->dropColumn('target_country');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issue #1 Fix — Geographic Diversity (Sub-problem 3).
     *
     * Adds geo_city and geo_state columns to news_candidates so the
     * GeographicDiversityEnforcer can enforce per-city / per-state
     * quotas at candidate-batch level, preventing the "9 stories from
     * Ujjain" bubble effect.
     *
     * Also adds quality_score (0-100) so the ContentQualityFilterService
     * result is stored alongside the candidate for future admin UI display
     * and per-workspace threshold configuration.
     */
    public function up(): void
    {
        Schema::table('news_candidates', function (Blueprint $table) {
            $table->string('geo_city', 100)->nullable()
                ->after('metadata')
                ->comment('City extracted from candidate content by GeoTaggingService');

            $table->string('geo_state', 100)->nullable()
                ->after('geo_city')
                ->comment('State/region extracted from candidate content by GeoTaggingService');

            $table->unsignedTinyInteger('quality_score')->nullable()
                ->after('geo_state')
                ->comment('0-100 content quality score from ContentQualityFilterService');

            // Composite index to make geo-quota queries fast
            $table->index(['pipeline_run_id', 'geo_city'], 'nc_run_city_idx');
            $table->index(['pipeline_run_id', 'geo_state'], 'nc_run_state_idx');
        });
    }

    public function down(): void
    {
        Schema::table('news_candidates', function (Blueprint $table) {
            $table->dropIndex('nc_run_city_idx');
            $table->dropIndex('nc_run_state_idx');
            $table->dropColumn(['geo_city', 'geo_state', 'quality_score']);
        });
    }
};

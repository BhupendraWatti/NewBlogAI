<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Newsroom Coverage workflow (Phase 5).
     *
     * - pipeline_runs.run_type discriminates 'full' generation runs from
     *   'discovery' runs (Coverage → 9 news candidates → employee selects one).
     * - news_candidates stores the lightweight candidate news events produced
     *   by a discovery run. Only the employee-selected candidate ever proceeds
     *   to full content generation.
     */
    public function up(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->string('run_type', 20)->default('full')->after('status')->index();
        });

        Schema::create('news_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_run_id')->constrained('pipeline_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('full_run_id')->nullable()->comment('PipelineRun created when this candidate was selected for full generation');
            $table->unsignedTinyInteger('position')->comment('1-9 ordering within the discovery run');
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->json('source_references')->nullable()->comment('[{name, url}] trusted source references');
            $table->json('keywords')->nullable();
            $table->unsignedTinyInteger('trend_score')->default(0)->comment('0-100');
            $table->unsignedTinyInteger('freshness_score')->default(0)->comment('0-100');
            $table->string('uniqueness_hash', 64)->index()->comment('sha256 of normalized title for fast duplicate lookups');
            $table->json('metadata')->nullable();
            $table->string('status', 20)->default('candidate')->index()->comment('candidate|selected|rejected|duplicate');
            $table->unsignedBigInteger('selected_by')->nullable()->comment('User ID of the employee who selected this candidate');
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->index(['pipeline_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_candidates');

        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->dropColumn('run_type');
        });
    }
};

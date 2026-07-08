<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops the topic_id FK from content_pipelines and adds a `news_category` string column
     * so the pipeline can be driven by a global news category instead of a user-defined topic.
     */
    public function up(): void
    {
        Schema::table('content_pipelines', function (Blueprint $table) {
            // Drop old topic FK and column (nullable first to avoid constraint issues)
            if (Schema::hasColumn('content_pipelines', 'topic_id')) {
                // Drop foreign key first
                try {
                    $table->dropForeign(['topic_id']);
                } catch (\Exception $e) {
                    // Ignore if FK doesn't exist
                }
                $table->dropColumn('topic_id');
            }

            // Add news_category column (replaces topic)
            $table->string('news_category')->default('global')->after('site_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_pipelines', function (Blueprint $table) {
            // Remove news_category
            if (Schema::hasColumn('content_pipelines', 'news_category')) {
                $table->dropColumn('news_category');
            }

            // Restore topic_id (nullable to allow rollback without data)
            $table->unsignedBigInteger('topic_id')->nullable()->after('site_id');
            $table->foreign('topic_id')->references('id')->on('topics')->onDelete('cascade');
        });
    }
};

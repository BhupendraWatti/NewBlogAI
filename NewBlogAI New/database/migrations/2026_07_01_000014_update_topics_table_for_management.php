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
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->string('category')->nullable()->after('name');
            $table->string('priority')->default('medium')->after('category');
            $table->string('language')->default('en')->after('priority');
            $table->string('status')->default('draft')->after('language'); // active, inactive, draft
            $table->string('generation_frequency')->default('daily')->after('status');
            $table->json('tags')->nullable()->after('generation_frequency');
            $table->unsignedBigInteger('prompt_id')->nullable()->after('tags');
            $table->softDeletes()->after('updated_at');

            $table->foreign('parent_id')->references('id')->on('topics')->onDelete('cascade');
            $table->foreign('prompt_id')->references('id')->on('prompts')->onDelete('set null');

            $table->index('status');
            $table->index('category');
            $table->index('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['prompt_id']);
            $table->dropColumn([
                'parent_id',
                'category',
                'priority',
                'language',
                'status',
                'generation_frequency',
                'tags',
                'prompt_id',
                'deleted_at',
            ]);
        });
    }
};

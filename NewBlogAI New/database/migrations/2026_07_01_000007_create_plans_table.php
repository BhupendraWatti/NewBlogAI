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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('monthly_price', 8, 2);
            $table->decimal('yearly_price', 8, 2);
            $table->integer('max_wordpress_sites');
            $table->integer('max_topics');
            $table->integer('publishing_schedule_limit');
            $table->integer('max_articles_per_day');
            $table->integer('prompt_templates_allowed');
            $table->json('ai_providers_available')->nullable();
            $table->integer('api_keys_allowed');
            $table->bigInteger('storage_limit');
            $table->boolean('analytics_access')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

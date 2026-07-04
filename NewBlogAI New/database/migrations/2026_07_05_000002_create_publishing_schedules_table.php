<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishing_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('pipeline_id')->nullable();
            $table->string('name');
            $table->string('frequency')->default('daily');
            $table->string('timezone')->default('UTC');
            $table->time('time_of_day')->default('09:00:00');
            $table->json('days_of_week')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('pipeline_id')->references('id')->on('content_pipelines')->nullOnDelete();
            $table->index(['is_active', 'next_run_at']);
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publishing_schedules');
    }
};

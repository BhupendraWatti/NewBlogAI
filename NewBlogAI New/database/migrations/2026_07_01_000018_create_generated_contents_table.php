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
        Schema::create('generated_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipeline_id')->nullable();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('topic_id');
            $table->string('title');
            $table->longText('content');
            $table->string('status')->default('draft'); // draft, pending_review, approved, rejected, published
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('pipeline_id')->references('id')->on('content_pipelines')->onDelete('set null');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('topics')->onDelete('cascade');

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_contents');
    }
};

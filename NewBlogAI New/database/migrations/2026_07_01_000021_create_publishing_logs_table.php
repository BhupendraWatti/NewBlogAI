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
        Schema::create('publishing_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('generated_content_id');
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Author
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled, retrying
            $table->unsignedBigInteger('wp_post_id')->nullable();
            $table->string('published_url')->nullable();
            $table->string('wp_status')->default('draft'); // draft, future, publish, pending
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();

            $table->foreign('generated_content_id')->references('id')->on('generated_contents')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('status');
            $table->index('wp_post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publishing_logs');
    }
};

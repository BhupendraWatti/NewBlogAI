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
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model');
            $table->unsignedBigInteger('prompt_id')->nullable();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->unsignedInteger('execution_time_ms');
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 10, 6)->default(0.000000);
            $table->string('status'); // success, failed
            $table->json('response_metadata')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('prompt_id')->references('id')->on('prompts')->onDelete('set null');
            $table->foreign('topic_id')->references('id')->on('topics')->onDelete('set null');

            $table->index('provider');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
    }
};

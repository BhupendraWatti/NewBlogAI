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
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key')->unique(); // e.g. gemini, openai, claude, groq, openrouter, ollama
            $table->string('name'); // e.g. Google Gemini
            $table->text('api_key')->nullable(); // encrypted
            $table->string('default_model')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index('is_enabled');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};

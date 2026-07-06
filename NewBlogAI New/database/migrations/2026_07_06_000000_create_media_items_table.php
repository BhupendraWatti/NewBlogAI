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
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('generated_content_id')->nullable();
            $table->string('filename')->nullable();
            $table->string('filepath')->nullable();
            $table->text('url');
            $table->string('driver');
            $table->text('prompt')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('generated_content_id')
                ->references('id')
                ->on('generated_contents')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};

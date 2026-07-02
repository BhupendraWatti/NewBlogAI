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
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('generated_content_id');
            $table->string('title');
            $table->longText('content');
            $table->unsignedBigInteger('user_id')->nullable(); // who created this revision/edited it
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('generated_content_id')->references('id')->on('generated_contents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
    }
};

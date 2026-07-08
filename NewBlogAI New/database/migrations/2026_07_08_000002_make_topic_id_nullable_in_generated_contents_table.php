<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Alters the topic_id column on generated_contents table to make it nullable.
     */
    public function up(): void
    {
        Schema::table('generated_contents', function (Blueprint $table) {
            $table->unsignedBigInteger('topic_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_contents', function (Blueprint $table) {
            // Restore back to non-nullable (make sure no null values exist before doing this in production)
            $table->unsignedBigInteger('topic_id')->change();
        });
    }
};

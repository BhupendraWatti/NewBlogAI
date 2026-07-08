<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publishing_schedules', function (Blueprint $table) {
            $table->string('schedule_mode')->default('time_based')->after('is_active');
            $table->json('metadata')->nullable()->after('schedule_mode');
        });
    }

    public function down(): void
    {
        Schema::table('publishing_schedules', function (Blueprint $table) {
            $table->dropColumn(['schedule_mode', 'metadata']);
        });
    }
};

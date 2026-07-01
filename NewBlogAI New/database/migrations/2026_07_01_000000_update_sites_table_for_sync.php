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
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('slot');
            $table->timestamp('last_synced_at')->nullable()->after('is_active');
            $table->string('last_sync_status')->nullable()->after('last_synced_at');
            $table->text('error_log')->nullable()->after('last_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_synced_at', 'last_sync_status', 'error_log']);
        });
    }
};

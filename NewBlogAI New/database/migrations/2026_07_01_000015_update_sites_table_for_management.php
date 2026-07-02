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
            if (!Schema::hasColumn('sites', 'status')) {
                $table->string('status')->default('disconnected')->after('is_active'); // connected, disconnected, error
            }
            if (!Schema::hasColumn('sites', 'plugin_version')) {
                $table->string('plugin_version')->nullable()->after('status');
            }
            if (!Schema::hasColumn('sites', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('plugin_version');
            }

            $table->index('status');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['status', 'plugin_version', 'is_default']);
        });
    }
};

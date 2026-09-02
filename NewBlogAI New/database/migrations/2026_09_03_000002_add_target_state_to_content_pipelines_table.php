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
        Schema::table('content_pipelines', function (Blueprint $table) {
            if (!Schema::hasColumn('content_pipelines', 'target_state')) {
                $table->string('target_state')->nullable()->after('target_country')
                    ->comment('Target state/province for granular local newsroom discovery.');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_pipelines', function (Blueprint $table) {
            if (Schema::hasColumn('content_pipelines', 'target_state')) {
                $table->dropColumn('target_state');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            // Drop unique constraint on provider_key to allow multiple credentials
            // of the same provider type (e.g. Gemini Free vs Gemini Paid)
            $table->dropUnique(['provider_key']);

            $table->string('tier')->default('free')->after('is_enabled'); // free, paid, local
            $table->integer('priority')->default(0)->after('tier');
            $table->string('status')->default('healthy')->after('priority'); // healthy, cooldown, disabled
            $table->timestamp('last_failure')->nullable()->after('status');
            $table->timestamp('cooldown_until')->nullable()->after('last_failure');
            $table->timestamp('last_used')->nullable()->after('cooldown_until');
            $table->integer('error_count')->default(0)->after('last_used');
            $table->integer('success_count')->default(0)->after('error_count');

            $table->index('status');
            $table->index('priority');
            $table->index('tier');
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->foreignId('provider_id')->nullable()->after('provider')->constrained('ai_providers')->nullOnDelete();
        });

        // Set default priorities for seeded providers so priority sorting works out of the box
        DB::table('ai_providers')->where('provider_key', 'gemini')->update(['priority' => 1, 'tier' => 'free']);
        DB::table('ai_providers')->where('provider_key', 'groq')->update(['priority' => 2, 'tier' => 'free']);
        DB::table('ai_providers')->where('provider_key', 'openai')->update(['priority' => 3, 'tier' => 'paid']);
        DB::table('ai_providers')->where('provider_key', 'claude')->update(['priority' => 4, 'tier' => 'paid']);
        DB::table('ai_providers')->where('provider_key', 'openrouter')->update(['priority' => 5, 'tier' => 'paid']);
        DB::table('ai_providers')->where('provider_key', 'ollama')->update(['priority' => 6, 'tier' => 'local']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropColumn('provider_id');
        });

        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['tier']);
            
            $table->dropColumn([
                'tier',
                'priority',
                'status',
                'last_failure',
                'cooldown_until',
                'last_used',
                'error_count',
                'success_count',
            ]);
        });

        // Resolve duplicates before adding the unique constraint back (keep only the first one of each type)
        $duplicates = DB::table('ai_providers')
            ->select('provider_key', DB::raw('MIN(id) as keep_id'))
            ->groupBy('provider_key')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('ai_providers')
                ->where('provider_key', $row->provider_key)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('ai_providers', function (Blueprint $table) {
            $table->unique('provider_key');
        });
    }
};

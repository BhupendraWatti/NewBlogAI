<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keys', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('key_hash', 64)->nullable()->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'revoked_at']);
        });

        DB::table('keys')
            ->where('name', 'like', 'plugin-token-%')
            ->orderBy('id')
            ->each(function (object $credential): void {
                $userId = (int) str_replace('plugin-token-', '', $credential->name);

                DB::table('keys')
                    ->where('id', $credential->id)
                    ->update([
                        'user_id' => $userId ?: null,
                        'key_hash' => hash('sha256', $credential->key),
                        'abilities' => json_encode(['plugin:connect', 'plugin:read', 'plugin:write']),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('keys', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['key_hash']);
            $table->dropIndex(['user_id', 'revoked_at']);
            $table->dropColumn([
                'user_id',
                'key_hash',
                'abilities',
                'last_used_at',
                'expires_at',
                'revoked_at',
            ]);
        });
    }
};

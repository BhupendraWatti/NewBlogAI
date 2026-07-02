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
        Schema::create('plugin_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key')->unique();
            $table->uuid('customer_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('domain')->nullable();
            $table->string('status')->default('inactive'); // active, inactive, expired, revoked
            $table->unsignedInteger('installations_count')->default(0);
            $table->unsignedInteger('max_installations')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('set null');

            $table->index('license_key');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_licenses');
    }
};

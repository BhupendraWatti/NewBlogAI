<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable()->after('id');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index(['customer_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'is_enabled']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};

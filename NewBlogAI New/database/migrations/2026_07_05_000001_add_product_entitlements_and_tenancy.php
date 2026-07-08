<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('monthly_generation_limit')->default(100);
            $table->string('minimum_publishing_frequency')->default('daily');
            $table->json('feature_flags')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index('customer_id');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->index('subscription_id');
        });

        Schema::table('promts', function (Blueprint $table) {
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->foreign('topic_id')->references('id')->on('topics')->nullOnDelete();
            $table->index('topic_id');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('publishing_mode')->default('draft');
            $table->json('category_mapping')->nullable();
            $table->json('sync_settings')->nullable();
            $table->string('timezone')->default('UTC');
            $table->unsignedBigInteger('configuration_version')->default(1);
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            $table->index(['customer_id', 'created_at']);
            $table->index(['subscription_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['site_id']);
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropIndex(['subscription_id', 'created_at']);
            $table->dropColumn(['customer_id', 'subscription_id', 'site_id']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'publishing_mode',
                'category_mapping',
                'sync_settings',
                'timezone',
                'configuration_version',
            ]);
        });

        $promtTable = Schema::hasTable('prompts') ? 'prompts' : (Schema::hasTable('promts') ? 'promts' : null);
        if ($promtTable) {
            Schema::table($promtTable, function (Blueprint $table) {
                $table->dropForeign(['topic_id']);
                $table->dropIndex(['topic_id']);
                $table->dropColumn('topic_id');
            });
        }

        Schema::table('topics', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropIndex(['subscription_id']);
            $table->dropColumn('subscription_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_generation_limit',
                'minimum_publishing_frequency',
                'feature_flags',
            ]);
        });
    }
};

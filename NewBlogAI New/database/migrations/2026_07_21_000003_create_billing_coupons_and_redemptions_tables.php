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
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('type'); // percentage, fixed
            $table->decimal('value', 10, 2);
            $table->string('duration')->default('once'); // once, repeating, forever
            $table->integer('duration_in_months')->nullable();
            $table->integer('max_redemptions')->nullable();
            $table->integer('redeemed_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('coupon_id');
            $table->uuid('customer_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->timestamp('redeemed_at')->useCurrent();

            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};

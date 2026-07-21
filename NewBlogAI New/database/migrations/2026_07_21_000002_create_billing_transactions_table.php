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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('invoice_id')->nullable();
            $table->string('gateway'); // Stripe, Razorpay, Cashfree, PhonePe, manual, coupon
            $table->string('gateway_transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('type')->default('charge'); // charge, refund, credit
            $table->string('status')->default('pending'); // pending, succeeded, failed, refunded
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();

            $table->index(['customer_id', 'status']);
            $table->index('gateway_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

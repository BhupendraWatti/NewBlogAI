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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('unpaid'); // draft, unpaid, paid, void, uncollectible
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('billing_reason')->default('subscription_cycle'); // subscription_create, subscription_cycle, subscription_update, manual
            $table->string('gateway_invoice_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();

            $table->index(['customer_id', 'status']);
            $table->index('gateway_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

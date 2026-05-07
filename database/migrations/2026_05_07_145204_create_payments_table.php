<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('invoice_installment_id')->nullable()->constrained('invoice_installments')->nullOnDelete();
            $table->string('gateway');
            $table->string('mode')->default('sandbox');
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_customer_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('refunded_amount_cents')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['gateway', 'gateway_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

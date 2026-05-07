<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'gateway' => Payment::GATEWAY_MANUAL,
            'mode' => Payment::MODE_SANDBOX,
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => 25000,
            'currency' => 'USD',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_COMPLETED,
            'received_at' => now(),
        ]);
    }

    public function square(): static
    {
        return $this->state(fn () => [
            'gateway' => Payment::GATEWAY_SQUARE,
            'gateway_payment_id' => 'sq_'.fake()->uuid(),
        ]);
    }

    public function paypal(): static
    {
        return $this->state(fn () => [
            'gateway' => Payment::GATEWAY_PAYPAL,
            'gateway_payment_id' => 'pp_'.fake()->uuid(),
        ]);
    }
}

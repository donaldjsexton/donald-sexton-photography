<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'USD',
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(14),
            'default_tax_rate' => 7.000,
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 0,
            'amount_paid_cents' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_PAID,
            'sent_at' => now()->subDay(),
            'paid_at' => now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_VOID,
            'voided_at' => now(),
        ]);
    }
}

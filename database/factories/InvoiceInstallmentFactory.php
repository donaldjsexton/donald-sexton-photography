<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<InvoiceInstallment>
 */
class InvoiceInstallmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'sequence' => 1,
            'label' => 'Retainer',
            'due_date' => Carbon::today()->addDays(7),
            'amount_cents' => 50000,
            'amount_paid_cents' => 0,
            'status' => InvoiceInstallment::STATUS_PENDING,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'amount_paid_cents' => $attrs['amount_cents'] ?? 50000,
            'status' => InvoiceInstallment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLineItem>
 */
class InvoiceLineItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'sort_order' => 0,
            'description' => fake()->randomElement([
                'Wedding photography coverage — 8 hours',
                'Engagement session',
                'Second photographer',
                'Premium album',
                'Travel fee',
            ]),
            'quantity' => 1,
            'unit_price_cents' => fake()->numberBetween(20000, 500000),
            'tax_rate' => 0,
        ];
    }
}

<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;

class InvoiceComposer
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncLineItems(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $index => $row) {
            $invoice->lineItems()->create([
                'sort_order' => $index,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price_cents' => $this->dollarsToCents($row['unit_price']),
                'tax_rate' => $row['tax_rate'] ?? 0,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncInstallments(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $index => $row) {
            $amount = isset($row['amount']) ? $this->dollarsToCents($row['amount']) : 0;
            if ($amount <= 0) {
                continue;
            }

            $invoice->installments()->create([
                'sequence' => $index + 1,
                'label' => $row['label'] ?? null,
                'due_date' => $row['due_date'] ?? null,
                'amount_cents' => $amount,
            ]);
        }
    }

    public function dollarsToCents(float|int|string|null $dollars): int
    {
        if ($dollars === null || $dollars === '') {
            return 0;
        }

        return (int) round(((float) $dollars) * 100);
    }
}

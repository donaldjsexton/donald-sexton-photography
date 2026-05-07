<?php

namespace App\Models;

use Database\Factories\InvoiceLineItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    /** @use HasFactory<InvoiceLineItemFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'sort_order',
        'description',
        'quantity',
        'unit_price_cents',
        'tax_rate',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_cents' => 'integer',
            'tax_rate' => 'decimal:3',
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InvoiceLineItem $item) {
            $item->recomputeTotals();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recomputeTotals(): void
    {
        $quantity = (float) ($this->quantity ?? 0);
        $unit = (int) ($this->unit_price_cents ?? 0);
        $rate = (float) ($this->tax_rate ?? 0);

        $subtotal = (int) round($quantity * $unit);
        $tax = (int) round($subtotal * ($rate / 100));

        $this->subtotal_cents = $subtotal;
        $this->tax_cents = $tax;
        $this->total_cents = $subtotal + $tax;
    }
}

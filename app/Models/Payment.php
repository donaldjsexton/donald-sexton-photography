<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const GATEWAY_SQUARE = 'square';

    public const GATEWAY_PAYPAL = 'paypal';

    public const GATEWAY_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_LIVE = 'live';

    protected $fillable = [
        'uuid',
        'invoice_id',
        'invoice_installment_id',
        'gateway',
        'mode',
        'status',
        'amount_cents',
        'currency',
        'gateway_payment_id',
        'gateway_order_id',
        'gateway_customer_id',
        'failure_reason',
        'payload',
        'received_at',
        'refunded_at',
        'refunded_amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'refunded_at' => 'datetime',
            'amount_cents' => 'integer',
            'refunded_amount_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    public static function gatewayOptions(): array
    {
        return [
            self::GATEWAY_SQUARE => 'Square',
            self::GATEWAY_PAYPAL => 'PayPal',
            self::GATEWAY_MANUAL => 'Manual',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(InvoiceInstallment::class, 'invoice_installment_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}

<?php

namespace App\Services\Payments;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $gatewayPaymentId = null,
        public readonly ?string $gatewayOrderId = null,
        public readonly ?string $failureReason = null,
        /** @var array<string, mixed> */
        public readonly array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function completed(string $gatewayPaymentId, array $payload = [], ?string $gatewayOrderId = null): self
    {
        return new self(
            success: true,
            status: 'completed',
            gatewayPaymentId: $gatewayPaymentId,
            gatewayOrderId: $gatewayOrderId,
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function failed(string $reason, array $payload = []): self
    {
        return new self(
            success: false,
            status: 'failed',
            failureReason: $reason,
            payload: $payload,
        );
    }
}

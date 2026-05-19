<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RecordRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'refunded_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Payment|null $payment */
            $payment = $this->route('payment');

            if ($payment === null) {
                return;
            }

            $requestedCents = (int) round(((float) $this->input('amount', 0)) * 100);
            $remaining = $payment->amount_cents - $payment->refunded_amount_cents;

            if ($requestedCents > $remaining) {
                $validator->errors()->add('amount', 'Refund amount exceeds the remaining refundable balance ('.number_format($remaining / 100, 2).').');
            }
        });
    }
}

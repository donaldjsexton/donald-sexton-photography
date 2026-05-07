<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');

        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'gateway' => ['required', Rule::in(array_keys(Payment::gatewayOptions()))],
            'invoice_installment_id' => [
                'nullable',
                Rule::exists('invoice_installments', 'id')->where('invoice_id', $invoice?->id ?? 0),
            ],
            'received_at' => ['nullable', 'date'],
            'gateway_payment_id' => ['nullable', 'string', 'max:255'],
            'failure_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

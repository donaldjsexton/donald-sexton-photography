<?php

namespace App\Http\Requests\Admin;

use App\Models\Client;
use App\Models\Venue;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
        $billableType = (string) $this->input('billable_type', 'client');
        $billableTable = $billableType === 'venue' ? 'venues' : 'clients';

        return [
            'billable_type' => ['required', Rule::in(['client', 'venue'])],
            'billable_id' => ['required', 'integer', 'exists:'.$billableTable.',id'],
            'booked_job_id' => ['nullable', 'integer', 'exists:booked_jobs,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'net_terms' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],

            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'installments' => ['nullable', 'array'],
            'installments.*.label' => ['nullable', 'string', 'max:255'],
            'installments.*.due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'installments.*.amount' => ['required_with:installments.*.label', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lineItems = (array) $this->input('line_items', []);
            $subtotal = 0.0;
            foreach ($lineItems as $item) {
                $subtotal += (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
            }

            $discount = (float) $this->input('discount', 0);
            if ($discount > $subtotal && $subtotal > 0) {
                $validator->errors()->add('discount', 'Discount cannot exceed the line-item subtotal ('.number_format($subtotal, 2).').');
            }

            if ($subtotal <= 0) {
                $validator->errors()->add('line_items', 'An invoice must total more than zero.');
            }

            $installments = (array) $this->input('installments', []);
            $installmentTotal = 0.0;
            foreach ($installments as $installment) {
                if ($installment['amount'] ?? null) {
                    $installmentTotal += (float) $installment['amount'];
                }
            }

            $total = max(0, $subtotal - $discount);
            if ($installmentTotal > $total + 0.01) {
                $validator->errors()->add('installments', 'Installments add up to '.number_format($installmentTotal, 2).' but the invoice total is only '.number_format($total, 2).'.');
            }
        });
    }

    public function billableClass(): string
    {
        return $this->input('billable_type') === 'venue'
            ? Venue::class
            : Client::class;
    }
}

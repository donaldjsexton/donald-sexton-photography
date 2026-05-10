<?php

namespace App\Http\Requests\Admin;

use App\Models\Client;
use App\Models\Venue;
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
            'installments.*.due_date' => ['nullable', 'date'],
            'installments.*.amount' => ['required_with:installments.*.label', 'numeric', 'min:0.01'],
        ];
    }

    public function billableClass(): string
    {
        return $this->input('billable_type') === 'venue'
            ? Venue::class
            : Client::class;
    }
}

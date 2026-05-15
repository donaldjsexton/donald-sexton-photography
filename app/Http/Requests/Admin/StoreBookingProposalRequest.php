<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingProposalRequest extends FormRequest
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
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'booked_job_id' => ['nullable', 'integer', 'exists:booked_jobs,id'],

            'contract_template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'issue_date' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'internal_notes' => ['nullable', 'string'],

            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'invoice_notes' => ['nullable', 'string'],
            'invoice_terms' => ['nullable', 'string'],

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
}

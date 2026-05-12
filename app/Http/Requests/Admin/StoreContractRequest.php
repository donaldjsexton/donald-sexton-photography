<?php

namespace App\Http\Requests\Admin;

use App\Models\Client;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
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
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'contract_template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],

            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'issue_date' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }

    public function billableClass(): string
    {
        return $this->input('billable_type') === 'venue'
            ? Venue::class
            : Client::class;
    }
}

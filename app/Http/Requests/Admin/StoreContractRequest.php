<?php

namespace App\Http\Requests\Admin;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Venue;
use Illuminate\Contracts\Validation\Validator;
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $billableClass = $this->billableClass();
            $billableId = (int) $this->input('billable_id');

            if ($invoiceId = $this->input('invoice_id')) {
                $invoice = Invoice::query()->find((int) $invoiceId);
                if ($invoice && ($invoice->billable_type !== $billableClass || $invoice->billable_id !== $billableId)) {
                    $validator->errors()->add('invoice_id', 'The linked invoice does not belong to the selected client or venue.');
                }
            }

            if ($bookedJobId = $this->input('booked_job_id')) {
                $bookedJob = BookedJob::query()->with('inquiry')->find((int) $bookedJobId);
                if ($bookedJob && $billableClass === Client::class) {
                    $clientIdOnJob = $bookedJob->inquiry?->client_id;
                    if ($clientIdOnJob !== null && $clientIdOnJob !== $billableId) {
                        $validator->errors()->add('booked_job_id', 'The selected job does not belong to this client.');
                    }
                }
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

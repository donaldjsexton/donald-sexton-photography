<?php

namespace App\Services\Contracts;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

class ContractVariableResolver
{
    /**
     * Variables exposed to template authors. Keep this list in sync with the
     * admin help text on the template form.
     *
     * @return array<string, string>
     */
    public static function availableVariables(): array
    {
        return [
            'client_name' => 'Client / venue display name',
            'client_email' => 'Client / venue email',
            'photographer_name' => 'Studio business name',
            'photographer_email' => 'Studio email',
            'photographer_phone' => 'Studio phone',
            'business_name' => 'Studio business name (alias of photographer_name)',
            'contract_number' => 'Auto-generated contract number',
            'contract_title' => 'Contract title',
            'issue_date' => 'Date the contract is issued',
            'expires_at' => 'Date the offer expires (if set)',
            'event_name' => 'Booked job summary',
            'event_date' => 'Booked job event date',
            'event_location' => 'Booked job event location',
            'invoice_number' => 'Linked invoice number (if any)',
            'invoice_total' => 'Linked invoice total (if any)',
        ];
    }

    /**
     * Build the merge variable map for a contract draft.
     *
     * @return array<string, string>
     */
    public function variablesFor(
        ?Model $billable = null,
        ?BookedJob $bookedJob = null,
        ?Invoice $invoice = null,
        ?string $contractNumber = null,
        ?string $contractTitle = null,
        ?string $issueDate = null,
        ?string $expiresAt = null,
    ): array {
        $clientName = '';
        $clientEmail = '';

        if ($billable instanceof Client) {
            $clientName = $billable->displayName();
            $clientEmail = (string) $billable->email;
        } elseif ($billable instanceof Venue) {
            $clientName = $billable->billingName();
            $clientEmail = (string) $billable->billing_email;
        }

        $invoiceTotal = $invoice
            ? '$'.number_format($invoice->total_cents / 100, 2)
            : '';

        return [
            'client_name' => $clientName,
            'client_email' => $clientEmail,
            'photographer_name' => (string) config('payments.business.name'),
            'photographer_email' => (string) config('payments.business.email'),
            'photographer_phone' => (string) config('payments.business.phone'),
            'business_name' => (string) config('payments.business.name'),
            'contract_number' => (string) ($contractNumber ?? ''),
            'contract_title' => (string) ($contractTitle ?? ''),
            'issue_date' => $issueDate ?? now()->toDateString(),
            'expires_at' => $expiresAt ?? '',
            'event_name' => (string) ($bookedJob?->summary ?? ''),
            'event_date' => $bookedJob?->event_date?->format('F j, Y') ?? '',
            'event_location' => (string) ($bookedJob?->location ?? ''),
            'invoice_number' => (string) ($invoice?->number ?? ''),
            'invoice_total' => $invoiceTotal,
        ];
    }

    /**
     * Replace every "{{key}}" token with its value. Unknown tokens are left
     * untouched so authors can spot typos.
     *
     * @param  array<string, string>  $variables
     */
    public function render(string $body, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function (array $match) use ($variables): string {
                $key = $match[1];

                return array_key_exists($key, $variables)
                    ? $variables[$key]
                    : $match[0];
            },
            $body,
        ) ?? $body;
    }
}

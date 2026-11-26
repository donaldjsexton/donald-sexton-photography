@extends('layouts.admin-v2')

@section('title', $invoice->number)
@section('eyebrow', 'Studio')
@section('heading', $invoice->number)
@section('subheading', $invoice->billableName().' · '.\App\Models\Invoice::statusOptions()[$invoice->status])
@section('header_actions')
    <div class="flex flex-wrap gap-2">
        <x-ui.button variant="secondary" :href="route('admin.invoices.pdf', $invoice)" target="_blank" rel="noopener">Download PDF</x-ui.button>
        @if ($invoice->isEditable())
            <x-ui.button variant="secondary" :href="route('admin.invoices.edit', $invoice)">Edit</x-ui.button>
        @endif
    </div>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $label = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
        $muted = 'text-sm text-gray-500 dark:text-gray-400';
        $val = 'font-medium text-gray-900 dark:text-white';
        $th = 'px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
        $td = 'px-5 py-3 text-gray-700 dark:text-gray-300';
        $tr = 'border-b border-gray-100 dark:border-gray-800';
    @endphp
    <div class="space-y-6">
        <x-ui.card>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div>
                    <p class="{{ $label }}">Bill to</p>
                    <p class="{{ $val }}">{{ $invoice->billableName() }}</p>
                    <p class="{{ $muted }}">{{ $invoice->billableEmail() ?: 'No billing email on file' }}</p>
                    @if ($invoice->isVendorInvoice())
                        <p class="{{ $muted }}">Vendor / venue</p>
                        @if ($invoice->net_terms)
                            <p class="{{ $muted }}">Terms: {{ $invoice->net_terms }}</p>
                        @endif
                    @elseif ($invoice->billable instanceof \App\Models\Client && $invoice->billable->company)
                        <p class="{{ $muted }}">{{ $invoice->billable->company }}</p>
                    @endif
                </div>
                <div>
                    <p class="{{ $label }}">Issued</p>
                    <p class="{{ $val }}">{{ $invoice->issue_date?->format('M j, Y') }}</p>
                    @if ($invoice->due_date)
                        <p class="{{ $label }} mt-3">Due</p>
                        <p class="{{ $val }}">{{ $invoice->due_date->format('M j, Y') }}</p>
                    @endif
                </div>
                <div>
                    <p class="{{ $label }}">Total</p>
                    <p class="{{ $val }}">${{ number_format($invoice->total_cents / 100, 2) }}</p>
                    <p class="{{ $label }} mt-3">Balance due</p>
                    <p class="{{ $val }}">${{ number_format($invoice->amountDueCents() / 100, 2) }}</p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Line items" flush>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
                        <tr>
                            <th class="{{ $th }}">Description</th>
                            <th class="{{ $th }} text-right">Qty</th>
                            <th class="{{ $th }} text-right">Unit</th>
                            <th class="{{ $th }} text-right">Tax</th>
                            <th class="{{ $th }} text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->lineItems as $item)
                            <tr class="{{ $tr }}">
                                <td class="{{ $td }}">{{ $item->description }}</td>
                                <td class="{{ $td }} text-right">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                                <td class="{{ $td }} text-right">${{ number_format($item->unit_price_cents / 100, 2) }}</td>
                                <td class="{{ $td }} text-right">{{ rtrim(rtrim(number_format($item->tax_rate, 2), '0'), '.') }}%</td>
                                <td class="{{ $td }} text-right">${{ number_format($item->total_cents / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="{{ $td }} text-right">Subtotal</td>
                            <td class="{{ $td }} text-right">${{ number_format($invoice->subtotal_cents / 100, 2) }}</td>
                        </tr>
                        @if ($invoice->discount_cents > 0)
                            <tr>
                                <td colspan="4" class="{{ $td }} text-right">Discount</td>
                                <td class="{{ $td }} text-right">−${{ number_format($invoice->discount_cents / 100, 2) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="4" class="{{ $td }} text-right">Tax</td>
                            <td class="{{ $td }} text-right">${{ number_format($invoice->tax_cents / 100, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="{{ $td }} text-right"><strong class="text-gray-900 dark:text-white">Total</strong></td>
                            <td class="{{ $td }} text-right"><strong class="text-gray-900 dark:text-white">${{ number_format($invoice->total_cents / 100, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-ui.card>

        @if ($invoice->installments->isNotEmpty())
            <x-ui.card title="Installments" flush>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
                            <tr>
                                <th class="{{ $th }}">Label</th>
                                <th class="{{ $th }}">Due</th>
                                <th class="{{ $th }}">Amount</th>
                                <th class="{{ $th }}">Paid</th>
                                <th class="{{ $th }}">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->installments as $inst)
                                <tr class="{{ $tr }} last:border-0">
                                    <td class="{{ $td }}">{{ $inst->label ?: 'Installment '.$inst->sequence }}</td>
                                    <td class="{{ $td }}">{{ $inst->due_date?->format('M j, Y') ?: '—' }}</td>
                                    <td class="{{ $td }}">${{ number_format($inst->amount_cents / 100, 2) }}</td>
                                    <td class="{{ $td }}">${{ number_format($inst->amount_paid_cents / 100, 2) }}</td>
                                    <td class="{{ $td }}">{{ ucfirst(str_replace('_', ' ', $inst->status)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card title="Payments" flush>
            @if ($invoice->payments->isEmpty())
                <p class="px-5 py-6 {{ $muted }}">No payments recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
                            <tr>
                                <th class="{{ $th }}">Received</th>
                                <th class="{{ $th }}">Gateway</th>
                                <th class="{{ $th }}">Amount</th>
                                <th class="{{ $th }}">Status</th>
                                <th class="{{ $th }}">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->payments as $payment)
                                <tr class="{{ $tr }}">
                                    <td class="{{ $td }}">{{ $payment->received_at?->format('M j, Y g:i a') ?: $payment->created_at?->format('M j, Y') }}</td>
                                    <td class="{{ $td }}">{{ \App\Models\Payment::gatewayOptions()[$payment->gateway] ?? $payment->gateway }}</td>
                                    <td class="{{ $td }}">
                                        ${{ number_format($payment->amount_cents / 100, 2) }}
                                        @if ($payment->refunded_amount_cents > 0)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                ${{ number_format($payment->refunded_amount_cents / 100, 2) }} refunded
                                            </div>
                                        @endif
                                    </td>
                                    <td class="{{ $td }}">{{ \Illuminate\Support\Str::headline($payment->status) }}</td>
                                    <td class="{{ $td }}"><span class="text-gray-500 dark:text-gray-400">{{ $payment->gateway_payment_id ?: '—' }}</span></td>
                                </tr>
                                @if (Auth::user()->can('refund', $payment) && $payment->refunded_amount_cents < $payment->amount_cents)
                                    <tr class="{{ $tr }}">
                                        <td colspan="5" class="p-0">
                                            <details class="bg-gray-50 px-5 py-2 dark:bg-gray-800/40">
                                                <summary class="cursor-pointer text-sm text-gray-600 dark:text-gray-400">Record refund</summary>
                                                <form method="POST" action="{{ route('admin.invoices.payments.refund', ['invoice' => $invoice, 'payment' => $payment]) }}" class="max-w-none! mt-3 space-y-4 pb-3">
                                                    @csrf
                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                        <x-ui.field label="Amount ($)">
                                                            <input class="{{ $input }}" type="number" step="0.01" min="0.01" max="{{ number_format(($payment->amount_cents - $payment->refunded_amount_cents) / 100, 2, '.', '') }}" name="amount" value="{{ number_format(($payment->amount_cents - $payment->refunded_amount_cents) / 100, 2, '.', '') }}" required>
                                                        </x-ui.field>
                                                        <x-ui.field label="Refunded at">
                                                            <input class="{{ $input }}" type="datetime-local" name="refunded_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                                                        </x-ui.field>
                                                    </div>
                                                    <x-ui.field label="Reason (optional)">
                                                        <input class="{{ $input }}" type="text" name="reason" maxlength="500" placeholder="Why this refund was issued">
                                                    </x-ui.field>
                                                    <x-ui.button variant="secondary" type="submit">Record Refund</x-ui.button>
                                                </form>
                                            </details>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($invoice->status !== \App\Models\Invoice::STATUS_VOID && $invoice->amountDueCents() > 0)
                <details class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                    <summary class="inline-flex cursor-pointer items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Record a manual payment</summary>
                    <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}" class="max-w-none! mt-4 space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field label="Amount ($)">
                                <input class="{{ $input }}" type="number" step="0.01" min="0.01" name="amount" value="{{ number_format($invoice->amountDueCents() / 100, 2, '.', '') }}" required>
                            </x-ui.field>
                            <x-ui.field label="Gateway">
                                <select class="{{ $input }}" name="gateway" required>
                                    @foreach (\App\Models\Payment::gatewayOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected($value === \App\Models\Payment::GATEWAY_MANUAL)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            @if ($invoice->installments->isNotEmpty())
                                <x-ui.field label="Apply to installment">
                                    <select class="{{ $input }}" name="invoice_installment_id">
                                        <option value="">— Unallocated —</option>
                                        @foreach ($invoice->installments as $inst)
                                            <option value="{{ $inst->id }}">
                                                {{ $inst->label ?: 'Installment '.$inst->sequence }}
                                                (${{ number_format($inst->amountDueCents() / 100, 2) }} due)
                                            </option>
                                        @endforeach
                                    </select>
                                </x-ui.field>
                            @endif
                            <x-ui.field label="Received at">
                                <input class="{{ $input }}" type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                            </x-ui.field>
                            <x-ui.field label="Reference / transaction ID">
                                <input class="{{ $input }}" type="text" name="gateway_payment_id" placeholder="Optional">
                            </x-ui.field>
                        </div>
                        <x-ui.button type="submit">Record Payment</x-ui.button>
                    </form>
                </details>
            @endif
        </x-ui.card>

        @if ($invoice->notes || $invoice->terms)
            <x-ui.card>
                @if ($invoice->notes)
                    <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Notes</h3>
                    <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $invoice->notes }}</p>
                @endif
                @if ($invoice->terms)
                    <h3 class="mb-1 mt-4 text-base font-semibold text-gray-900 dark:text-white">Terms</h3>
                    <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $invoice->terms }}</p>
                @endif
            </x-ui.card>
        @endif

        @if ($invoice->internal_notes)
            <x-ui.card title="Internal notes">
                <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $invoice->internal_notes }}</p>
            </x-ui.card>
        @endif

        <x-ui.card title="Actions">
            <div class="flex flex-wrap gap-3">
                @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                    <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" onsubmit="return confirm('Email this invoice to {{ $invoice->billableEmail() }}?');">
                        @csrf
                        <x-ui.button type="submit">Send to Client</x-ui.button>
                    </form>
                @elseif ($invoice->status === \App\Models\Invoice::STATUS_SENT)
                    <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" onsubmit="return confirm('Re-send this invoice to {{ $invoice->billableEmail() }}?');">
                        @csrf
                        <x-ui.button variant="secondary" type="submit">Re-send</x-ui.button>
                    </form>
                @endif

                @if ($invoice->status !== \App\Models\Invoice::STATUS_VOID)
                    <form method="POST" action="{{ route('admin.invoices.void', $invoice) }}" onsubmit="return confirm('Void this invoice? It will no longer be payable.');">
                        @csrf
                        <x-ui.button variant="secondary" type="submit">Void</x-ui.button>
                    </form>
                @endif

                @if ($invoice->isEditable())
                    <form method="POST" action="{{ route('admin.invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this draft invoice? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="danger" type="submit">Delete Draft</x-ui.button>
                    </form>
                @endif
            </div>
        </x-ui.card>
    </div>
@endsection

@extends('layouts.admin-v2')

@section('title', $invoice->exists ? 'Edit Invoice' : 'New Invoice')
@section('eyebrow', 'Studio')
@section('heading', $invoice->exists ? 'Edit '.$invoice->number : 'New Invoice')
@if ($invoice->billable)
    @section('subheading', 'For '.$invoice->billable->displayName())
@endif
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form
        method="POST"
        action="{{ $invoice->exists ? route('admin.invoices.update', $invoice) : route('admin.invoices.store') }}"
        class="max-w-none! space-y-6"
    >
        @csrf
        @if ($invoice->exists)
            @method('PUT')
        @endif

        <x-ui.card title="Bill to & dates">
            <div class="space-y-4">

            @php
                $currentBillableType = old('billable_type', $billableType ?? 'client');
                $currentBillableId = (int) old('billable_id', $invoice->billable_id);
            @endphp

            <fieldset class="mb-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-700 dark:text-gray-300">
                <legend class="mb-2 text-sm text-gray-500 dark:text-gray-400">Counterparty type</legend>
                <label class="inline-flex items-center gap-1.5">
                    <input type="radio" name="billable_type" value="client" data-billable-type="client" @checked($currentBillableType === 'client')>
                    Client (couple)
                </label>
                <label class="inline-flex items-center gap-1.5">
                    <input type="radio" name="billable_type" value="venue" data-billable-type="venue" @checked($currentBillableType === 'venue')>
                    Vendor / venue
                </label>
            </fieldset>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Client" data-billable-picker="client" :style="$currentBillableType !== 'client' ? 'display:none;' : ''">
                    <select class="{{ $input }}" name="billable_id" data-billable-id="client" @if ($currentBillableType !== 'client') disabled @endif>
                        <option value="">— Choose a client —</option>
                        @foreach ($clients as $option)
                            <option value="{{ $option->id }}" @selected($currentBillableType === 'client' && $currentBillableId === $option->id)>
                                {{ $option->displayName() }} ({{ $option->email }})
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Vendor / venue" data-billable-picker="venue" :style="$currentBillableType !== 'venue' ? 'display:none;' : ''">
                    <select class="{{ $input }}" name="billable_id" data-billable-id="venue" @if ($currentBillableType !== 'venue') disabled @endif>
                        <option value="">— Choose a venue —</option>
                        @foreach ($venues as $option)
                            <option value="{{ $option->id }}" @selected($currentBillableType === 'venue' && $currentBillableId === $option->id)>
                                {{ $option->billingName() }} ({{ $option->billing_email }})
                            </option>
                        @endforeach
                    </select>
                    @if ($venues->isEmpty())
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">No venues with billing details yet — add billing fields to a venue first.</span>
                    @endif
                </x-ui.field>

                <x-ui.field label="Booked job (optional)">
                    <select class="{{ $input }}" name="booked_job_id">
                        <option value="">— None —</option>
                        @foreach ($bookedJobs as $job)
                            <option value="{{ $job->id }}" @selected(old('booked_job_id', $invoice->booked_job_id) == $job->id)>
                                {{ $job->summary }} — {{ $job->event_date?->format('M j, Y') }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Issue date">
                    <input class="{{ $input }}" type="date" name="issue_date" value="{{ old('issue_date', optional($invoice->issue_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
                </x-ui.field>

                <x-ui.field label="Due date">
                    <input class="{{ $input }}" type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
                </x-ui.field>

                <x-ui.field label="Net terms (vendor invoices)">
                    <input class="{{ $input }}" type="text" name="net_terms" maxlength="50" placeholder="e.g. Net 30" value="{{ old('net_terms', $invoice->net_terms) }}">
                </x-ui.field>

                <x-ui.field label="Default tax rate (%)">
                    <input class="{{ $input }}" type="number" step="0.001" min="0" max="100" name="default_tax_rate" value="{{ old('default_tax_rate', $invoice->default_tax_rate ?? config('payments.default_tax_rate')) }}">
                </x-ui.field>

                <x-ui.field label="Discount ($)">
                    <input class="{{ $input }}" type="number" step="0.01" min="0" name="discount" value="{{ old('discount', number_format(($invoice->discount_cents ?? 0) / 100, 2, '.', '')) }}">
                </x-ui.field>
            </div>

            <script>
            (function () {
                document.querySelectorAll('[data-billable-type]').forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        var picked = radio.value;
                        document.querySelectorAll('[data-billable-picker]').forEach(function (label) {
                            var on = label.getAttribute('data-billable-picker') === picked;
                            label.style.display = on ? '' : 'none';
                            var select = label.querySelector('select');
                            if (select) { select.disabled = ! on; if (! on) { select.value = ''; } }
                        });
                    });
                });
            })();
            </script>
        </div>
        </x-ui.card>

        <x-ui.card title="Line items">
            <div class="space-y-4">

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm" id="line-items-table">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2" style="min-width:240px;">Description</th>
                            <th class="px-3 py-2" style="width:90px;">Qty</th>
                            <th class="px-3 py-2" style="width:130px;">Unit ($)</th>
                            <th class="px-3 py-2" style="width:110px;">Tax %</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody data-rows-target>
                        @foreach ($lineItems as $i => $item)
                            <tr data-row>
                                <td class="p-2"><input class="{{ $input }}" type="text" name="line_items[{{ $i }}][description]" value="{{ old("line_items.$i.description", $item->description) }}" required></td>
                                <td class="p-2"><input class="{{ $input }}" type="number" step="0.01" min="0.01" name="line_items[{{ $i }}][quantity]" value="{{ old("line_items.$i.quantity", $item->quantity ?? 1) }}" required></td>
                                <td class="p-2"><input class="{{ $input }}" type="number" step="0.01" min="0" name="line_items[{{ $i }}][unit_price]" value="{{ old("line_items.$i.unit_price", number_format(($item->unit_price_cents ?? 0) / 100, 2, '.', '')) }}" required></td>
                                <td class="p-2"><input class="{{ $input }}" type="number" step="0.001" min="0" max="100" name="line_items[{{ $i }}][tax_rate]" value="{{ old("line_items.$i.tax_rate", $item->tax_rate ?? config('payments.default_tax_rate')) }}"></td>
                                <td class="p-2"><x-ui.button variant="secondary" type="button" data-remove-row>Remove</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.button variant="secondary" type="button" data-add-row="line_items">+ Add line item</x-ui.button>

            <template data-row-template="line_items">
                <tr data-row>
                    <td class="p-2"><input class="{{ $input }}" type="text" name="line_items[__INDEX__][description]" required></td>
                    <td class="p-2"><input class="{{ $input }}" type="number" step="0.01" min="0.01" name="line_items[__INDEX__][quantity]" value="1" required></td>
                    <td class="p-2"><input class="{{ $input }}" type="number" step="0.01" min="0" name="line_items[__INDEX__][unit_price]" required></td>
                    <td class="p-2"><input class="{{ $input }}" type="number" step="0.001" min="0" max="100" name="line_items[__INDEX__][tax_rate]" value="{{ config('payments.default_tax_rate') }}"></td>
                    <td class="p-2"><x-ui.button variant="secondary" type="button" data-remove-row>Remove</x-ui.button></td>
                </tr>
            </template>
        </div>
        </x-ui.card>

        <x-ui.card title="Installments (optional)">
            <div class="space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Split the invoice into a payment schedule (e.g. retainer + balance). Leave blank for a single payment.</p>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm" id="installments-table">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2" style="min-width:200px;">Label</th>
                            <th class="px-3 py-2" style="width:160px;">Due date</th>
                            <th class="px-3 py-2" style="width:140px;">Amount ($)</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody data-rows-target>
                        @foreach ($installments as $i => $inst)
                            <tr data-row>
                                <td class="p-2"><input class="{{ $input }}" type="text" name="installments[{{ $i }}][label]" value="{{ old("installments.$i.label", $inst->label) }}"></td>
                                <td class="p-2"><input class="{{ $input }}" type="date" name="installments[{{ $i }}][due_date]" value="{{ old("installments.$i.due_date", optional($inst->due_date)->format('Y-m-d')) }}"></td>
                                <td class="p-2"><input class="{{ $input }}" type="number" step="0.01" min="0.01" name="installments[{{ $i }}][amount]" value="{{ old("installments.$i.amount", number_format(($inst->amount_cents ?? 0) / 100, 2, '.', '')) }}"></td>
                                <td class="p-2"><x-ui.button variant="secondary" type="button" data-remove-row>Remove</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.button variant="secondary" type="button" data-add-row="installments">+ Add installment</x-ui.button>

            <template data-row-template="installments">
                <tr data-row>
                    <td class="p-2"><input class="{{ $input }}" type="text" name="installments[__INDEX__][label]" placeholder="Retainer"></td>
                    <td class="p-2"><input class="{{ $input }}" type="date" name="installments[__INDEX__][due_date]"></td>
                    <td class="p-2"><input class="{{ $input }}" type="number" step="0.01" min="0.01" name="installments[__INDEX__][amount]"></td>
                    <td class="p-2"><x-ui.button variant="secondary" type="button" data-remove-row>Remove</x-ui.button></td>
                </tr>
            </template>
        </div>
        </x-ui.card>

        <x-ui.card title="Notes">
            <div class="space-y-4">
            <x-ui.field label="Notes for the client">
                <textarea class="{{ $input }}" name="notes" rows="3">{{ old('notes', $invoice->notes) }}</textarea>
            </x-ui.field>
            <x-ui.field label="Internal notes (admin only)">
                <textarea class="{{ $input }}" name="internal_notes" rows="3">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
            </x-ui.field>
            <x-ui.field label="Terms">
                <textarea class="{{ $input }}" name="terms" rows="3">{{ old('terms', $invoice->terms) }}</textarea>
            </x-ui.field>
        </div>
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">{{ $invoice->exists ? 'Save Changes' : 'Create Invoice' }}</x-ui.button>
            @if ($invoice->exists)
                <x-ui.button variant="secondary" href="{{ route('admin.invoices.show', $invoice) }}">Cancel</x-ui.button>
            @else
                <x-ui.button variant="secondary" href="{{ route('admin.invoices.index') }}">Cancel</x-ui.button>
            @endif
        </div>
    </form>

    <script>
    (function () {
        var counters = { line_items: {{ count($lineItems) }}, installments: {{ count($installments) }} };

        document.addEventListener('click', function (event) {
            var addBtn = event.target.closest('[data-add-row]');
            if (addBtn) {
                event.preventDefault();
                var key = addBtn.getAttribute('data-add-row');
                var template = document.querySelector('[data-row-template="' + key + '"]');
                var target = addBtn.closest('section').querySelector('[data-rows-target]');
                if (!template || !target) return;

                var html = template.innerHTML.replace(/__INDEX__/g, counters[key]++);
                var wrapper = document.createElement('tbody');
                wrapper.innerHTML = html;
                target.appendChild(wrapper.firstElementChild);
                return;
            }

            var removeBtn = event.target.closest('[data-remove-row]');
            if (removeBtn) {
                event.preventDefault();
                var row = removeBtn.closest('[data-row]');
                var body = row && row.parentElement;
                if (row && body && body.querySelectorAll('[data-row]').length > 1) {
                    row.remove();
                } else if (row && body) {
                    row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
                }
            }
        });
    })();
    </script>
@endsection

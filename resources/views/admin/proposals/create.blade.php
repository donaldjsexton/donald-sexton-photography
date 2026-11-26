@extends('layouts.admin-v2')

@section('title', 'New Booking Proposal')
@section('eyebrow', 'Studio')
@section('heading', 'New Booking Proposal')
@if ($client)
    @section('subheading', 'For '.$client->displayName())
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

    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        Creates a draft contract and a draft invoice linked together. Review them, then send both at once with
        “Send as Proposal” — the client signs and pays the deposit on one page.
    </p>

    <form method="POST" action="{{ route('admin.proposals.store') }}" class="max-w-none! space-y-6">
        @csrf

        <x-ui.card title="Client & event">
            <div class="space-y-4">

            @php
                $currentClientId = (int) old('client_id', $client?->id);
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Client">
                    <select class="{{ $input }}" name="client_id" id="proposal-client" required>
                        <option value="">— Choose a client —</option>
                        @foreach ($clients as $option)
                            <option value="{{ $option->id }}" @selected($currentClientId === $option->id)>
                                {{ $option->displayName() }} ({{ $option->email }})
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Booked job (optional)">
                    <select class="{{ $input }}" name="booked_job_id">
                        <option value="">— None —</option>
                        @foreach ($bookedJobs as $job)
                            <option value="{{ $job->id }}" @selected(old('booked_job_id', $bookedJob?->id) == $job->id)>
                                {{ $job->summary }} — {{ $job->event_date?->format('M j, Y') }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Issue date">
                    <input class="{{ $input }}" type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required>
                </x-ui.field>

                <x-ui.field label="Contract expires (optional)">
                    <input class="{{ $input }}" type="date" name="expires_at" value="{{ old('expires_at', now()->addDays(14)->toDateString()) }}">
                </x-ui.field>

                <x-ui.field label="Invoice due date (optional)">
                    <input class="{{ $input }}" type="date" name="due_date" value="{{ old('due_date', now()->addDays(14)->toDateString()) }}">
                </x-ui.field>
            </div>
        </div>
        </x-ui.card>

        <x-ui.card title="Agreement">
            <div class="space-y-4">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Title">
                    <input class="{{ $input }}" type="text" name="title" maxlength="255" required value="{{ old('title', $title) }}">
                </x-ui.field>

                <x-ui.field label="Apply template">
                    <select class="{{ $input }}" id="contract-template-picker">
                        <option value="">— Choose a template —</option>
                        @foreach ($templates as $option)
                            <option value="{{ $option->id }}" @selected(old('contract_template_id', $defaultTemplate?->id) == $option->id)>
                                {{ $option->name }}{{ $option->is_default ? ' (default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">Inserts the template body and replaces variables like
                        <code>&#123;&#123;client_name&#125;&#125;</code>. Edit freely after applying.</span>
                </x-ui.field>
            </div>

            <input type="hidden" name="contract_template_id" id="contract-template-id" value="{{ old('contract_template_id', $defaultTemplate?->id) }}">

            <x-ui.field label="Body">
                <textarea class="{{ $input }}" name="body" id="contract-body" rows="16" required>{{ old('body', $body) }}</textarea>
            </x-ui.field>

            <details class="mt-2">
                <summary class="cursor-pointer text-sm text-gray-500 dark:text-gray-400">Available merge variables</summary>
                <ul class="mt-2 space-y-1 text-sm text-gray-500 dark:text-gray-400">
                    @foreach ($availableVariables as $key => $description)
                        <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> — {{ $description }}</li>
                    @endforeach
                </ul>
            </details>
        </div>
        </x-ui.card>

        <x-ui.card title="Invoice line items">
            <div class="space-y-4">

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
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
                                <td class="p-2"><input class="{{ $input }}" type="number" step="0.01" min="0" name="line_items[{{ $i }}][unit_price]" value="{{ old("line_items.$i.unit_price") }}" required></td>
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

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Default tax rate (%)">
                    <input class="{{ $input }}" type="number" step="0.001" min="0" max="100" name="default_tax_rate" value="{{ old('default_tax_rate', config('payments.default_tax_rate')) }}">
                </x-ui.field>
                <x-ui.field label="Discount ($)">
                    <input class="{{ $input }}" type="number" step="0.01" min="0" name="discount" value="{{ old('discount', '0.00') }}">
                </x-ui.field>
            </div>
        </div>
        </x-ui.card>

        <x-ui.card title="Deposit schedule (optional)">
            <div class="space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Split the invoice into installments (e.g. deposit + balance). Leave blank for a single payment.</p>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2" style="min-width:200px;">Label</th>
                            <th class="px-3 py-2" style="width:160px;">Due date</th>
                            <th class="px-3 py-2" style="width:140px;">Amount ($)</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody data-rows-target>
                    </tbody>
                </table>
            </div>

            <x-ui.button variant="secondary" type="button" data-add-row="installments">+ Add installment</x-ui.button>

            <template data-row-template="installments">
                <tr data-row>
                    <td class="p-2"><input class="{{ $input }}" type="text" name="installments[__INDEX__][label]" placeholder="Deposit"></td>
                    <td class="p-2"><input class="{{ $input }}" type="date" name="installments[__INDEX__][due_date]"></td>
                    <td class="p-2"><input class="{{ $input }}" type="number" step="0.01" min="0.01" name="installments[__INDEX__][amount]"></td>
                    <td class="p-2"><x-ui.button variant="secondary" type="button" data-remove-row>Remove</x-ui.button></td>
                </tr>
            </template>
        </div>
        </x-ui.card>

        <x-ui.card title="Notes">
            <div class="space-y-4">
            <x-ui.field label="Invoice notes for the client">
                <textarea class="{{ $input }}" name="invoice_notes" rows="3">{{ old('invoice_notes') }}</textarea>
            </x-ui.field>
            <x-ui.field label="Invoice terms">
                <textarea class="{{ $input }}" name="invoice_terms" rows="3">{{ old('invoice_terms') }}</textarea>
            </x-ui.field>
            <x-ui.field label="Internal notes (admin only)">
                <textarea class="{{ $input }}" name="internal_notes" rows="3">{{ old('internal_notes') }}</textarea>
            </x-ui.field>
        </div>
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Create Proposal</x-ui.button>
            <x-ui.button variant="secondary" href="{{ $client ? route('admin.clients.show', $client) : route('admin.contracts.index') }}">Cancel</x-ui.button>
        </div>
    </form>

    <script>
    (function () {
        var counters = { line_items: {{ count($lineItems) }}, installments: 0 };

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
                if (row && body) { row.remove(); }
            }
        });
    })();
    </script>

    <script>
    (function () {
        var picker = document.getElementById('contract-template-picker');
        var hidden = document.getElementById('contract-template-id');
        var body = document.getElementById('contract-body');
        var titleField = document.querySelector('input[name="title"]');
        var clientSelect = document.getElementById('proposal-client');
        var previewUrl = @json(route('admin.contracts.preview'));
        var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        if (!picker) { return; }

        // Title the server prefilled (or a title we applied). Anything else means
        // the user typed their own and we leave it alone.
        var appliedTitle = titleField ? titleField.value : '';

        picker.addEventListener('change', function () {
            var previousId = hidden.value;
            var id = picker.value;
            if (!id) {
                hidden.value = '';
                return;
            }

            if (body.value.trim() && !confirm('Replace the current contract body with this template?')) {
                picker.value = previousId;
                return;
            }

            hidden.value = id;

            fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    template_id: id,
                    billable_type: 'client',
                    billable_id: clientSelect ? clientSelect.value : '',
                    booked_job_id: document.querySelector('select[name="booked_job_id"]')?.value || '',
                }),
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (data.title && titleField && (!titleField.value.trim() || titleField.value === appliedTitle)) {
                    titleField.value = data.title;
                    appliedTitle = data.title;
                }
                body.value = data.body || '';
            }).catch(function () {
                alert('Could not load template.');
            });
        });
    })();
    </script>
@endsection

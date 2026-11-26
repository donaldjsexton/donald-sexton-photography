@extends('layouts.admin-v2')

@section('title', $contract->exists ? 'Edit Contract' : 'New Contract')
@section('eyebrow', 'Studio')
@section('heading', $contract->exists ? 'Edit '.$contract->number : 'New Contract')
@if ($contract->billable)
    @section('subheading', 'For '.$contract->billable->displayName())
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
        action="{{ $contract->exists ? route('admin.contracts.update', $contract) : route('admin.contracts.store') }}"
        class="max-w-none! space-y-6"
    >
        @csrf
        @if ($contract->exists)
            @method('PUT')
        @endif

        <x-ui.card title="Counterparty & references">
            <div class="space-y-4">

            @php
                $currentBillableType = old('billable_type', $billableType ?? 'client');
                $currentBillableId = (int) old('billable_id', $contract->billable_id);
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
                </x-ui.field>

                <x-ui.field label="Booked job (optional)">
                    <select class="{{ $input }}" name="booked_job_id">
                        <option value="">— None —</option>
                        @foreach ($bookedJobs as $job)
                            <option value="{{ $job->id }}" @selected(old('booked_job_id', $contract->booked_job_id) == $job->id)>
                                {{ $job->summary }} — {{ $job->event_date?->format('M j, Y') }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Linked invoice (optional)">
                    <select class="{{ $input }}" name="invoice_id">
                        <option value="">— None —</option>
                        @foreach ($invoices as $option)
                            <option value="{{ $option->id }}" @selected(old('invoice_id', $contract->invoice_id) == $option->id)>
                                {{ $option->number }} — ${{ number_format($option->total_cents / 100, 2) }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Issue date">
                    <input class="{{ $input }}" type="date" name="issue_date" value="{{ old('issue_date', optional($contract->issue_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
                </x-ui.field>

                <x-ui.field label="Expires (optional)">
                    <input class="{{ $input }}" type="date" name="expires_at" value="{{ old('expires_at', optional($contract->expires_at)->format('Y-m-d')) }}">
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

        <x-ui.card title="Contract content">
            <div class="space-y-4">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Title">
                    <input class="{{ $input }}" type="text" name="title" maxlength="255" required value="{{ old('title', $contract->title) }}">
                </x-ui.field>

                <x-ui.field label="Apply template">
                    <select class="{{ $input }}" id="contract-template-picker">
                        <option value="">— Choose a template —</option>
                        @foreach ($templates as $option)
                            <option value="{{ $option->id }}" @selected(old('contract_template_id', $contract->contract_template_id) == $option->id)>
                                {{ $option->name }}{{ $option->is_default ? ' (default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">Inserts the template body and replaces variables like
                        <code>&#123;&#123;client_name&#125;&#125;</code>. You can edit freely after applying.</span>
                </x-ui.field>
            </div>

            <input type="hidden" name="contract_template_id" id="contract-template-id" value="{{ old('contract_template_id', $contract->contract_template_id) }}">

            <x-ui.field label="Body">
                <textarea class="{{ $input }}" name="body" id="contract-body" rows="18" required>{{ old('body', $contract->body) }}</textarea>
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

        <x-ui.card title="Internal notes">
            <div class="space-y-4">
            <x-ui.field label="Notes for the studio (not shown to the client)">
                <textarea class="{{ $input }}" name="internal_notes" rows="3">{{ old('internal_notes', $contract->internal_notes) }}</textarea>
            </x-ui.field>
        </div>
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">{{ $contract->exists ? 'Save Changes' : 'Create Contract' }}</x-ui.button>
            @if ($contract->exists)
                <x-ui.button variant="secondary" href="{{ route('admin.contracts.show', $contract) }}">Cancel</x-ui.button>
            @else
                <x-ui.button variant="secondary" href="{{ route('admin.contracts.index') }}">Cancel</x-ui.button>
            @endif
        </div>
    </form>

    <script>
    (function () {
        var picker = document.getElementById('contract-template-picker');
        var hidden = document.getElementById('contract-template-id');
        var body = document.getElementById('contract-body');
        var titleField = document.querySelector('input[name="title"]');
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

            var billableType = document.querySelector('input[name="billable_type"]:checked')?.value || '';
            var billableSelect = document.querySelector('select[data-billable-id="' + billableType + '"]');
            var billableId = billableSelect ? billableSelect.value : '';
            var bookedJobId = document.querySelector('select[name="booked_job_id"]')?.value || '';
            var invoiceId = document.querySelector('select[name="invoice_id"]')?.value || '';

            fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    template_id: id,
                    billable_type: billableType,
                    billable_id: billableId,
                    booked_job_id: bookedJobId,
                    invoice_id: invoiceId,
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

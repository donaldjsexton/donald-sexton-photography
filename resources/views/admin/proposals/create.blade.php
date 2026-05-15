@extends('layouts.admin')

@section('title', 'New Booking Proposal')
@section('eyebrow', 'Studio')
@section('heading', 'New Booking Proposal')
@if ($client)
    @section('subheading', 'For '.$client->displayName())
@endif
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <p class="meta" style="margin:0 0 1rem;">
        Creates a draft contract and a draft invoice linked together. Review them, then send both at once with
        “Send as Proposal” — the client signs and pays the deposit on one page.
    </p>

    <form method="POST" action="{{ route('admin.proposals.store') }}" class="admin-form">
        @csrf

        <section class="admin-card">
            <h3>Client &amp; event</h3>

            @php
                $currentClientId = (int) old('client_id', $client?->id);
            @endphp

            <div class="field-grid">
                <label>
                    Client
                    <select name="client_id" id="proposal-client" required>
                        <option value="">— Choose a client —</option>
                        @foreach ($clients as $option)
                            <option value="{{ $option->id }}" @selected($currentClientId === $option->id)>
                                {{ $option->displayName() }} ({{ $option->email }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Booked job (optional)
                    <select name="booked_job_id">
                        <option value="">— None —</option>
                        @foreach ($bookedJobs as $job)
                            <option value="{{ $job->id }}" @selected(old('booked_job_id', $bookedJob?->id) == $job->id)>
                                {{ $job->summary }} — {{ $job->event_date?->format('M j, Y') }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="field-grid">
                <label>
                    Issue date
                    <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required>
                </label>

                <label>
                    Contract expires (optional)
                    <input type="date" name="expires_at" value="{{ old('expires_at', now()->addDays(14)->toDateString()) }}">
                </label>

                <label>
                    Invoice due date (optional)
                    <input type="date" name="due_date" value="{{ old('due_date', now()->addDays(14)->toDateString()) }}">
                </label>
            </div>
        </section>

        <section class="admin-card">
            <h3>Agreement</h3>

            <div class="field-grid">
                <label>
                    Title
                    <input type="text" name="title" maxlength="255" required value="{{ old('title', $title) }}">
                </label>

                <label>
                    Apply template
                    <select id="contract-template-picker">
                        <option value="">— Choose a template —</option>
                        @foreach ($templates as $option)
                            <option value="{{ $option->id }}" @selected(old('contract_template_id', $defaultTemplate?->id) == $option->id)>
                                {{ $option->name }}{{ $option->is_default ? ' (default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="meta">Inserts the template body and replaces variables like
                        <code>&#123;&#123;client_name&#125;&#125;</code>. Edit freely after applying.</span>
                </label>
            </div>

            <input type="hidden" name="contract_template_id" id="contract-template-id" value="{{ old('contract_template_id', $defaultTemplate?->id) }}">

            <label>
                Body
                <textarea name="body" id="contract-body" rows="16" required>{{ old('body', $body) }}</textarea>
            </label>

            <details style="margin-top:8px;">
                <summary class="meta" style="cursor:pointer;">Available merge variables</summary>
                <ul class="meta" style="margin-top:8px;">
                    @foreach ($availableVariables as $key => $description)
                        <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> — {{ $description }}</li>
                    @endforeach
                </ul>
            </details>
        </section>

        <section class="admin-card">
            <h3>Invoice line items</h3>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="min-width:240px;">Description</th>
                            <th style="width:90px;">Qty</th>
                            <th style="width:130px;">Unit ($)</th>
                            <th style="width:110px;">Tax %</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-rows-target>
                        @foreach ($lineItems as $i => $item)
                            <tr data-row>
                                <td><input type="text" name="line_items[{{ $i }}][description]" value="{{ old("line_items.$i.description", $item->description) }}" required></td>
                                <td><input type="number" step="0.01" min="0.01" name="line_items[{{ $i }}][quantity]" value="{{ old("line_items.$i.quantity", $item->quantity ?? 1) }}" required></td>
                                <td><input type="number" step="0.01" min="0" name="line_items[{{ $i }}][unit_price]" value="{{ old("line_items.$i.unit_price") }}" required></td>
                                <td><input type="number" step="0.001" min="0" max="100" name="line_items[{{ $i }}][tax_rate]" value="{{ old("line_items.$i.tax_rate", $item->tax_rate ?? config('payments.default_tax_rate')) }}"></td>
                                <td><button type="button" class="cta-secondary" data-remove-row>Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" class="cta-secondary" data-add-row="line_items">+ Add line item</button>

            <template data-row-template="line_items">
                <tr data-row>
                    <td><input type="text" name="line_items[__INDEX__][description]" required></td>
                    <td><input type="number" step="0.01" min="0.01" name="line_items[__INDEX__][quantity]" value="1" required></td>
                    <td><input type="number" step="0.01" min="0" name="line_items[__INDEX__][unit_price]" required></td>
                    <td><input type="number" step="0.001" min="0" max="100" name="line_items[__INDEX__][tax_rate]" value="{{ config('payments.default_tax_rate') }}"></td>
                    <td><button type="button" class="cta-secondary" data-remove-row>Remove</button></td>
                </tr>
            </template>

            <div class="field-grid" style="margin-top:1rem;">
                <label>
                    Default tax rate (%)
                    <input type="number" step="0.001" min="0" max="100" name="default_tax_rate" value="{{ old('default_tax_rate', config('payments.default_tax_rate')) }}">
                </label>
                <label>
                    Discount ($)
                    <input type="number" step="0.01" min="0" name="discount" value="{{ old('discount', '0.00') }}">
                </label>
            </div>
        </section>

        <section class="admin-card">
            <h3>Deposit schedule (optional)</h3>
            <p class="meta">Split the invoice into installments (e.g. deposit + balance). Leave blank for a single payment.</p>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="min-width:200px;">Label</th>
                            <th style="width:160px;">Due date</th>
                            <th style="width:140px;">Amount ($)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-rows-target>
                    </tbody>
                </table>
            </div>

            <button type="button" class="cta-secondary" data-add-row="installments">+ Add installment</button>

            <template data-row-template="installments">
                <tr data-row>
                    <td><input type="text" name="installments[__INDEX__][label]" placeholder="Deposit"></td>
                    <td><input type="date" name="installments[__INDEX__][due_date]"></td>
                    <td><input type="number" step="0.01" min="0.01" name="installments[__INDEX__][amount]"></td>
                    <td><button type="button" class="cta-secondary" data-remove-row>Remove</button></td>
                </tr>
            </template>
        </section>

        <section class="admin-card">
            <h3>Notes</h3>
            <label>
                Invoice notes for the client
                <textarea name="invoice_notes" rows="3">{{ old('invoice_notes') }}</textarea>
            </label>
            <label>
                Invoice terms
                <textarea name="invoice_terms" rows="3">{{ old('invoice_terms') }}</textarea>
            </label>
            <label>
                Internal notes (admin only)
                <textarea name="internal_notes" rows="3">{{ old('internal_notes') }}</textarea>
            </label>
        </section>

        <div class="form-actions">
            <button class="cta" type="submit">Create Proposal</button>
            <a class="cta-secondary" href="{{ $client ? route('admin.clients.show', $client) : route('admin.contracts.index') }}">Cancel</a>
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

        picker.addEventListener('change', function () {
            var id = picker.value;
            hidden.value = id || '';
            if (!id) { return; }

            if (body.value.trim() && !confirm('Replace the current contract body with this template?')) {
                picker.value = hidden.value;
                return;
            }

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
                if (data.title && titleField && !titleField.value.trim()) {
                    titleField.value = data.title;
                }
                body.value = data.body || '';
            }).catch(function () {
                alert('Could not load template.');
            });
        });
    })();
    </script>
@endsection

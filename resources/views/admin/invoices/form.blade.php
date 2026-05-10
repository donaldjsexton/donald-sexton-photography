@extends('layouts.admin')

@section('title', $invoice->exists ? 'Edit Invoice' : 'New Invoice')
@section('eyebrow', 'Studio')
@section('heading', $invoice->exists ? 'Edit '.$invoice->number : 'New Invoice')
@if ($invoice->billable)
    @section('subheading', 'For '.$invoice->billable->displayName())
@endif
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form
        method="POST"
        action="{{ $invoice->exists ? route('admin.invoices.update', $invoice) : route('admin.invoices.store') }}"
        class="admin-form"
    >
        @csrf
        @if ($invoice->exists)
            @method('PUT')
        @endif

        <section class="admin-card">
            <h3>Bill to &amp; dates</h3>

            @php
                $currentBillableType = old('billable_type', $billableType ?? 'client');
                $currentBillableId = (int) old('billable_id', $invoice->billable_id);
            @endphp

            <fieldset style="border:0; padding:0; margin:0 0 16px;">
                <legend class="meta">Counterparty type</legend>
                <label style="display:inline-flex; align-items:center; gap:6px; margin-right:18px;">
                    <input type="radio" name="billable_type" value="client" data-billable-type="client" @checked($currentBillableType === 'client')>
                    Client (couple)
                </label>
                <label style="display:inline-flex; align-items:center; gap:6px;">
                    <input type="radio" name="billable_type" value="venue" data-billable-type="venue" @checked($currentBillableType === 'venue')>
                    Vendor / venue
                </label>
            </fieldset>

            <div class="field-grid">
                <label data-billable-picker="client" @if ($currentBillableType !== 'client') style="display:none;" @endif>
                    Client
                    <select name="billable_id" data-billable-id="client" @if ($currentBillableType !== 'client') disabled @endif>
                        <option value="">— Choose a client —</option>
                        @foreach ($clients as $option)
                            <option value="{{ $option->id }}" @selected($currentBillableType === 'client' && $currentBillableId === $option->id)>
                                {{ $option->displayName() }} ({{ $option->email }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label data-billable-picker="venue" @if ($currentBillableType !== 'venue') style="display:none;" @endif>
                    Vendor / venue
                    <select name="billable_id" data-billable-id="venue" @if ($currentBillableType !== 'venue') disabled @endif>
                        <option value="">— Choose a venue —</option>
                        @foreach ($venues as $option)
                            <option value="{{ $option->id }}" @selected($currentBillableType === 'venue' && $currentBillableId === $option->id)>
                                {{ $option->billingName() }} ({{ $option->billing_email }})
                            </option>
                        @endforeach
                    </select>
                    @if ($venues->isEmpty())
                        <span class="meta">No venues with billing details yet — add billing fields to a venue first.</span>
                    @endif
                </label>

                <label>
                    Booked job (optional)
                    <select name="booked_job_id">
                        <option value="">— None —</option>
                        @foreach ($bookedJobs as $job)
                            <option value="{{ $job->id }}" @selected(old('booked_job_id', $invoice->booked_job_id) == $job->id)>
                                {{ $job->summary }} — {{ $job->event_date?->format('M j, Y') }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="field-grid">
                <label>
                    Issue date
                    <input type="date" name="issue_date" value="{{ old('issue_date', optional($invoice->issue_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
                </label>

                <label>
                    Due date
                    <input type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
                </label>

                <label>
                    Net terms (vendor invoices)
                    <input type="text" name="net_terms" maxlength="50" placeholder="e.g. Net 30" value="{{ old('net_terms', $invoice->net_terms) }}">
                </label>

                <label>
                    Default tax rate (%)
                    <input type="number" step="0.001" min="0" max="100" name="default_tax_rate" value="{{ old('default_tax_rate', $invoice->default_tax_rate ?? config('payments.default_tax_rate')) }}">
                </label>

                <label>
                    Discount ($)
                    <input type="number" step="0.01" min="0" name="discount" value="{{ old('discount', number_format(($invoice->discount_cents ?? 0) / 100, 2, '.', '')) }}">
                </label>
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
        </section>

        <section class="admin-card">
            <h3>Line items</h3>

            <div class="admin-table-wrap">
                <table class="admin-table" id="line-items-table">
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
                                <td><input type="number" step="0.01" min="0" name="line_items[{{ $i }}][unit_price]" value="{{ old("line_items.$i.unit_price", number_format(($item->unit_price_cents ?? 0) / 100, 2, '.', '')) }}" required></td>
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
        </section>

        <section class="admin-card">
            <h3>Installments (optional)</h3>
            <p class="meta">Split the invoice into a payment schedule (e.g. retainer + balance). Leave blank for a single payment.</p>

            <div class="admin-table-wrap">
                <table class="admin-table" id="installments-table">
                    <thead>
                        <tr>
                            <th style="min-width:200px;">Label</th>
                            <th style="width:160px;">Due date</th>
                            <th style="width:140px;">Amount ($)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-rows-target>
                        @foreach ($installments as $i => $inst)
                            <tr data-row>
                                <td><input type="text" name="installments[{{ $i }}][label]" value="{{ old("installments.$i.label", $inst->label) }}"></td>
                                <td><input type="date" name="installments[{{ $i }}][due_date]" value="{{ old("installments.$i.due_date", optional($inst->due_date)->format('Y-m-d')) }}"></td>
                                <td><input type="number" step="0.01" min="0.01" name="installments[{{ $i }}][amount]" value="{{ old("installments.$i.amount", number_format(($inst->amount_cents ?? 0) / 100, 2, '.', '')) }}"></td>
                                <td><button type="button" class="cta-secondary" data-remove-row>Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" class="cta-secondary" data-add-row="installments">+ Add installment</button>

            <template data-row-template="installments">
                <tr data-row>
                    <td><input type="text" name="installments[__INDEX__][label]" placeholder="Retainer"></td>
                    <td><input type="date" name="installments[__INDEX__][due_date]"></td>
                    <td><input type="number" step="0.01" min="0.01" name="installments[__INDEX__][amount]"></td>
                    <td><button type="button" class="cta-secondary" data-remove-row>Remove</button></td>
                </tr>
            </template>
        </section>

        <section class="admin-card">
            <h3>Notes</h3>
            <label>
                Notes for the client
                <textarea name="notes" rows="3">{{ old('notes', $invoice->notes) }}</textarea>
            </label>
            <label>
                Internal notes (admin only)
                <textarea name="internal_notes" rows="3">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
            </label>
            <label>
                Terms
                <textarea name="terms" rows="3">{{ old('terms', $invoice->terms) }}</textarea>
            </label>
        </section>

        <div class="form-actions">
            <button class="cta" type="submit">{{ $invoice->exists ? 'Save Changes' : 'Create Invoice' }}</button>
            @if ($invoice->exists)
                <a class="cta-secondary" href="{{ route('admin.invoices.show', $invoice) }}">Cancel</a>
            @else
                <a class="cta-secondary" href="{{ route('admin.invoices.index') }}">Cancel</a>
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

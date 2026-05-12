@extends('layouts.admin')

@section('title', $contract->exists ? 'Edit Contract' : 'New Contract')
@section('eyebrow', 'Studio')
@section('heading', $contract->exists ? 'Edit '.$contract->number : 'New Contract')
@if ($contract->billable)
    @section('subheading', 'For '.$contract->billable->displayName())
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
        action="{{ $contract->exists ? route('admin.contracts.update', $contract) : route('admin.contracts.store') }}"
        class="admin-form"
    >
        @csrf
        @if ($contract->exists)
            @method('PUT')
        @endif

        <section class="admin-card">
            <h3>Counterparty &amp; references</h3>

            @php
                $currentBillableType = old('billable_type', $billableType ?? 'client');
                $currentBillableId = (int) old('billable_id', $contract->billable_id);
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
                </label>

                <label>
                    Booked job (optional)
                    <select name="booked_job_id">
                        <option value="">— None —</option>
                        @foreach ($bookedJobs as $job)
                            <option value="{{ $job->id }}" @selected(old('booked_job_id', $contract->booked_job_id) == $job->id)>
                                {{ $job->summary }} — {{ $job->event_date?->format('M j, Y') }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Linked invoice (optional)
                    <select name="invoice_id">
                        <option value="">— None —</option>
                        @foreach ($invoices as $option)
                            <option value="{{ $option->id }}" @selected(old('invoice_id', $contract->invoice_id) == $option->id)>
                                {{ $option->number }} — ${{ number_format($option->total_cents / 100, 2) }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="field-grid">
                <label>
                    Issue date
                    <input type="date" name="issue_date" value="{{ old('issue_date', optional($contract->issue_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
                </label>

                <label>
                    Expires (optional)
                    <input type="date" name="expires_at" value="{{ old('expires_at', optional($contract->expires_at)->format('Y-m-d')) }}">
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
            <h3>Contract content</h3>

            <div class="field-grid">
                <label>
                    Title
                    <input type="text" name="title" maxlength="255" required value="{{ old('title', $contract->title) }}">
                </label>

                <label>
                    Apply template
                    <select id="contract-template-picker">
                        <option value="">— Choose a template —</option>
                        @foreach ($templates as $option)
                            <option value="{{ $option->id }}" @selected(old('contract_template_id', $contract->contract_template_id) == $option->id)>
                                {{ $option->name }}{{ $option->is_default ? ' (default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="meta">Inserts the template body and replaces variables like
                        <code>&#123;&#123;client_name&#125;&#125;</code>. You can edit freely after applying.</span>
                </label>
            </div>

            <input type="hidden" name="contract_template_id" id="contract-template-id" value="{{ old('contract_template_id', $contract->contract_template_id) }}">

            <label>
                Body
                <textarea name="body" id="contract-body" rows="18" required>{{ old('body', $contract->body) }}</textarea>
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
            <h3>Internal notes</h3>
            <label>
                Notes for the studio (not shown to the client)
                <textarea name="internal_notes" rows="3">{{ old('internal_notes', $contract->internal_notes) }}</textarea>
            </label>
        </section>

        <div class="form-actions">
            <button class="cta" type="submit">{{ $contract->exists ? 'Save Changes' : 'Create Contract' }}</button>
            @if ($contract->exists)
                <a class="cta-secondary" href="{{ route('admin.contracts.show', $contract) }}">Cancel</a>
            @else
                <a class="cta-secondary" href="{{ route('admin.contracts.index') }}">Cancel</a>
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

        picker.addEventListener('change', function () {
            var id = picker.value;
            hidden.value = id || '';
            if (!id) { return; }

            if (body.value.trim() && !confirm('Replace the current contract body with this template?')) {
                picker.value = hidden.value;
                return;
            }

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

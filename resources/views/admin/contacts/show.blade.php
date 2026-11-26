@extends('layouts.admin')

@section('title', $contact->name ?: $contact->email)
@section('eyebrow', 'Contacts')
@section('heading', $contact->name ?: $contact->email)
@section('content')
    @if (session('status'))
        <div class="admin-flash" style="margin-bottom:1rem;">{{ session('status') }}</div>
    @endif

    <section class="admin-card">
        <h2>Details</h2>
        <dl>
            <dt>Email</dt><dd>{{ $contact->email }}</dd>
            @if ($contact->partner_name)<dt>Partner</dt><dd>{{ $contact->partner_name }}</dd>@endif
            @if ($contact->phone)<dt>Phone</dt><dd>{{ $contact->phone }}</dd>@endif
            <dt>Type</dt><dd>{{ \App\Models\Contact::TYPES[$contact->type] ?? $contact->type }}</dd>
            <dt>Stage</dt><dd>{{ \App\Models\Contact::STAGES[$contact->stage] ?? $contact->stage }}</dd>
            <dt>Source</dt><dd>{{ $contact->source }}@if ($contact->source_detail) <span class="meta">({{ $contact->source_detail }})</span>@endif</dd>
            @if ($contact->shoot_type)<dt>Shoot</dt><dd>{{ $contact->shoot_type }}</dd>@endif
            @if ($contact->event_date)<dt>Event date</dt><dd>{{ $contact->event_date->format('M j, Y') }}</dd>@endif
            <dt>First contact</dt><dd>{{ optional($contact->first_contact_at)->format('M j, Y') ?: '—' }}</dd>
            <dt>Last contact</dt><dd>{{ optional($contact->last_contact_at)->format('M j, Y') ?: '—' }}</dd>
        </dl>
        @if ($contact->notes)
            <p>{{ $contact->notes }}</p>
        @endif
        @if ($contact->client)
            <p><a href="{{ route('admin.clients.show', $contact->client) }}">Open client record</a></p>
        @endif
    </section>

    <section class="admin-card">
        <h2>Do not contact</h2>
        @if ($suppression)
            <p>
                On the list since {{ $suppression->suppressed_at->format('M j, Y') }}
                ({{ $suppression->source }}){{ $suppression->reason ? ': '.$suppression->reason : '' }}.
                Outreach and marketing email is blocked. Invoices and contracts still go to active clients.
            </p>
            <form method="POST" action="{{ route('admin.contacts.unsuppress', $contact) }}">
                @csrf
                <button class="cta-secondary" type="submit" style="cursor: pointer;">Remove from list</button>
            </form>
        @else
            <p>Not on the list.</p>
            <form method="POST" action="{{ route('admin.contacts.suppress', $contact) }}">
                @csrf
                <label>
                    Reason (optional)
                    <input type="text" name="reason" maxlength="255">
                </label>
                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Mark do not contact</button>
            </form>
        @endif
    </section>

    <section class="admin-card">
        <h2>Inquiries</h2>
        @forelse ($contact->inquiries as $inquiry)
            <p><a href="{{ route('admin.inquiries.edit', $inquiry) }}">{{ $inquiry->primary_name }}</a>
                <span class="meta">{{ $inquiry->status }} · {{ $inquiry->created_at->format('M j, Y') }}</span></p>
        @empty
            <p class="meta">None.</p>
        @endforelse
    </section>

    <section class="admin-card">
        <h2>Quiz</h2>
        @forelse ($contact->quizLeads as $lead)
            <p>{{ ucfirst($lead->result) }} ({{ $lead->score }}) <span class="meta">{{ $lead->created_at->format('M j, Y') }}{{ $lead->unsubscribed_at ? ' · unsubscribed' : '' }}</span></p>
        @empty
            <p class="meta">None.</p>
        @endforelse
    </section>
@endsection

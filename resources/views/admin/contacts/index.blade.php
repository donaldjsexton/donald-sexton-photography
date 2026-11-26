@extends('layouts.admin')

@section('title', 'Contacts')
@section('eyebrow', 'Studio')
@section('heading', 'Contacts')
@section('subheading', 'Everyone we have an email for, in one list. '.$totals['contacts'].' contacts, '.$totals['suppressed'].' on the do-not-contact list.')
@section('content')
    <section class="admin-card">
        <form method="GET" action="{{ route('admin.contacts.index') }}" class="admin-search-form">
            <label>
                Search
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Name or email">
            </label>
            <label>
                Type
                <select name="type">
                    <option value="">All</option>
                    @foreach (\App\Models\Contact::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Stage
                <select name="stage">
                    <option value="">All</option>
                    @foreach (\App\Models\Contact::STAGES as $key => $label)
                        <option value="{{ $key }}" @selected($filters['stage'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Source
                <select name="source">
                    <option value="">All</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source }}" @selected($filters['source'] === $source)>{{ $source }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Do not contact
                <select name="dnc">
                    <option value="">Any</option>
                    <option value="1" @selected($filters['dnc'] === '1')>Only DNC</option>
                    <option value="0" @selected($filters['dnc'] === '0')>Not DNC</option>
                </select>
            </label>

            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Apply</button>
            <a class="cta-secondary" href="{{ route('admin.contacts.index') }}">Clear</a>
        </form>
    </section>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Contact</th>
                    <th>Type</th>
                    <th>Stage</th>
                    <th>Source</th>
                    <th>Last contact</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contacts as $contact)
                    <tr>
                        <td>
                            <div class="admin-table__lead">
                                <strong>{{ $contact->name ?: $contact->email }}</strong>
                                @if ($contact->name)
                                    <span class="meta">{{ $contact->email }}</span>
                                @endif
                                @if ($suppressed->has($contact->email))
                                    <span class="meta">Do not contact</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ \App\Models\Contact::TYPES[$contact->type] ?? $contact->type }}</td>
                        <td>{{ \App\Models\Contact::STAGES[$contact->stage] ?? $contact->stage }}</td>
                        <td>{{ $contact->source }}</td>
                        <td>{{ optional($contact->last_contact_at)->format('M j, Y') ?: '—' }}</td>
                        <td><a href="{{ route('admin.contacts.show', $contact) }}">View</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No contacts match.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($contacts->hasPages())
        <div class="pagination">
            {{ $contacts->links() }}
        </div>
    @endif
@endsection

@extends('layouts.admin')

@section('title', 'Clients')
@section('eyebrow', 'Studio')
@section('heading', 'Clients')
@section('subheading', 'People and couples you invoice. Convert qualified inquiries into clients here.')
@section('header_actions')
    <a class="cta" href="{{ route('admin.clients.create') }}">New Client</a>
@endsection
@section('content')
    <section class="admin-card">
        <form method="GET" action="{{ route('admin.clients.index') }}" class="admin-search-form">
            <label>
                Search clients
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Name, email, or company"
                >
            </label>

            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Apply</button>

            @if ($search !== '')
                <a class="cta-secondary" href="{{ route('admin.clients.index') }}">Clear</a>
            @endif
        </form>
    </section>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Location</th>
                    <th>Invoices</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clients as $client)
                    <tr>
                        <td>
                            <div class="admin-table__lead">
                                <strong>{{ $client->displayName() }}</strong>
                                @if ($client->company)
                                    <span class="meta">{{ $client->company }}</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $client->email }}</td>
                        <td>{{ $client->phone ?: '—' }}</td>
                        <td>{{ collect([$client->city, $client->state])->filter()->implode(', ') ?: '—' }}</td>
                        <td>{{ $client->invoices_count }}</td>
                        <td>
                            <a href="{{ route('admin.clients.show', $client) }}">View</a>
                            <span class="meta"> · </span>
                            <a href="{{ route('admin.clients.edit', $client) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No clients yet. Create one or convert an inquiry.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($clients->hasPages())
        <div class="pagination">
            {{ $clients->links() }}
        </div>
    @endif
@endsection

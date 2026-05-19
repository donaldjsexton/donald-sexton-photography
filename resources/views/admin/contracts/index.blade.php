@extends('layouts.admin')

@section('title', 'Contracts')
@section('eyebrow', 'Studio')
@section('heading', 'Contracts')
@section('subheading', 'Drafts, sent, and signed. Filter by status to see what needs action.')
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.contract-templates.index') }}">Templates</a>
    <a class="cta" href="{{ route('admin.contracts.create') }}">New Contract</a>
@endsection
@section('content')
    <section class="admin-card">
        <nav class="admin-tabs">
            <a class="{{ $currentStatus === 'all' ? 'is-active' : '' }}" href="{{ route('admin.contracts.index') }}">All</a>
            @foreach ($statusOptions as $key => $label)
                <a class="{{ $currentStatus === $key ? 'is-active' : '' }}" href="{{ route('admin.contracts.index', ['status' => $key]) }}">{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <div class="admin-table-wrap">
        <table class="admin-table admin-table--cards admin-table--contracts">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Counterparty</th>
                    <th>Title</th>
                    <th>Issued</th>
                    <th>Status</th>
                    <th>Signed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contracts as $contract)
                    <tr>
                        <td class="contracts-col--number" data-label="Number"><strong>{{ $contract->number }}</strong></td>
                        <td class="contracts-col--counterparty" data-label="Counterparty">
                            @if ($contract->billable instanceof \App\Models\Client)
                                <a href="{{ route('admin.clients.show', $contract->billable) }}">{{ $contract->billableName() }}</a>
                            @elseif ($contract->billable instanceof \App\Models\Venue)
                                <a href="{{ route('admin.venues.edit', $contract->billable) }}">{{ $contract->billableName() }}</a>
                                <span class="meta"> · vendor</span>
                            @else
                                <span class="meta">—</span>
                            @endif
                        </td>
                        <td class="contracts-col--title" data-label="Title">{{ $contract->title }}</td>
                        <td class="contracts-col--issued" data-label="Issued">{{ $contract->issue_date?->format('M j, Y') ?: '—' }}</td>
                        <td class="contracts-col--status" data-label="Status">{{ $statusOptions[$contract->status] ?? $contract->status }}</td>
                        <td class="contracts-col--signed" data-label="Signed">{{ $contract->signed_at?->format('M j, Y') ?: '—' }}</td>
                        <td class="contracts-col--open"><a href="{{ route('admin.contracts.show', $contract) }}">View</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No contracts match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($contracts->hasPages())
        <div class="pagination">
            {{ $contracts->links() }}
        </div>
    @endif
@endsection

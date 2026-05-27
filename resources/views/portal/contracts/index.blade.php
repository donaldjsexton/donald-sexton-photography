@extends('portal.layouts.app')

@section('title', 'Contracts')

@section('content')
    <section class="card">
        <h2>Contracts</h2>

        @if ($contracts->isEmpty())
            <p class="meta" style="margin:0;">You don&rsquo;t have any contracts yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Title</th>
                        <th>Issued</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contracts as $contract)
                        <tr>
                            <td data-label="Number"><strong>{{ $contract->number }}</strong></td>
                            <td data-label="Title">{{ $contract->title }}</td>
                            <td data-label="Issued">{{ $contract->issue_date?->format('M j, Y') ?: '—' }}</td>
                            <td data-label="Status"><span class="pill">{{ \App\Models\Contract::statusOptions()[$contract->status] ?? $contract->status }}</span></td>
                            <td><a href="{{ route('portal.contracts.show', ['contract' => $contract->uuid]) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($contracts->hasPages())
                <div style="margin-top:24px;">
                    {{ $contracts->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection

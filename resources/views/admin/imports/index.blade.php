@extends('layouts.admin')

@section('title', 'Import Activity')
@section('eyebrow', 'Operations')
@section('heading', 'Import Activity')
@section('subheading', 'Review import summaries and error logs without forcing long payloads into the settings screen.')
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.settings.edit', ['tab' => 'imports']) }}#import-settings">Open Import Tools</a>
@endsection
@section('content')
    <section class="admin-dashboard-row">
        <x-admin.section-header
            eyebrow="Run History"
            title="WordPress and Pic-Time imports"
            description="A full-screen activity log for completed, running, and failed import jobs. Long summaries wrap here, and full error output stays collapsed until you open it."
        />

        <div class="admin-stat-grid">
            @foreach ($importStats as $stat)
                <x-admin.stat
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :meta="$stat['meta']"
                />
            @endforeach
        </div>
    </section>

    <section class="admin-card admin-import-activity-card">
        <p class="eyebrow">Recent Runs</p>

        <div class="admin-import-run-list">
            @forelse ($importRuns as $run)
                @php
                    $sourceLabel = match ($run->source_type) {
                        'wordpress' => 'WordPress',
                        'pictime' => 'Pic-Time',
                        default => ucfirst((string) $run->source_type),
                    };
                    $statusTone = match ($run->status) {
                        'completed' => 'completed',
                        'failed' => 'failed',
                        'running' => 'running',
                        default => 'archived',
                    };
                    $summary = collect($run->summary_json ?? []);
                    $startedAt = $run->started_at?->format('M j, Y g:i A') ?: $run->created_at?->format('M j, Y g:i A') ?: 'No start time recorded';
                    $finishedAt = $run->finished_at?->format('M j, Y g:i A');
                @endphp

                <article class="admin-import-run">
                    <div class="admin-import-run__header">
                        <div class="admin-import-run__copy">
                            <p class="eyebrow">{{ $sourceLabel }} Import</p>
                            <h3 class="admin-import-run__title">{{ $startedAt }}</h3>
                        </div>

                        <x-admin.badge :tone="$statusTone">{{ str($run->status)->headline() }}</x-admin.badge>
                    </div>

                    <div class="admin-import-run__meta">
                        <span><strong>Source:</strong> {{ $sourceLabel }}</span>
                        <span><strong>Started:</strong> {{ $startedAt }}</span>
                        <span><strong>Finished:</strong> {{ $finishedAt ?: 'Still running or not recorded' }}</span>
                    </div>

                    @if ($summary->isNotEmpty())
                        <div class="admin-import-run__summary">
                            @foreach ($summary as $key => $value)
                                <span>{{ str_replace('_', ' ', $key) }}: {{ is_scalar($value) ? $value : json_encode($value) }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($run->error_log)
                        <details class="admin-details admin-import-run__details">
                            <summary>View error log</summary>
                            <pre class="admin-pre admin-pre--wrap">{{ $run->error_log }}</pre>
                        </details>
                    @endif
                </article>
            @empty
                <p class="meta">No import runs have been recorded yet.</p>
            @endforelse
        </div>
    </section>

    <div class="pagination">
        {{ $importRuns->links() }}
    </div>
@endsection

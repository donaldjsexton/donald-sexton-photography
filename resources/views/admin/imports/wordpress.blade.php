@extends('layouts.admin')

@section('title', 'Legacy Blog Import')
@section('heading', 'Legacy Blog Import')
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <section class="admin-grid admin-grid--two">
        <article class="admin-card">
            <p class="eyebrow">Importer</p>
            <h3 class="feature-title">Upload a legacy blog XML export.</h3>
            <p class="section-copy">This import pass brings older blog posts into the journal, matches featured images from the export, records import mappings, and builds redirects from the original paths.</p>
            <p class="meta">For large exports, use <code>php artisan wordpress:import /absolute/path/to/export.xml</code> instead of the browser upload.</p>

            <form method="POST" action="{{ route('admin.imports.wordpress.store') }}" enctype="multipart/form-data" class="admin-form">
                @csrf
                <label>
                    WXR file
                    <input type="file" name="wxr_file" accept=".xml,text/xml,application/xml" required>
                </label>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Run Import</button>
            </form>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Scope</p>
            <div class="admin-list">
                <div class="admin-list__item"><strong>Included</strong><span class="meta">Posts, categories, tags, publish dates, featured image downloads, mappings, redirects, and wedding-story promotion.</span></div>
                <div class="admin-list__item"><strong>Still manual</strong><span class="meta">Large legacy media archives and page-only content that are not part of the post export.</span></div>
            </div>
        </article>
    </section>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Started</th>
                    <th>Status</th>
                    <th>Summary</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($importRuns as $run)
                    <tr>
                        <td>{{ $run->started_at?->format('M j, Y g:i A') ?: $run->created_at?->format('M j, Y g:i A') }}</td>
                        <td>{{ $run->status }}</td>
                        <td>
                            @if ($run->summary_json)
                                <div class="admin-summary">
                                    @foreach ($run->summary_json as $key => $value)
                                        <span>{{ str_replace('_', ' ', $key) }}: {{ $value }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="meta">No summary saved.</span>
                            @endif
                        </td>
                        <td>{{ $run->error_log ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No import runs recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($importRuns->hasPages())
        <div class="pagination">
            {{ $importRuns->links() }}
        </div>
    @endif
@endsection

@extends('layouts.admin')

@section('title', 'Pic-Time Import')
@section('heading', 'Pic-Time Import')
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
            <p class="eyebrow">Temporary Tool</p>
            <h3 class="feature-title">Import Pic-Time blog pages into the CMS.</h3>
            <p class="section-copy">Paste one Pic-Time post URL per line. The importer fetches the page, parses title, text, and gallery images, downloads the images into local media, and ingests the result into either wedding stories or journal posts.</p>
            <p class="meta">For legacy XML exports, saved HTML files, or directories of saved pages on the server, use <code>php artisan pictime:import /absolute/path/to/export.xml /absolute/path/to/saved-pages --target=auto</code>.</p>

            <form method="POST" action="{{ route('admin.imports.pictime.store') }}" class="admin-form">
                @csrf

                <label>
                    Pic-Time URLs
                    <textarea name="sources" rows="8" placeholder="https://gallery.example.com/blog/post-one&#10;https://gallery.example.com/blog/post-two" required>{{ old('sources') }}</textarea>
                </label>

                <label>
                    Target
                    <select name="target">
                        <option value="auto" @selected(old('target', 'auto') === 'auto')>Auto detect</option>
                        <option value="weddings" @selected(old('target') === 'weddings')>Wedding stories</option>
                        <option value="journal" @selected(old('target') === 'journal')>Journal posts</option>
                    </select>
                </label>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Run Import</button>
            </form>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Scope</p>
            <div class="admin-list">
                <div class="admin-list__item"><strong>Included</strong><span class="meta">Title parsing, text extraction, image download, local media creation, import mappings, and auto classification into wedding stories or journal posts.</span></div>
                <div class="admin-list__item"><strong>Temporary</strong><span class="meta">This importer is meant to help move Pic-Time blog content into the CMS, not replace the main editing workflow.</span></div>
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
                        <td colspan="4">No Pic-Time import runs recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $importRuns->links() }}
    </div>
@endsection

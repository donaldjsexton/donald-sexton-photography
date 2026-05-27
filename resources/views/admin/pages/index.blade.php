@extends('layouts.admin')

@section('title', 'Pages')
@section('heading', 'Pages')
@section('content')
    <div class="admin-toolbar">
        <p class="section-copy">Manage about, collections, locations, and supporting evergreen content.</p>
        <a class="cta" href="{{ route('admin.pages.create') }}">New Page</a>
    </div>

    @if (session('status'))
        <div class="admin-flash">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <section class="admin-card admin-card--feature" style="margin-top:1.5rem;">
        <p class="eyebrow">Location landing page</p>
        <h3 class="feature-title">Draft a "[City] Wedding Photographer" page</h3>
        <p class="section-copy">Scaffolds a draft location page with title, slug, excerpt, body, and SEO meta. The public page will auto-pull matching wedding stories, venues, and journal posts based on city and state.</p>

        <form method="POST" action="{{ route('admin.pages.generate-location') }}" class="admin-form">
            @csrf

            <div class="field-grid">
                <label>
                    City <span class="meta">*</span>
                    <input type="text" name="city" value="{{ old('city') }}" required placeholder="Tampa">
                </label>
                <label>
                    State
                    <input type="text" name="state" value="{{ old('state', 'FL') }}" placeholder="FL">
                </label>
                <label>
                    Region
                    <input type="text" name="region" value="{{ old('region') }}" placeholder="Tampa Bay">
                </label>
            </div>

            <button class="cta-secondary" type="submit" style="border: 0; cursor: pointer;">Generate Draft</button>
        </form>
    </section>

    <div class="admin-table-wrap" style="margin-top:1.5rem;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Template</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr>
                        <td>{{ $page->title }}</td>
                        <td>{{ $page->slug }}</td>
                        <td>{{ $page->template }}</td>
                        <td>{{ $page->status }}</td>
                        <td>{{ $page->updated_at?->format('M j, Y') }}</td>
                        <td><a href="{{ route('admin.pages.edit', $page) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($pages->hasPages())
        <div class="pagination">
            {{ $pages->links() }}
        </div>
    @endif
@endsection

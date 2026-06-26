@extends('layouts.admin')

@section('title', 'Galleries')
@section('eyebrow', 'Content')
@section('heading', 'Client Galleries')
@section('subheading', 'Deliver proofing and final galleries to clients with shareable, optionally password-protected links.')
@section('header_actions')
    <a class="cta" href="{{ route('admin.galleries.create') }}">New Gallery</a>
@endsection
@section('content')
    <section class="admin-card">
        <form method="GET" action="{{ route('admin.galleries.index') }}" class="admin-search-form">
            <label>
                Search galleries
                <input type="search" name="search" value="{{ $search }}" placeholder="Title or slug">
            </label>
            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Apply</button>
            @if ($search !== '')
                <a class="cta-secondary" href="{{ route('admin.galleries.index') }}">Clear</a>
            @endif
        </form>
    </section>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Visibility</th>
                    <th>Albums</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($galleries as $gallery)
                    <tr>
                        <td>
                            <div class="admin-table__lead">
                                <strong>{{ $gallery->title }}</strong>
                                <span class="meta">{{ $gallery->slug }}</span>
                            </div>
                        </td>
                        <td>{{ ucfirst($gallery->visibility) }}{{ $gallery->password ? ' · protected' : '' }}</td>
                        <td>{{ $gallery->albums_count }}</td>
                        <td><span class="meta">{{ $gallery->created_at?->format('M j, Y') }}</span></td>
                        <td>
                            <div class="admin-row-actions">
                                <a class="cta-secondary" href="{{ route('admin.galleries.edit', $gallery) }}">Manage</a>
                                <form method="POST" action="{{ route('admin.galleries.destroy', $gallery) }}"
                                      onsubmit="return confirm('Delete this gallery and its photos?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="cta-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><span class="meta">No galleries yet.</span></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 16px;">{{ $galleries->links() }}</div>
@endsection

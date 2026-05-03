@extends('layouts.admin')

@section('title', 'Wedding Stories')
@section('heading', 'Wedding Stories')
@section('content')
    <div class="admin-toolbar">
        <p class="section-copy">Manage featured portfolio stories, story summaries, venues, and publication state.</p>
        <a class="cta" href="{{ route('admin.wedding-stories.create') }}">New Story</a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Venue</th>
                    <th>Published</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stories as $story)
                    <tr>
                        <td>{{ $story->title }}</td>
                        <td>{{ $story->story_type }}</td>
                        <td>{{ $story->status }}</td>
                        <td>{{ $story->venue?->name ?: '—' }}</td>
                        <td>{{ $story->published_at?->format('M j, Y') ?: '—' }}</td>
                        <td><a href="{{ route('admin.wedding-stories.edit', $story) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($stories->hasPages())
        <div class="pagination">
            {{ $stories->links() }}
        </div>
    @endif
@endsection


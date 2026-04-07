@extends('layouts.admin')

@section('title', 'Journal Posts')
@section('heading', 'Journal Posts')
@section('content')
    <div class="admin-toolbar">
        <p class="section-copy">Manage the journal archive directly and review imported legacy content before publishing.</p>
        <a class="cta" href="{{ route('admin.journal-posts.create') }}">New Journal Post</a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th>Legacy ID</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posts as $post)
                    <tr>
                        <td>{{ $post->title }}</td>
                        <td>{{ $post->post_type }}</td>
                        <td>{{ $post->status }}</td>
                        <td>{{ $post->published_at?->format('M j, Y') ?: '—' }}</td>
                        <td>{{ $post->original_wp_post_id ?: '—' }}</td>
                        <td><a href="{{ route('admin.journal-posts.edit', $post) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $posts->links() }}
    </div>
@endsection

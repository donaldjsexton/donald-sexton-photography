@extends('layouts.admin')

@section('title', 'Pages')
@section('heading', 'Pages')
@section('content')
    <div class="admin-toolbar">
        <p class="section-copy">Manage about, collections, locations, and supporting evergreen content.</p>
        <a class="cta" href="{{ route('admin.pages.create') }}">New Page</a>
    </div>

    <div class="admin-table-wrap">
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

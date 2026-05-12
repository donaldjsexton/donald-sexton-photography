@extends('layouts.admin')

@section('title', 'Contract Templates')
@section('eyebrow', 'Studio')
@section('heading', 'Contract Templates')
@section('subheading', 'Reusable contract bodies. Use merge variables to auto-fill names, dates, and totals.')
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.contracts.index') }}">Back to Contracts</a>
    <a class="cta" href="{{ route('admin.contract-templates.create') }}">New Template</a>
@endsection
@section('content')
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Title</th>
                    <th>Default?</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td><strong>{{ $template->name }}</strong></td>
                        <td>{{ $template->title }}</td>
                        <td>{{ $template->is_default ? 'Yes' : '—' }}</td>
                        <td>
                            <a href="{{ route('admin.contract-templates.edit', $template) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No templates yet. Create one to speed up new contracts.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

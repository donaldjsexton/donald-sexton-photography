@extends('layouts.admin')

@section('title', 'New Gallery')
@section('eyebrow', 'Content')
@section('heading', 'New Gallery')
@section('subheading', 'Name the gallery, then add albums and upload photos.')
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.galleries.store') }}" class="admin-form">
        @csrf

        <div class="field-grid">
            <label>
                Title
                <input type="text" name="title" value="{{ old('title') }}" required autofocus>
            </label>

            <label>
                Visibility
                <select name="visibility">
                    <option value="private" @selected(old('visibility', 'private') === 'private')>Private (link only)</option>
                    <option value="public" @selected(old('visibility') === 'public')>Public</option>
                </select>
            </label>

            <label>
                Password <span class="meta">(optional)</span>
                <input type="text" name="password" value="{{ old('password') }}" autocomplete="off"
                       placeholder="Leave blank for no password">
            </label>
        </div>

        <div class="form-actions">
            <button class="cta" type="submit">Create gallery</button>
            <a class="cta-secondary" href="{{ route('admin.galleries.index') }}">Cancel</a>
        </div>
    </form>
@endsection

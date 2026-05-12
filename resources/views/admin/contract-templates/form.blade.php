@extends('layouts.admin')

@section('title', $template->exists ? 'Edit Template' : 'New Contract Template')
@section('eyebrow', 'Studio')
@section('heading', $template->exists ? 'Edit '.$template->name : 'New Contract Template')
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form
        method="POST"
        action="{{ $template->exists ? route('admin.contract-templates.update', $template) : route('admin.contract-templates.store') }}"
        class="admin-form"
    >
        @csrf
        @if ($template->exists)
            @method('PUT')
        @endif

        <section class="admin-card">
            <div class="field-grid">
                <label>
                    Internal name
                    <input type="text" name="name" maxlength="255" required value="{{ old('name', $template->name) }}">
                </label>

                <label>
                    Contract title (shown on the document)
                    <input type="text" name="title" maxlength="255" required value="{{ old('title', $template->title) }}">
                </label>

                <label style="grid-column: 1 / -1;">
                    Description (internal only)
                    <input type="text" name="description" maxlength="1000" value="{{ old('description', $template->description) }}">
                </label>
            </div>

            <label style="display:inline-flex; align-items:center; gap:6px; margin-top:8px;">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default))>
                Use as the default template for new contracts
            </label>
        </section>

        <section class="admin-card">
            <h3>Body</h3>
            <p class="meta" style="margin:0 0 12px;">
                Use <code>&#123;&#123;variable&#125;&#125;</code> tokens. They get replaced automatically when a contract is created from this template.
            </p>

            <label>
                <span class="meta">Contract body</span>
                <textarea name="body" rows="22" required>{{ old('body', $template->body) }}</textarea>
            </label>

            <details style="margin-top:8px;">
                <summary class="meta" style="cursor:pointer;">Available merge variables</summary>
                <ul class="meta" style="margin-top:8px;">
                    @foreach ($availableVariables as $key => $description)
                        <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> — {{ $description }}</li>
                    @endforeach
                </ul>
            </details>
        </section>

        <div class="form-actions">
            <button class="cta" type="submit">{{ $template->exists ? 'Save Changes' : 'Create Template' }}</button>
            <a class="cta-secondary" href="{{ route('admin.contract-templates.index') }}">Cancel</a>

            @if ($template->exists)
                <form method="POST" action="{{ route('admin.contract-templates.destroy', $template) }}" onsubmit="return confirm('Delete this template? Contracts already created from it are unaffected.');" style="margin-left:auto;">
                    @csrf
                    @method('DELETE')
                    <button class="cta-secondary" type="submit" style="color:#a03030;">Delete</button>
                </form>
            @endif
        </div>
    </form>
@endsection

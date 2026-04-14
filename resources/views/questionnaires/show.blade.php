@extends('layouts.app')

@section('title', 'Wedding Questionnaire')

@section('content')
    <x-editorial.page-hero
        eyebrow="Wedding Questionnaire"
        title="Tell me about your day."
        copy="A few details to help me show up ready and attentive. Take your time — nothing needs to be perfect."
        shell="tight"
    />

    <section class="section">
        <div class="page-shell--wide page-form-layout">
            <div class="page-form-aside">
                <p class="eyebrow">Before We Begin</p>
                <p class="section-copy">Fill in what you know today. Skip anything that doesn't apply.</p>
                <p class="meta">When you're ready, send it — I'll review before our next call.</p>
            </div>

            <div class="form-panel">
                @if ($questionnaire->isSubmitted())
                    <p class="meta">This questionnaire was submitted on {{ $questionnaire->submitted_at->format('F j, Y') }}. Reach out if you need to make changes.</p>
                @else
                    <form method="POST" action="{{ route('questionnaire.update', $questionnaire) }}">
                        @csrf
                        @method('PUT')

                        @foreach ($schema as $section)
                            <fieldset style="border:1px solid #efe3d7; padding:1.25rem 1.5rem 1.5rem; margin-bottom:2rem;">
                                <legend class="eyebrow" style="padding:0 .5rem;">{{ $section['title'] }}</legend>

                                @foreach ($section['fields'] as $field)
                                    @php
                                        $stored = $questionnaire->response($field['key']);
                                        $value = old($field['key'], $stored);
                                    @endphp

                                    @if ($field['type'] === 'textarea')
                                        <label style="display:block; margin-bottom:1rem;">
                                            {{ $field['label'] }}
                                            <textarea name="{{ $field['key'] }}" rows="3">{{ $value }}</textarea>
                                        </label>
                                    @elseif ($field['type'] === 'radio')
                                        <div style="margin-bottom:1rem;">
                                            <p style="margin:0 0 .35rem; font-weight:600;">{{ $field['label'] }}</p>
                                            <div style="display:flex; flex-wrap:wrap; gap:1rem;">
                                                @foreach ($field['options'] as $option)
                                                    <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                                        <input type="radio" name="{{ $field['key'] }}" value="{{ $option }}" @checked((string) $value === (string) $option)>
                                                        {{ $option }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif ($field['type'] === 'checkboxes')
                                        @php $values = is_array($value) ? $value : []; @endphp
                                        <div style="margin-bottom:1rem;">
                                            <p style="margin:0 0 .35rem; font-weight:600;">{{ $field['label'] }}</p>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:.5rem;">
                                                @foreach ($field['options'] as $option)
                                                    <label style="display:inline-flex; align-items:center; gap:.4rem; font-weight:400;">
                                                        <input type="checkbox" name="{{ $field['key'] }}[]" value="{{ $option }}" @checked(in_array($option, $values, true))>
                                                        {{ $option }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <label style="display:block; margin-bottom:1rem;">
                                            {{ $field['label'] }}
                                            <input type="{{ $field['type'] }}" name="{{ $field['key'] }}" value="{{ $value }}">
                                        </label>
                                    @endif
                                @endforeach
                            </fieldset>
                        @endforeach

                        <button class="cta" type="submit" style="border:0; cursor:pointer;">Submit Questionnaire</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection

@extends('layouts.app')

@section('title', 'Wedding Questionnaire')

@section('content')
    <style>
        .q-section { border:1px solid #efe3d7; padding:1.25rem 1.5rem 1.5rem; margin-bottom:2rem; }
        .q-section legend { padding:0 .5rem; }
        .q-field { margin-bottom:1.25rem; }
        .q-field > label { display:block; }
        .q-label { display:block; margin:0 0 .4rem; font-weight:600; }
        .q-options { display:flex; flex-direction:column; gap:.5rem; align-items:flex-start; }
        .q-options--grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(min(100%, 220px), 1fr)); gap:.5rem .75rem; }
        .q-options--grid > .q-option { min-width:0; }
        .q-option { display:flex; align-items:flex-start; gap:.5rem; margin:0; font-weight:400; line-height:1.35; }
        .q-option input { margin:.2rem 0 0; flex:none; }
    </style>

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
                            <fieldset class="q-section">
                                <legend class="eyebrow">{{ $section['title'] }}</legend>

                                @foreach ($section['fields'] as $field)
                                    @php
                                        $stored = $questionnaire->response($field['key']);
                                        $value = old($field['key'], $stored);
                                    @endphp

                                    @if ($field['type'] === 'textarea')
                                        <div class="q-field">
                                            <label>
                                                {{ $field['label'] }}
                                                <textarea name="{{ $field['key'] }}" rows="3">{{ $value }}</textarea>
                                            </label>
                                        </div>
                                    @elseif ($field['type'] === 'radio')
                                        <div class="q-field">
                                            <span class="q-label">{{ $field['label'] }}</span>
                                            <div class="q-options">
                                                @foreach ($field['options'] as $option)
                                                    <label class="q-option">
                                                        <input type="radio" name="{{ $field['key'] }}" value="{{ $option }}" @checked((string) $value === (string) $option)>
                                                        <span>{{ $option }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif ($field['type'] === 'checkboxes')
                                        @php $values = is_array($value) ? $value : []; @endphp
                                        <div class="q-field">
                                            <span class="q-label">{{ $field['label'] }}</span>
                                            <div class="q-options q-options--grid">
                                                @foreach ($field['options'] as $option)
                                                    <label class="q-option">
                                                        <input type="checkbox" name="{{ $field['key'] }}[]" value="{{ $option }}" @checked(in_array($option, $values, true))>
                                                        <span>{{ $option }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="q-field">
                                            <label>
                                                {{ $field['label'] }}
                                                <input type="{{ $field['type'] }}" name="{{ $field['key'] }}" value="{{ $value }}">
                                            </label>
                                        </div>
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

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
                    <div class="q-submitted">
                        <p class="meta">This questionnaire was submitted on {{ $questionnaire->submitted_at->format('F j, Y') }}. Reach out if you need to make changes.</p>
                    </div>
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
                                                <span class="q-label">{{ $field['label'] }}</span>
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
                                            <div class="q-options">
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
                                                <span class="q-label">{{ $field['label'] }}</span>
                                                <input type="{{ $field['type'] }}" name="{{ $field['key'] }}" value="{{ $value }}">
                                            </label>
                                        </div>
                                    @endif
                                @endforeach
                            </fieldset>
                        @endforeach

                        <button class="cta q-submit" type="submit">Submit Questionnaire</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection

@extends('portal.layouts.app')

@section('title', 'Wedding Questionnaire')

@section('content')
    <section class="card">
        <h2>Wedding Questionnaire</h2>

        @if ($questionnaire->isSubmitted())
            <p class="meta">Submitted on {{ $questionnaire->submitted_at->format('F j, Y') }}. Reach out if you need to make changes.</p>

            @include('questionnaires._responses', ['questionnaire' => $questionnaire, 'schema' => $schema])
        @else
            <p class="meta">Fill in what you know today — skip anything that doesn&rsquo;t apply. You can save it once and we&rsquo;ll review before your next call.</p>

            <form method="POST" action="{{ route('portal.questionnaires.update', ['questionnaire' => $questionnaire->token]) }}">
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
    </section>
@endsection

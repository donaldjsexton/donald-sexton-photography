@php
    /** @var \App\Models\WeddingQuestionnaire $questionnaire */
    /** @var array $schema */
@endphp
<div class="q-view">
    @foreach ($schema as $section)
        @php
            $sectionHasAny = false;
            foreach ($section['fields'] as $f) {
                $v = $questionnaire->response($f['key']);
                if ($v !== null && $v !== '' && $v !== []) { $sectionHasAny = true; break; }
            }
        @endphp
        @if ($sectionHasAny)
            <section class="q-view__section">
                <h2 class="q-view__section-title">{{ $section['title'] }}</h2>
                <dl class="q-view__list">
                    @foreach ($section['fields'] as $field)
                        @php $value = $questionnaire->response($field['key']); @endphp
                        @if ($value !== null && $value !== '' && $value !== [])
                            <div class="q-view__row">
                                <dt>{{ $field['label'] }}</dt>
                                <dd>{!! is_array($value) ? e(implode(', ', $value)) : nl2br(e($value)) !!}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </section>
        @endif
    @endforeach
</div>

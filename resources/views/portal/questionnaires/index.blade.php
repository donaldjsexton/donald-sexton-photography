@extends('portal.layouts.app')

@section('title', 'Questionnaires')

@section('content')
    <section class="card">
        <h2>Questionnaires</h2>

        @if ($questionnaires->isEmpty())
            <p class="meta" style="margin:0;">You don&rsquo;t have any questionnaires yet. We&rsquo;ll send one your way when it&rsquo;s time to plan the details.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Questionnaire</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($questionnaires as $questionnaire)
                        <tr>
                            <td data-label="Questionnaire"><strong>Wedding Questionnaire</strong></td>
                            <td data-label="Event">{{ $questionnaire->inquiry?->event_date?->format('M j, Y') ?: '—' }}</td>
                            <td data-label="Status">
                                @if ($questionnaire->isSubmitted())
                                    <span class="pill">Submitted {{ $questionnaire->submitted_at->format('M j, Y') }}</span>
                                @else
                                    <span class="pill">Awaiting your answers</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('portal.questionnaires.show', ['questionnaire' => $questionnaire->token]) }}">
                                    {{ $questionnaire->isSubmitted() ? 'View' : 'Complete' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection

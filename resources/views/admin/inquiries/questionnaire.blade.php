@extends('layouts.admin')

@section('title', 'Wedding Questionnaire')
@section('eyebrow', 'Leads')
@section('heading', ($questionnaire->response('bride_name') ?: $inquiry->primary_name).' — Questionnaire')
@section('subheading', $questionnaire->isSubmitted() ? 'Submitted '.$questionnaire->submitted_at->format('F j, Y g:i A') : 'Awaiting submission.')
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.inquiries.edit', $inquiry) }}">Back to Lead</a>
    @if ($questionnaire->isSubmitted())
        <button type="button" class="cta" onclick="window.print()" style="border:0; cursor:pointer; margin-left:.5rem;">Print / Save PDF</button>
    @endif
@endsection
@section('content')
    <style>
        .q-view__section { margin-bottom: 2rem; }
        .q-view__section-title { font-size: 12px; letter-spacing: .1em; text-transform: uppercase; color: #8a7a6a; margin: 0 0 .75rem; border-bottom: 1px solid #efe3d7; padding-bottom: .4rem; }
        .q-view__list { margin: 0; }
        .q-view__row { display: grid; grid-template-columns: minmax(180px, 35%) 1fr; gap: 1rem; padding: .5rem 0; border-bottom: 1px solid #f6eee5; }
        .q-view__row dt { margin: 0; color: #6b6157; font-weight: 600; }
        .q-view__row dd { margin: 0; color: #2a2320; }
        @media print {
            .admin-header, .admin-nav, .admin-sidebar, .cta, .cta-secondary, .header_actions { display: none !important; }
            body { background: #fff !important; }
            .admin-card { border: 0 !important; padding: 0 !important; }
        }
    </style>

    <article class="admin-card">
        @if ($questionnaire->isSubmitted())
            @include('questionnaires._responses', ['questionnaire' => $questionnaire, 'schema' => $schema])
        @else
            <p class="meta">Questionnaire has not been submitted yet. Shareable link:</p>
            <p class="meta" style="word-break:break-all;"><a href="{{ $questionnaire->publicUrl() }}" target="_blank" rel="noopener">{{ $questionnaire->publicUrl() }}</a></p>
        @endif
    </article>
@endsection

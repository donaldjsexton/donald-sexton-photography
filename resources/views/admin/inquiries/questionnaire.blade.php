@extends('layouts.admin-v2')

@section('title', 'Wedding Questionnaire')
@section('eyebrow', 'Leads')
@section('heading', ($questionnaire->response('bride_name') ?: $inquiry?->primary_name ?: $questionnaire->client?->displayName() ?: 'Client').' — Questionnaire')
@section('subheading', $questionnaire->isSubmitted() ? 'Submitted '.$questionnaire->submitted_at->format('F j, Y g:i A') : 'Awaiting submission.')
@section('header_actions')
    @if ($inquiry)
        <x-ui.button variant="secondary" :href="route('admin.inquiries.edit', $inquiry)">Back to Lead</x-ui.button>
    @elseif ($questionnaire->client)
        <x-ui.button variant="secondary" :href="route('admin.clients.show', $questionnaire->client)">Back to Client</x-ui.button>
    @endif
    @if ($questionnaire->isSubmitted())
        <x-ui.button type="button" onclick="window.print()" class="ml-2">Print / Save PDF</x-ui.button>
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
            aside, header, [data-print-hide], a[class*="rounded-lg"], button { display: none !important; }
            body { background: #fff !important; }
            .q-card { border: 0 !important; box-shadow: none !important; padding: 0 !important; }
            .lg\:pl-72, .lg\:pl-20 { padding-left: 0 !important; }
        }
    </style>

    <x-ui.card class="q-card">
        @if ($questionnaire->isSubmitted())
            @include('questionnaires._responses', ['questionnaire' => $questionnaire, 'schema' => $schema])
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Questionnaire has not been submitted yet. Shareable link:</p>
            <p class="break-all text-sm text-gray-500 dark:text-gray-400"><a class="text-brand-600 underline dark:text-brand-400" href="{{ $questionnaire->publicUrl() }}" target="_blank" rel="noopener">{{ $questionnaire->publicUrl() }}</a></p>
        @endif
    </x-ui.card>
@endsection

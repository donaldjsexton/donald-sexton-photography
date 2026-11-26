@extends('layouts.admin-v2')

@php
    use App\Models\Invoice;

    $totalBilledCents = $client->invoices->sum('total_cents');
    $totalPaidCents = $client->invoices->sum('amount_paid_cents');
    $totalOutstandingCents = $client->invoices->sum(fn (Invoice $i) => $i->amountDueCents());

    $money = fn (int $cents) => '$'.number_format($cents / 100, 0);

    $initials = strtoupper(mb_substr($client->first_name ?: '?', 0, 1).mb_substr($client->last_name ?: ($client->partner_first_name ?: ''), 0, 1));

    $sinceDate = $client->inquiries->min('created_at') ?? $client->created_at;

    $bookings = $client->inquiries
        ->filter(fn ($inquiry) => $inquiry->bookedJob !== null)
        ->sortByDesc(fn ($inquiry) => $inquiry->bookedJob->event_date ?? $inquiry->created_at);

    $location = trim(($client->city ?: '').($client->state ? ', '.$client->state : ''), ', ');
@endphp

@section('title', $client->displayName())
@section('eyebrow', 'Studio')
@section('heading', $client->displayName())
@section('subheading', 'Client since '.optional($sinceDate)->format('M Y').($client->company ? ' · '.$client->company : ''))
@section('header_actions')
    <div class="flex flex-wrap gap-2">
        <x-ui.button :href="route('admin.proposals.create', ['client_id' => $client->id])">New proposal</x-ui.button>
        <x-ui.button variant="secondary" :href="route('admin.contracts.create', ['client_id' => $client->id])">Contract</x-ui.button>
        <x-ui.button variant="secondary" :href="route('admin.invoices.create', ['client_id' => $client->id])">Invoice</x-ui.button>
        <x-ui.button variant="secondary" :href="route('admin.clients.edit', $client)">Edit</x-ui.button>
    </div>
@endsection
@section('content')
    @php($link = 'text-brand-600 hover:underline dark:text-brand-400')
    @if (session('error'))
        <x-ui.alert class="mb-4 !border-rose-200 !bg-rose-50 !text-rose-800 dark:!border-rose-500/30 dark:!bg-rose-500/10 dark:!text-rose-300">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Billed" :value="$money($totalBilledCents)" />
        <x-ui.stat-card label="Paid" :value="$money($totalPaidCents)" />
        <x-ui.stat-card label="Due" :value="$money($totalOutstandingCents)" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($bookings->isNotEmpty())
                <x-ui.card title="Bookings" flush>
                    @foreach ($bookings as $inquiry)
                        @php($job = $inquiry->bookedJob)
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 text-sm last:border-0 dark:border-gray-800">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $job->summary ?: $job->couple_names ?: 'Booking' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">@if ($job->event_date){{ $job->event_date->format('l, M j, Y') }}@else Date TBD @endif</p>
                                <div class="mt-2 flex gap-3 text-xs">
                                    <a class="{{ $link }}" href="{{ route('admin.proposals.create', ['client_id' => $client->id, 'booked_job_id' => $job->id]) }}">+ Proposal</a>
                                    <a class="{{ $link }}" href="{{ route('admin.contracts.create', ['client_id' => $client->id, 'booked_job_id' => $job->id]) }}">+ Contract</a>
                                    <a class="{{ $link }}" href="{{ route('admin.invoices.create', ['client_id' => $client->id, 'booked_job_id' => $job->id]) }}">+ Invoice</a>
                                </div>
                            </div>
                            <x-ui.badge :tone="$job->isCancelled() ? 'gray' : 'green'">{{ $job->portalStage() }}</x-ui.badge>
                        </div>
                    @endforeach
                </x-ui.card>
            @endif

            <x-ui.card title="Activity" flush>
                @if (empty($timeline))
                    <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Nothing yet. New inquiries from {{ $client->email }} land here automatically.</p>
                @else
                    <ol>
                        @foreach ($timeline as $event)
                            @php($tag = $event['url'] ? 'a' : 'div')
                            <li class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <{{ $tag }} class="flex items-start gap-3 px-5 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60" @if ($event['url']) href="{{ $event['url'] }}" @endif>
                                    <span aria-hidden="true">{{ $event['icon'] }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-gray-900 dark:text-white">{{ $event['title'] }}</span>
                                        @if ($event['meta'])
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $event['meta'] }}</span>
                                        @endif
                                    </span>
                                    <time class="shrink-0 text-xs text-gray-500 dark:text-gray-400" datetime="{{ optional($event['at'])->toIso8601String() }}">{{ optional($event['at'])->diffForHumans(null, true) }} ago</time>
                                </{{ $tag }}>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Contact">
                <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    <li><a class="{{ $link }}" href="mailto:{{ $client->email }}">{{ $client->email }}</a></li>
                    @if ($client->phone)
                        <li><a class="{{ $link }}" href="tel:{{ preg_replace('/[^0-9+]/', '', $client->phone) }}">{{ $client->phone }}</a></li>
                    @endif
                    @if ($location)
                        <li>{{ $location }}</li>
                    @endif
                </ul>
            </x-ui.card>

            <x-ui.card title="Portal access">
                @if ($client->password !== null)
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Active · {{ $client->last_login_at ? 'last sign-in '.$client->last_login_at->diffForHumans() : 'not signed in yet' }}
                        @if ($loginCount > 0)
                            · {{ $loginCount }} {{ Str::plural('sign-in', $loginCount) }}
                        @endif
                    </p>
                @else
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">No portal access yet.</p>
                    <form method="POST" action="{{ route('admin.clients.portal-invite', $client) }}">
                        @csrf
                        <x-ui.button variant="secondary" type="submit">Send portal invite</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card title="Questionnaire">
                @if ($questionnaires->isEmpty())
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">Not sent yet. Email {{ $client->email ?: 'the client' }} a link.</p>
                @else
                    @foreach ($questionnaires as $questionnaire)
                        <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">
                            @if ($questionnaire->isSubmitted())
                                Submitted {{ $questionnaire->submitted_at->format('M j, Y') }} ·
                                <a class="{{ $link }}" href="{{ route('admin.questionnaires.show', $questionnaire) }}">View responses</a>
                            @else
                                Sent, awaiting responses ·
                                <a class="{{ $link }}" href="{{ $questionnaire->publicUrl() }}">Open link</a>
                            @endif
                        </p>
                    @endforeach
                @endif
                <form method="POST" action="{{ route('admin.clients.questionnaire', $client) }}" class="mt-3">
                    @csrf
                    <x-ui.button variant="secondary" type="submit">Send questionnaire</x-ui.button>
                </form>
            </x-ui.card>

            @if ($client->notes)
                <x-ui.card title="Internal notes">
                    <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $client->notes }}</p>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection

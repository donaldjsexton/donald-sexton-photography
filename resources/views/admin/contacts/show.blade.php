@extends('layouts.admin-v2')

@section('title', $contact->name ?: $contact->email)
@section('eyebrow', 'Contacts')
@section('heading', $contact->name ?: $contact->email)
@section('header_actions')
    <x-ui.button variant="secondary" :href="route('admin.contacts.index')">&larr; All contacts</x-ui.button>
@endsection
@section('content')
    @php
        $label = $contact->name ?: $contact->email;
        $typeTone = in_array($contact->type, ['couple', 'past_couple'], true) ? 'green' : 'amber';
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $row = 'flex justify-between gap-4 border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800';
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card>
                <div class="mb-4 flex items-center gap-4">
                    <span class="flex size-14 items-center justify-center rounded-full bg-brand-50 text-xl font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">{{ strtoupper(mb_substr($label, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-lg font-semibold text-gray-900 dark:text-white">{{ $label }}</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <x-ui.badge :tone="$typeTone">{{ \App\Models\Contact::TYPES[$contact->type] ?? $contact->type }}</x-ui.badge>
                            <x-ui.badge>{{ \App\Models\Contact::STAGES[$contact->stage] ?? $contact->stage }}</x-ui.badge>
                            @if ($suppression)<x-ui.badge tone="red">Do not contact</x-ui.badge>@endif
                        </div>
                    </div>
                </div>
                <dl>
                    <div class="{{ $row }}"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="break-all text-right text-gray-900 dark:text-gray-100">{{ $contact->email }}</dd></div>
                    @if ($contact->partner_name)<div class="{{ $row }}"><dt class="text-gray-500 dark:text-gray-400">Partner</dt><dd class="text-gray-900 dark:text-gray-100">{{ $contact->partner_name }}</dd></div>@endif
                    @if ($contact->phone)<div class="{{ $row }}"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="text-gray-900 dark:text-gray-100">{{ $contact->phone }}</dd></div>@endif
                    <div class="{{ $row }}"><dt class="text-gray-500 dark:text-gray-400">Source</dt><dd class="text-right text-gray-900 dark:text-gray-100">{{ $contact->source }}@if ($contact->source_detail) <span class="text-gray-500">({{ $contact->source_detail }})</span>@endif</dd></div>
                    @if ($contact->shoot_type)<div class="{{ $row }}"><dt class="text-gray-500 dark:text-gray-400">Shoot</dt><dd class="text-gray-900 dark:text-gray-100">{{ $contact->shoot_type }}</dd></div>@endif
                    @if ($contact->event_date)<div class="{{ $row }}"><dt class="text-gray-500 dark:text-gray-400">Event date</dt><dd class="text-gray-900 dark:text-gray-100">{{ $contact->event_date->format('M j, Y') }}</dd></div>@endif
                    <div class="{{ $row }}"><dt class="text-gray-500 dark:text-gray-400">First contact</dt><dd class="text-gray-900 dark:text-gray-100">{{ optional($contact->first_contact_at)->format('M j, Y') ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-gray-500 dark:text-gray-400">Last contact</dt><dd class="text-gray-900 dark:text-gray-100">{{ optional($contact->last_contact_at)->format('M j, Y') ?: '—' }}</dd></div>
                </dl>
                @if ($contact->notes)
                    <p class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-900/60 dark:text-gray-300">{{ $contact->notes }}</p>
                @endif
                @if ($contact->client)
                    <p class="mt-4 text-sm"><a class="font-medium text-brand-600 hover:underline dark:text-brand-300" href="{{ route('admin.clients.show', $contact->client) }}">Open client record</a></p>
                @endif
            </x-ui.card>

            <x-ui.card title="Inquiries">
                @forelse ($contact->inquiries as $inquiry)
                    <p class="{{ $row }}"><a class="font-medium text-brand-600 hover:underline dark:text-brand-300" href="{{ route('admin.inquiries.edit', $inquiry) }}">{{ $inquiry->primary_name }}</a>
                        <span class="text-gray-500 dark:text-gray-400">{{ $inquiry->status }} · {{ $inquiry->created_at->format('M j, Y') }}</span></p>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">None.</p>
                @endforelse
            </x-ui.card>

            <x-ui.card title="Quiz">
                @forelse ($contact->quizLeads as $lead)
                    <p class="{{ $row }}"><span class="text-gray-900 dark:text-gray-100">{{ ucfirst($lead->result) }} ({{ $lead->score }})</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $lead->created_at->format('M j, Y') }}{{ $lead->unsubscribed_at ? ' · unsubscribed' : '' }}</span></p>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">None.</p>
                @endforelse
            </x-ui.card>
        </div>

        <aside>
            <x-ui.card title="Do not contact" @class(['border-rose-300 dark:border-rose-500/40' => $suppression])>
                @if ($suppression)
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        On the list since {{ $suppression->suppressed_at->format('M j, Y') }}
                        ({{ $suppression->source }}){{ $suppression->reason ? ': '.$suppression->reason : '' }}.
                        Outreach and marketing email is blocked. Invoices and contracts still go to active clients.
                    </p>
                    <form method="POST" action="{{ route('admin.contacts.unsuppress', $contact) }}" class="mt-4">
                        @csrf
                        <x-ui.button type="submit" variant="secondary">Remove from list</x-ui.button>
                    </form>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Not on the list.</p>
                    <form method="POST" action="{{ route('admin.contacts.suppress', $contact) }}" class="mt-4 space-y-4">
                        @csrf
                        <x-ui.field label="Reason (optional)">
                            <input type="text" name="reason" maxlength="255" class="{{ $input }}">
                        </x-ui.field>
                        <x-ui.button type="submit" variant="danger">Mark do not contact</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        </aside>
    </div>
@endsection

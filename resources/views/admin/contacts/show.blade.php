@extends('layouts.admin')

@section('title', $contact->name ?: $contact->email)
@section('eyebrow', 'Contacts')
@section('heading', $contact->name ?: $contact->email)
@section('content')
    @php
        $label = $contact->name ?: $contact->email;
        $typeStyle = in_array($contact->type, ['couple', 'past_couple'], true) ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900';
        $card = 'rounded-2xl border border-[#6c5743]/20 bg-white/80 p-5 shadow-sm';
        $row = 'flex justify-between gap-4 border-b border-[#6c5743]/10 py-2 text-sm last:border-0';
    @endphp

    @if (session('status'))
        <div class="admin-flash" style="margin-bottom:1rem;">{{ session('status') }}</div>
    @endif

    <p class="mb-4 text-sm"><a href="{{ route('admin.contacts.index') }}" class="text-[#6d6256] underline">&larr; All contacts</a></p>

    <div class="[display:grid] gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="{{ $card }}">
                <div class="mb-4 flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#2d1d15] text-lg font-semibold text-white">{{ strtoupper(mb_substr($label, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-lg font-semibold text-[#201714]">{{ $label }}</p>
                        <p class="flex flex-wrap gap-2 pt-1">
                            <span class="rounded-full {{ $typeStyle }} px-2.5 py-0.5 text-xs font-medium">{{ \App\Models\Contact::TYPES[$contact->type] ?? $contact->type }}</span>
                            <span class="rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-stone-800">{{ \App\Models\Contact::STAGES[$contact->stage] ?? $contact->stage }}</span>
                            @if ($suppression)<span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-800">Do not contact</span>@endif
                        </p>
                    </div>
                </div>
                <dl>
                    <div class="{{ $row }}"><dt class="text-[#6d6256]">Email</dt><dd class="break-all text-right">{{ $contact->email }}</dd></div>
                    @if ($contact->partner_name)<div class="{{ $row }}"><dt class="text-[#6d6256]">Partner</dt><dd>{{ $contact->partner_name }}</dd></div>@endif
                    @if ($contact->phone)<div class="{{ $row }}"><dt class="text-[#6d6256]">Phone</dt><dd>{{ $contact->phone }}</dd></div>@endif
                    <div class="{{ $row }}"><dt class="text-[#6d6256]">Source</dt><dd class="text-right">{{ $contact->source }}@if ($contact->source_detail) <span class="text-[#6d6256]">({{ $contact->source_detail }})</span>@endif</dd></div>
                    @if ($contact->shoot_type)<div class="{{ $row }}"><dt class="text-[#6d6256]">Shoot</dt><dd>{{ $contact->shoot_type }}</dd></div>@endif
                    @if ($contact->event_date)<div class="{{ $row }}"><dt class="text-[#6d6256]">Event date</dt><dd>{{ $contact->event_date->format('M j, Y') }}</dd></div>@endif
                    <div class="{{ $row }}"><dt class="text-[#6d6256]">First contact</dt><dd>{{ optional($contact->first_contact_at)->format('M j, Y') ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-[#6d6256]">Last contact</dt><dd>{{ optional($contact->last_contact_at)->format('M j, Y') ?: '—' }}</dd></div>
                </dl>
                @if ($contact->notes)
                    <p class="mt-4 rounded-lg bg-[#f7f0e6] p-3 text-sm text-[#201714]">{{ $contact->notes }}</p>
                @endif
                @if ($contact->client)
                    <p class="mt-4 text-sm"><a class="underline" href="{{ route('admin.clients.show', $contact->client) }}">Open client record</a></p>
                @endif
            </section>

            <section class="{{ $card }}">
                <h2 class="mb-3 text-base font-semibold text-[#201714]">Inquiries</h2>
                @forelse ($contact->inquiries as $inquiry)
                    <p class="{{ $row }}"><a class="underline" href="{{ route('admin.inquiries.edit', $inquiry) }}">{{ $inquiry->primary_name }}</a>
                        <span class="text-[#6d6256]">{{ $inquiry->status }} · {{ $inquiry->created_at->format('M j, Y') }}</span></p>
                @empty
                    <p class="text-sm text-[#6d6256]">None.</p>
                @endforelse
            </section>

            <section class="{{ $card }}">
                <h2 class="mb-3 text-base font-semibold text-[#201714]">Quiz</h2>
                @forelse ($contact->quizLeads as $lead)
                    <p class="{{ $row }}"><span>{{ ucfirst($lead->result) }} ({{ $lead->score }})</span>
                        <span class="text-[#6d6256]">{{ $lead->created_at->format('M j, Y') }}{{ $lead->unsubscribed_at ? ' · unsubscribed' : '' }}</span></p>
                @empty
                    <p class="text-sm text-[#6d6256]">None.</p>
                @endforelse
            </section>
        </div>

        <aside>
            <section class="{{ $card }} {{ $suppression ? 'border-rose-300 bg-rose-50/70' : '' }}">
                <h2 class="mb-2 text-base font-semibold text-[#201714]">Do not contact</h2>
                @if ($suppression)
                    <p class="text-sm text-[#201714]">
                        On the list since {{ $suppression->suppressed_at->format('M j, Y') }}
                        ({{ $suppression->source }}){{ $suppression->reason ? ': '.$suppression->reason : '' }}.
                        Outreach and marketing email is blocked. Invoices and contracts still go to active clients.
                    </p>
                    <form method="POST" action="{{ route('admin.contacts.unsuppress', $contact) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="rounded-lg border border-[#6c5743]/40 bg-white px-4 py-2 text-sm font-medium">Remove from list</button>
                    </form>
                @else
                    <p class="text-sm text-[#6d6256]">Not on the list.</p>
                    <form method="POST" action="{{ route('admin.contacts.suppress', $contact) }}" class="mt-3 space-y-3">
                        @csrf
                        <label class="flex flex-col gap-1 text-xs font-medium text-[#6d6256]">
                            Reason (optional)
                            <input type="text" name="reason" maxlength="255" class="rounded-lg border border-[#6c5743]/30 bg-white px-3 py-2 text-sm text-[#201714]">
                        </label>
                        <button type="submit" class="rounded-lg bg-rose-800 px-4 py-2 text-sm font-medium text-white">Mark do not contact</button>
                    </form>
                @endif
            </section>
        </aside>
    </div>
@endsection

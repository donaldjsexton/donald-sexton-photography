@extends('layouts.admin-v2')

@section('title', 'Custom Domains')
@section('eyebrow', 'Operations')
@section('heading', 'Custom Domains')
@section('subheading', 'Point your own domain at this site. Add it here, create the DNS record we show you, then verify.')
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <x-ui.card title="Connect a domain">
        <form method="POST" action="{{ route('admin.domains.store') }}" class="admin-block-add flex max-w-none! flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <x-ui.field label="Domain" class="flex-1">
                <input class="{{ $input }}" type="text" name="host" value="{{ old('host') }}" placeholder="www.yourstudio.com" required>
            </x-ui.field>
            <x-ui.button type="submit">Add domain</x-ui.button>
        </form>
    </x-ui.card>

    <div class="admin-block-list mt-6 space-y-4">
        @forelse ($domains as $domain)
            <x-ui.card class="admin-block-card space-y-4">
                <header class="admin-block-card__header flex flex-wrap items-center justify-between gap-2">
                    <strong class="text-gray-900 dark:text-white">{{ $domain->host }}</strong>
                    <x-ui.badge :tone="$domain->isVerified() ? 'green' : 'amber'">{{ $domain->isVerified() ? 'Verified · live' : 'Pending verification' }}</x-ui.badge>
                </header>

                @unless ($domain->isVerified())
                    <div class="detail-shell rich-text space-y-2 text-sm text-gray-700 dark:text-gray-300">
                        <p>Add this DNS record at your domain registrar, then click verify:</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li><strong>Type:</strong> TXT</li>
                            <li><strong>Name:</strong> {{ $domain->verificationRecordName() }}</li>
                            <li><strong>Value:</strong> {{ $domain->verification_token }}</li>
                        </ul>
                        <p class="text-gray-500 dark:text-gray-400">Also point the domain (A or CNAME) at this server so traffic reaches your site.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.domains.verify', $domain) }}" class="max-w-none!">
                        @csrf
                        <x-ui.button variant="secondary" type="submit">Verify domain</x-ui.button>
                    </form>
                @endunless

                <form method="POST" action="{{ route('admin.domains.destroy', $domain) }}" onsubmit="return confirm('Remove this domain?');" class="max-w-none!">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" type="submit" class="admin-block-card__delete">Remove</x-ui.button>
                </form>
            </x-ui.card>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No custom domains yet. Your site is reachable at its subdomain.</p>
        @endforelse
    </div>
@endsection

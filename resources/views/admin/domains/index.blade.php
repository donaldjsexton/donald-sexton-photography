@extends('layouts.admin')

@section('title', 'Custom Domains')
@section('heading', 'Custom Domains')
@section('content')
    @if (session('status'))
        <p class="admin-flash">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="admin-section-header">
        <h2>Connect a domain</h2>
        <p class="meta">Point your own domain at this site. Add it here, create the DNS record we show you, then verify.</p>
    </div>

    <form method="POST" action="{{ route('admin.domains.store') }}" class="admin-form admin-block-add">
        @csrf
        <label>
            Domain
            <input type="text" name="host" value="{{ old('host') }}" placeholder="www.yourstudio.com" required>
        </label>
        <button class="cta" type="submit" style="border: 0; cursor: pointer;">Add domain</button>
    </form>

    <div class="admin-block-list">
        @forelse ($domains as $domain)
            <article class="admin-block-card">
                <header class="admin-block-card__header">
                    <strong>{{ $domain->host }}</strong>
                    <span class="meta">{{ $domain->isVerified() ? 'Verified · live' : 'Pending verification' }}</span>
                </header>

                @unless ($domain->isVerified())
                    <div class="detail-shell rich-text">
                        <p>Add this DNS record at your domain registrar, then click verify:</p>
                        <ul>
                            <li><strong>Type:</strong> TXT</li>
                            <li><strong>Name:</strong> {{ $domain->verificationRecordName() }}</li>
                            <li><strong>Value:</strong> {{ $domain->verification_token }}</li>
                        </ul>
                        <p class="meta">Also point the domain (A or CNAME) at this server so traffic reaches your site.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.domains.verify', $domain) }}">
                        @csrf
                        <button class="cta-secondary" type="submit">Verify domain</button>
                    </form>
                @endunless

                <form method="POST" action="{{ route('admin.domains.destroy', $domain) }}" onsubmit="return confirm('Remove this domain?');">
                    @csrf
                    @method('DELETE')
                    <button class="cta-secondary admin-block-card__delete" type="submit">Remove</button>
                </form>
            </article>
        @empty
            <p class="section-copy">No custom domains yet. Your site is reachable at its subdomain.</p>
        @endforelse
    </div>
@endsection

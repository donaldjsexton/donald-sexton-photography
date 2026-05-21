@extends('layouts.app')

@section('title', 'Thank You')

@php
    $justSubmitted = (bool) session('inquiry_submitted');
    $analyticsId = \App\Models\SiteSetting::current()->analyticsMeasurementId();
@endphp

@section('content')
    @if ($justSubmitted && $analyticsId)
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'generate_lead',
                event_category: 'inquiry',
                event_label: 'site_form',
            });
            if (typeof window.gtag === 'function') {
                window.gtag('event', 'generate_lead', {
                    event_category: 'inquiry',
                    event_label: 'site_form',
                });
            }
        </script>
    @endif

    <x-editorial.page-hero
        eyebrow="Inquiry Received"
        title="Thank you."
        copy="Your note is in. Most replies go out within 24 hours, straight from me — never an autoresponder."
        shell="tight"
    />

    <section class="section">
        <div class="page-shell--tight thank-you-next">
            <p class="eyebrow">What Happens Next</p>
            <ol class="thank-you-steps">
                <li>
                    <span class="thank-you-steps__num" aria-hidden="true">1</span>
                    <div>
                        <h3>A personal reply within 24 hours</h3>
                        <p>I read every inquiry and write back with availability and any quick clarifying questions.</p>
                    </div>
                </li>
                <li>
                    <span class="thank-you-steps__num" aria-hidden="true">2</span>
                    <div>
                        <h3>A short call or email back-and-forth</h3>
                        <p>We chat about the day, your venue, and what matters most about the photos.</p>
                    </div>
                </li>
                <li>
                    <span class="thank-you-steps__num" aria-hidden="true">3</span>
                    <div>
                        <h3>A tailored proposal, no pressure</h3>
                        <p>You'll get a quote built around your day — yours to consider with no follow-up nudges.</p>
                    </div>
                </li>
            </ol>

            <div class="cta-row">
                <a class="cta-secondary" href="{{ route('weddings.index') }}">See Wedding Stories</a>
                <a class="cta-secondary" href="{{ route('journal.index') }}">Read the Journal</a>
            </div>
        </div>
    </section>
@endsection

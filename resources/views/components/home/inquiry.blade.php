@props([
    'heading' => null,
    'copy' => null,
])

@php
    $inquiryHeading = $heading ?: 'Check your date in 30 seconds.';
    $inquiryCopy = $copy ?: 'Pop in your name, email, and the day you have in mind. I will take it from there — usually within 24 hours.';
@endphp

<section class="section home-inline-inquiry" data-reveal>
    <div class="page-shell--tight home-inline-inquiry__inner">
        <div class="home-inline-inquiry__intro">
            <p class="eyebrow">Start Your Inquiry</p>
            <h2 class="section-title">{{ $inquiryHeading }}</h2>
            <p class="section-copy">{{ $inquiryCopy }}</p>
        </div>

        <form class="home-inline-inquiry__form" method="GET" action="{{ route('inquiry.create') }}" data-inline-inquiry
              toolname="start_photography_inquiry"
              tooldescription="Start a photography inquiry: carries the person's name, email and date to the full inquiry form on the next page. Nothing is sent to the studio until the full form is submitted.">
            <label>
                Your name
                <input type="text" name="primary_name" autocomplete="name" required toolparamdescription="Name of the person (or couple) asking about photography.">
            </label>
            <label>
                Email
                <input type="email" name="email" autocomplete="email" required toolparamdescription="Email address where the reply should go.">
            </label>
            <label>
                Date
                <input type="date" name="event_date" toolparamdescription="Date of the wedding or session in YYYY-MM-DD format; leave empty if unknown.">
            </label>
            <button class="cta" type="submit">Continue</button>
            <p class="meta home-inline-inquiry__note">Takes you to the full form with your details ready, or <a href="{{ route('weddings.index') }}">browse wedding stories first</a>.</p>
        </form>
    </div>
</section>

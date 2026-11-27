@extends('layouts.app')

@section('title', 'Accessibility Statement')
@section('meta_description', 'Our commitment to an accessible website, known limitations, and how to report a problem.')

@section('content')
    <x-editorial.page-hero
        eyebrow="Legal"
        title="Accessibility Statement"
        copy="Effective {{ $effectiveDate }}"
        shell="tight"
    />

    <x-editorial.reading-section>
        <h2>Our commitment</h2>
        <p>We want everyone to be able to look through our work and contact us. We aim for the site to meet the Web Content Accessibility Guidelines (WCAG) 2.1 Level AA. This is an ongoing effort and we have not had a formal third-party audit.</p>

        <h2>What we do</h2>
        <ul>
            <li>A "Skip to main content" link at the top of every page.</li>
            <li>Visible keyboard focus and navigation that works without a mouse.</li>
            <li>Labeled form fields, with error messages shown in text.</li>
            <li>Text descriptions (alt text) for photographs.</li>
            <li>One main heading per page, with headings in order, and a language set on each page.</li>
            <li>Respect for the reduced-motion setting on your device.</li>
        </ul>

        <h2>Known gaps</h2>
        <ul>
            <li>Some older photos may have short or generic descriptions. We are improving them over time.</li>
            <li>Galleries and videos embedded from Pic-Time are controlled by Pic-Time, and may not fully meet WCAG 2.1 AA.</li>
            <li>The venue search on the inquiry form and the multi-step form behavior may not work perfectly with every screen reader. You can always send your details by email instead.</li>
            <li>Text over photographs may have uneven contrast in some places.</li>
            <li>Payment forms from Square and PayPal, and signed contracts and invoices provided as PDFs, may not be fully accessible. We will gladly provide the information in another form on request.</li>
        </ul>

        <h2>Tell us about a problem</h2>
        <p>If you cannot use part of the site, or you need information in another format, email @include('legal._email') and tell us the page and what went wrong. We aim to reply within 5 business days and to fix or offer a workaround as soon as we reasonably can.</p>

        <p>See also our <a href="{{ route('legal.privacy') }}">Privacy Policy</a> and <a href="{{ route('legal.terms') }}">Terms of Service</a>.</p>

        <h2>Contact</h2>
        @include('legal._contact')
    </x-editorial.reading-section>
@endsection

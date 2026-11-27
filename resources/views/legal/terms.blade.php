@extends('layouts.app')

@section('title', 'Terms of Service')
@section('meta_description', 'Terms of service for Donald Sexton Photography, including SMS messaging terms and conditions.')

@section('content')
    <x-editorial.page-hero
        eyebrow="Legal"
        title="Terms of Service"
        copy="Effective {{ $effectiveDate }}"
        shell="tight"
    />

    <x-editorial.reading-section>
        <h2>Agreement</h2>
        <p>By using donaldsextonphotography.com ("the Site") you agree to these terms. If you do not agree, please do not use the Site.</p>

        <h2>What We Do</h2>
        <p>Donald Sexton Photography ("we," "us," "our") provides wedding and event photography. Through the Site you can view our work, send an inquiry, and communicate with us about a booking.</p>

        <h2>Who we are</h2>
        <p>The Site and the services are provided by Donald Sexton Photography, a sole proprietorship in Clearwater, Florida.</p>

        <h2>Inquiries</h2>
        <p>When you submit an inquiry you agree to provide accurate information. We will use it to respond and coordinate as described in our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>. Sending an inquiry or taking the coverage quiz does not book us and does not create a contract.</p>

        <h2>Bookings, Contracts, and Payments</h2>
        <p>A booking is only confirmed when you and we have both signed a written contract (through the client portal or otherwise) and any retainer or booking fee in it has been paid. The contract sets out the price, schedule, cancellation, and delivery terms, and if it differs from these terms, the contract controls. Invoices are paid online through Square or PayPal, under those companies' terms. Prices or availability shown on the Site or in a quiz result are estimates, not offers.</p>

        <h2>Client Portal and Galleries</h2>
        <p>Keep your portal password and gallery links private. You are responsible for activity under your account. Delivered galleries are available for the period stated in your contract, so please download your photos. Tell us if you notice any unauthorized use of your account.</p>

        <h2>Social Media</h2>
        <p>Unless your contract says otherwise, we may share work from your session on our website and social channels such as Facebook and Instagram, and tag the venue and other vendors. Tell us if you prefer that we not, and we will take posts down on request.</p>

        <h2>Text Messages</h2>
        <p>We offer optional text-message updates. By checking the unchecked-by-default consent box on our inquiry form you agree to receive the messages described below. Consent is not a condition of inquiring or booking.</p>

        <h3>What You Can Receive</h3>
        <p><strong>Session texts</strong>: appointment reminders, booking confirmations, and session details. We will not send promotional texts unless we first offer a separate opt-in for them.</p>

        <h3>Frequency and Cost</h3>
        <p>Message frequency varies. We do not charge for texts, but your carrier's standard message and data rates may apply.</p>

        <h3>Carriers</h3>
        <p>Major U.S. carriers are supported, including AT&T, T-Mobile, and Verizon. Carriers are not liable for delayed or undelivered messages.</p>

        <h3>Opting Out</h3>
        <p>Reply <strong>STOP</strong> to any message to unsubscribe. You will receive one confirmation and no further texts unless you re-enroll.</p>

        <h3>Help</h3>
        <p>Reply <strong>HELP</strong> for assistance, or email @include('legal._email').</p>

        <h3>Delivery</h3>
        <p>We are not responsible for messages delayed or lost due to carrier issues, device incompatibility, or number changes. T-Mobile is not liable for delayed or undelivered messages.</p>

        <h2>Intellectual Property</h2>
        <p>All photographs, text, graphics, logos, and design on this Site belong to Donald Sexton Photography and are protected by copyright. Do not reproduce or redistribute any content without written permission. Rights in photographs we take for you are described in your contract.</p>

        <h2>Your Conduct</h2>
        <p>Do not use the Site for anything unlawful or harmful, and do not submit false information through our forms.</p>

        <h2>Accessibility and Data</h2>
        <p>Read our <a href="{{ route('legal.accessibility') }}">Accessibility Statement</a> and our <a href="{{ route('legal.data-deletion') }}">data deletion instructions</a>.</p>

        <h2>Limitation of Liability</h2>
        <p>To the fullest extent the law allows, we are not liable for indirect, incidental, special, or consequential damages from your use of the Site or our services. Our total liability will not exceed what you have paid us. Nothing here limits rights you have that cannot be limited by law, or terms in your signed contract.</p>

        <h2>Changes</h2>
        <p>We may update these terms or discontinue any Site feature — including text messages — at any time. Continued use after a change means you accept the update.</p>

        <h2>Governing Law</h2>
        <p>These terms are governed by the laws of the State of Florida.</p>

        <h2>Contact</h2>
        @include('legal._contact')
    </x-editorial.reading-section>
@endsection

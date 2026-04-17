@extends('layouts.app')

@section('title', 'Terms of Service — Donald Sexton Photography')
@section('meta_description', 'Terms of service for Donald Sexton Photography, including SMS messaging terms and conditions.')

@section('content')
    <x-editorial.page-hero
        eyebrow="Legal"
        title="Terms of Service"
        copy="Last updated: April 16, 2026"
        shell="tight"
    />

    <x-editorial.reading-section>
        <h2>Agreement</h2>
        <p>By using donaldsextonphotography.com ("the Site") you agree to these terms. If you do not agree, please do not use the Site.</p>

        <h2>What We Do</h2>
        <p>Donald Sexton Photography ("we," "us," "our") provides wedding and event photography. Through the Site you can view our work, send an inquiry, and communicate with us about a booking.</p>

        <h2>Inquiries</h2>
        <p>When you submit an inquiry you agree to provide accurate information. We will use it to respond and coordinate as described in our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>

        <h2>Text Messages</h2>
        <p>We offer optional text-message updates. By checking a consent box on our inquiry form you agree to receive the messages described below.</p>

        <h3>What You Can Receive</h3>
        <ul>
            <li><strong>Session texts</strong> — appointment reminders, booking confirmations, and session details.</li>
            <li><strong>Promotional texts</strong> — special offers, mini-session announcements, and seasonal promotions. Consent to promotional texts is not a condition of purchase.</li>
        </ul>

        <h3>Frequency and Cost</h3>
        <p>Message frequency varies. We do not charge for texts, but your carrier's standard message and data rates may apply.</p>

        <h3>Carriers</h3>
        <p>Major U.S. carriers are supported, including AT&T, T-Mobile, and Verizon. Carriers are not liable for delayed or undelivered messages.</p>

        <h3>Opting Out</h3>
        <p>Reply <strong>STOP</strong> to any message to unsubscribe. You will receive one confirmation and no further texts unless you re-enroll.</p>

        <h3>Help</h3>
        <p>Reply <strong>HELP</strong> for assistance, or email <a href="mailto:donaldjsexton@gmail.com">donaldjsexton@gmail.com</a>.</p>

        <h3>Delivery</h3>
        <p>We are not responsible for messages delayed or lost due to carrier issues, device incompatibility, or number changes. T-Mobile is not liable for delayed or undelivered messages.</p>

        <h2>Intellectual Property</h2>
        <p>All photographs, text, graphics, logos, and design on this Site belong to Donald Sexton Photography and are protected by copyright. Do not reproduce or redistribute any content without written permission.</p>

        <h2>Your Conduct</h2>
        <p>Do not use the Site for anything unlawful or harmful, and do not submit false information through our forms.</p>

        <h2>Limitation of Liability</h2>
        <p>To the fullest extent the law allows, we are not liable for indirect, incidental, special, or consequential damages from your use of the Site or our services. Our total liability will not exceed what you have paid us.</p>

        <h2>Changes</h2>
        <p>We may update these terms or discontinue any Site feature — including text messages — at any time. Continued use after a change means you accept the update.</p>

        <h2>Governing Law</h2>
        <p>These terms are governed by the laws of the State of Florida.</p>

        <h2>Contact</h2>
        <p>Donald Sexton Photography<br>
        <a href="mailto:donaldjsexton@gmail.com">donaldjsexton@gmail.com</a><br>
        donaldsextonphotography.com</p>
    </x-editorial.reading-section>
@endsection

@extends('layouts.app')

@section('title', 'Terms of Service — Donald Sexton Photography')
@section('meta_description', 'Terms of service for Donald Sexton Photography, including SMS messaging terms.')

@section('content')
    <x-editorial.page-hero
        eyebrow="Legal"
        title="Terms of Service"
        copy="Last updated: April 16, 2026"
        shell="tight"
    />

    <x-editorial.reading-section>
        <h2>Acceptance of Terms</h2>
        <p>By accessing or using the website at donaldsextonphotography.com ("the Site"), you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use the Site.</p>

        <h2>Services</h2>
        <p>Donald Sexton Photography ("we," "us," or "our") provides wedding and event photography services. The Site allows you to view our portfolio, submit inquiries, and communicate with us about potential bookings.</p>

        <h2>Inquiry Submissions</h2>
        <p>When you submit an inquiry through our website, you agree to provide accurate and complete information. We will use the information you provide to respond to your inquiry and communicate about our services as described in our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>

        <h2>Text Messaging (SMS) Terms</h2>
        <p>Donald Sexton Photography provides text message notifications for booking confirmations, appointment reminders, session details, and optional promotional communications.</p>

        <h3>Opt-In and Consent</h3>
        <p>By providing your phone number and checking the applicable consent box on our inquiry form, you agree to receive text messages as described:</p>
        <ul>
            <li><strong>Transactional messages:</strong> Appointment reminders, booking confirmations, and session details.</li>
            <li><strong>Promotional messages:</strong> Special offers, mini-session announcements, and photography promotions. Consent to receive promotional messages is not a condition of purchasing our services.</li>
        </ul>

        <h3>Message Frequency and Rates</h3>
        <p>Message frequency varies based on your interactions with us. There is no fee from Donald Sexton Photography for receiving text messages; however, message and data rates may apply from your mobile carrier. You are responsible for any charges from your carrier.</p>

        <h3>Supported Carriers</h3>
        <p>SMS services are available to users with a compatible mobile device on participating carriers. Major U.S. carriers are supported, including AT&T, T-Mobile, Verizon, and others. Carriers are not liable for delayed or undelivered messages.</p>

        <h3>Opting Out</h3>
        <p>You may opt out of text messages at any time by texting <strong>STOP</strong> to any message you receive from us. After opting out, you will receive a single confirmation message. You will receive no further text messages unless you re-enroll.</p>

        <h3>Help</h3>
        <p>Text <strong>HELP</strong> to any message for assistance, or contact us at <a href="mailto:donaldjsexton@gmail.com">donaldjsexton@gmail.com</a>.</p>

        <h3>Liability</h3>
        <p>Donald Sexton Photography is not responsible for delayed or undelivered messages due to carrier network issues, device incompatibility, changes to your phone number, or other conditions outside our control. T-Mobile is not liable for delayed or undelivered messages.</p>

        <h2>Intellectual Property</h2>
        <p>All content on this Site — including photographs, text, graphics, logos, and design — is the property of Donald Sexton Photography and is protected by copyright law. You may not reproduce, distribute, or otherwise use any content from this Site without our express written permission.</p>

        <h2>User Conduct</h2>
        <p>You agree not to use the Site in any way that is unlawful, harmful, or interferes with the Site's operation. You agree not to submit false or misleading information through our forms.</p>

        <h2>Limitation of Liability</h2>
        <p>To the fullest extent permitted by law, Donald Sexton Photography shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of the Site or our services. Our total liability shall not exceed the amount you have paid us for services.</p>

        <h2>Modifications</h2>
        <p>We reserve the right to modify these Terms of Service or discontinue any feature of the Site (including SMS services) at any time, with or without notice. Your continued use of the Site after changes are posted constitutes acceptance of the updated terms.</p>

        <h2>Governing Law</h2>
        <p>These Terms of Service are governed by the laws of the State of Florida, without regard to conflict of law principles.</p>

        <h2>Contact</h2>
        <p>If you have questions about these Terms of Service, please contact us at:</p>
        <p>Donald Sexton Photography<br>
        Email: <a href="mailto:donaldjsexton@gmail.com">donaldjsexton@gmail.com</a><br>
        Website: donaldsextonphotography.com</p>
    </x-editorial.reading-section>
@endsection

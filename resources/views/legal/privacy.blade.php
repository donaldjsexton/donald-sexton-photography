@extends('layouts.app')

@section('title', 'Privacy Policy — Donald Sexton Photography')
@section('meta_description', 'How Donald Sexton Photography collects, uses, and protects your personal information, including SMS data practices.')

@section('content')
    <x-editorial.page-hero
        eyebrow="Legal"
        title="Privacy Policy"
        copy="Last updated: April 16, 2026"
        shell="tight"
    />

    <x-editorial.reading-section>
        <h2>Overview</h2>
        <p>This policy describes how Donald Sexton Photography ("we," "us," "our") handles your personal information when you visit donaldsextonphotography.com or use our services.</p>

        <h2>What We Collect</h2>
        <p>When you fill out a form or book with us, we collect what you share:</p>
        <ul>
            <li>Name, email, and phone number</li>
            <li>Event details — date, venue, location, guest count</li>
            <li>Instagram handle, if provided</li>
            <li>Any message you write to us</li>
        </ul>
        <p>If you opt in to text messages, we also record your mobile number, which consent boxes you checked, the date and time of consent, and your IP address at that moment.</p>
        <p>Our site uses Google Analytics, which automatically collects your IP address, browser type, and pages visited. No names or other personal identifiers are sent to Google.</p>

        <h2>How We Use It</h2>
        <ul>
            <li>Reply to your inquiry and coordinate your session</li>
            <li>Send reminders, confirmations, and session details by email or text (with your consent)</li>
            <li>Send promotional messages — offers, mini-sessions, seasonal work — by text (only with your separate consent)</li>
            <li>Improve the website based on anonymous usage patterns</li>
        </ul>

        <h2>Text Messages</h2>
        <p>We offer two kinds of text messages. You choose each one independently on our inquiry form — neither is required.</p>

        <p><strong>Session texts</strong> — reminders, confirmations, logistics.<br>
        <strong>Promotional texts</strong> — offers, mini-session announcements, seasonal specials.</p>

        <p>Message frequency varies. Message and data rates may apply.</p>

        <p><strong>We will never sell, rent, or share your phone number or SMS consent with any third party for marketing.</strong></p>

        <p>We use Twilio to deliver messages. Your number is shared with Twilio only for delivery — no other third party receives it.</p>

        <p>To stop texts, reply <strong>STOP</strong> at any time. You will get one confirmation and nothing further. To get help, reply <strong>HELP</strong> or email <a href="mailto:donaldjsexton@gmail.com">donaldjsexton@gmail.com</a>. Opting out of texts does not affect your photography services or email communications.</p>

        <h2>Who Sees Your Information</h2>
        <p>We do not sell or rent your data. We share it only with:</p>
        <ul>
            <li><strong>Service providers</strong> — Twilio (SMS), our email platform, and our hosting provider — strictly to deliver their part of the service</li>
            <li><strong>Legal authorities</strong> — only when required by law</li>
        </ul>

        <h2>Cookies and Analytics</h2>
        <p>Google Analytics helps us see which pages are visited and how people find the site. It does not identify you by name. You can opt out with the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener">Google Analytics Opt-out Add-on</a>.</p>

        <h2>Security</h2>
        <p>We use reasonable technical safeguards to protect your information. No system is perfectly secure, but we take the responsibility seriously.</p>

        <h2>Your Rights</h2>
        <p>You can ask to see, correct, or delete your personal information at any time by emailing <a href="mailto:donaldjsexton@gmail.com">donaldjsexton@gmail.com</a>.</p>

        <h2>Children</h2>
        <p>This site is not directed at anyone under 13, and we do not knowingly collect information from children.</p>

        <h2>Changes</h2>
        <p>If we update this policy, we will post the new version here and update the date at the top. Continued use of the site after a change means you accept the update.</p>

        <h2>Contact</h2>
        <p>Donald Sexton Photography<br>
        <a href="mailto:donaldjsexton@gmail.com">donaldjsexton@gmail.com</a><br>
        donaldsextonphotography.com</p>
    </x-editorial.reading-section>
@endsection

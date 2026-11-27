@extends('layouts.app')

@section('title', 'Privacy Policy')
@section('meta_description', 'How Donald Sexton Photography collects, uses, shares, and protects your personal information.')

@section('content')
    <x-editorial.page-hero
        eyebrow="Legal"
        title="Privacy Policy"
        copy="Effective {{ $effectiveDate }}"
        shell="tight"
    />

    <x-editorial.reading-section>
        <h2>Who we are</h2>
        <p>Donald Sexton Photography is a sole proprietorship run by Donald Sexton in Clearwater, Florida. In this policy, "we," "us," and "our" mean the studio. It covers donaldsextonphotography.com and the services we provide to clients, including the client portal.</p>

        <h2>What we collect</h2>
        <ul>
            <li><strong>Inquiry form:</strong> your name, email, event date, event type, venue, how you heard about us, your message, and your phone number if you give one. We also keep campaign tags (UTM) from the link you arrived on.</li>
            <li><strong>Coverage quiz:</strong> your first name, email, your quiz answers (including any wedding month and venue you enter), and the date, time, and IP address when you ticked the consent box.</li>
            <li><strong>Text message opt-in (optional):</strong> the box you ticked, the date and time, and your IP address at that moment.</li>
            <li><strong>Clients:</strong> contact details, event details, questionnaire answers, contracts and signatures, proposals, invoices and payment status, and the photo galleries we deliver to you. Portal accounts also hold your email and a password stored in scrambled (hashed) form.</li>
            <li><strong>Email we exchange with you:</strong> our replies and the messages you send us.</li>
            <li><strong>Website usage:</strong> if analytics is turned on, Google Analytics records pages visited, approximate location, device and browser type, and your IP address.</li>
        </ul>
        <p>We do not store your card number. Card and PayPal details are entered with the payment provider, not with us.</p>

        <h2>How we use it</h2>
        <ul>
            <li>Answer your inquiry, check availability, and plan your session or wedding.</li>
            <li>Prepare contracts, proposals, and invoices, take payments, and deliver your photos.</li>
            <li>Email you the quiz result you asked for. This is a single email with an unsubscribe link.</li>
            <li>Send session reminders and confirmations by text, only if you opted in.</li>
            <li>Keep records we need for taxes and accounting.</li>
            <li>Understand how the site is used and fix problems.</li>
        </ul>
        <p>We do not run automated marketing email campaigns. We only send marketing email to people who agreed to it, and every such message includes an unsubscribe link. If you unsubscribe, we add your address to a do-not-contact list so we do not email you marketing again. Messages about a job you have booked with us (contracts, invoices, questionnaires, portal access) are service messages and may still be sent.</p>

        <h2>Text messages</h2>
        <p>The inquiry form has one optional, unchecked box for session texts: appointment reminders, booking confirmations, and session details. You do not need to tick it to inquire or book. Message frequency varies, and message and data rates may apply. Reply <strong>STOP</strong> to opt out or <strong>HELP</strong> for help. We do not sell or share your phone number or text consent with anyone for marketing. Opting out of texts does not affect your photography services or email.</p>

        <h2>Who we share it with</h2>
        <p>We do not sell your personal information. We share it only with the companies that help us run the studio, and only as needed for their part of the work:</p>
        <ul>
            <li><strong>Google</strong> (<a href="https://policies.google.com/privacy" rel="noopener">privacy policy</a>): Gmail and Google Workspace for sending and receiving email, Google Calendar for scheduling, and Google Analytics for site statistics.</li>
            <li><strong>Square</strong> (<a href="https://squareup.com/us/en/legal/general/privacy" rel="noopener">privacy policy</a>) <strong>and PayPal:</strong> to process invoice payments. They handle your payment details under their own privacy policies.</li>
            <li><strong>Pic-Time</strong> (<a href="https://www.pic-time.com/legal/privacy-policy" rel="noopener">privacy policy</a>): some galleries on this site and some client galleries are hosted or embedded from Pic-Time, which may set its own cookies when you view them.</li>
            <li><strong>Our hosting and file-storage providers:</strong> where the site, database, and photos are stored.</li>
            <li><strong>Anthropic</strong> (<a href="https://www.anthropic.com/legal/privacy" rel="noopener">privacy policy</a>): we use an AI service to read emails that venues forward to us about a couple's inquiry, so we can pull out the couple's name, date, and contact details. We also use it to draft website descriptions and image descriptions from our own content.</li>
            <li><strong>Your mobile carrier and any messaging service we use:</strong> to deliver text messages you opted in to.</li>
            <li><strong>Authorities:</strong> when the law requires it.</li>
        </ul>
        <p>If we ever sell or transfer the business, client records could transfer with it.</p>

        <h2>Social media and venue tagging</h2>
        <p>We share photos from weddings and sessions on Facebook and Instagram, and we may tag the venue's public business Page or account (for example, a venue or a vendor). Tagging a venue shares only the venue's public name, not your personal details. We tag or name you, or show identifiable photos of you, in public posts only where our contract allows it or you have agreed. To have a post changed or taken down, email us and we will do so.</p>
        <p>Facebook and Instagram are run by Meta, and their handling of your information is covered by Meta's policies. If you contact us through a social platform, that message is also held by the platform.</p>

        <h2>Cookies and analytics</h2>
        <p>The site uses a few essential cookies to keep forms secure and to keep you signed in to the client portal. If analytics is enabled, Google Analytics loads after you first interact with the page and sets cookies to count visits. We do not use advertising or retargeting pixels. You can block analytics with your browser settings or the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener">Google Analytics Opt-out Add-on</a>. We do not currently show a cookie banner.</p>

        <h2>How long we keep it</h2>
        <ul>
            <li><strong>Inquiries that do not become a booking, and quiz entries:</strong> up to 24 months after the last contact, then deleted.</li>
            <li><strong>Client records, contracts, invoices, and payment records:</strong> 7 years after the job, for tax and accounting purposes.</li>
            <li><strong>Delivered photo galleries:</strong> for the hosting period in your contract. We may keep a backup copy of delivered work.</li>
            <li><strong>Do-not-contact entries</strong> (just your email address): kept so we can honor your request.</li>
            <li><strong>Google Analytics data:</strong> kept by Google for the period set in our analytics account, which is 14 months at most.</li>
        </ul>
        <p>You can ask us to delete your information at any time, sooner than these periods. See <a href="{{ route('legal.data-deletion') }}">how to request data deletion</a>.</p>

        <h2>Security</h2>
        <p>We use reasonable safeguards: encrypted connections, password hashing, and access limited to the studio. No system is perfectly secure. If a breach of security affects your personal information, we will notify you as required by Florida law (section 501.171, Florida Statutes).</p>

        <h2>Your choices and rights</h2>
        <p>You can ask us to show you, correct, export, or delete your personal information, and you can withdraw consent to texts or marketing email at any time. Email @include('legal._email'). Depending on where you live, you may have additional rights under local law, and we will honor them where they apply.</p>

        <h2>Children</h2>
        <p>This site is not directed at anyone under 13, and we do not knowingly collect information from children. Photos of children are taken only at the request of their parent or guardian.</p>

        <h2>Changes</h2>
        <p>If we update this policy, we will post the new version here and change the effective date above.</p>

        <h2>Contact</h2>
        @include('legal._contact')
    </x-editorial.reading-section>
@endsection

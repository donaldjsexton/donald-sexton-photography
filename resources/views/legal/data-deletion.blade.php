@extends('layouts.app')

@section('title', 'Data Deletion')
@section('meta_description', 'How to ask Donald Sexton Photography to delete your personal data, and what happens next.')

@section('content')
    <x-editorial.page-hero
        eyebrow="Legal"
        title="Data Deletion Instructions"
        copy="Effective {{ $effectiveDate }}"
        shell="tight"
    />

    <x-editorial.reading-section>
        <h2>How to ask</h2>
        <p>Send an email to @include('legal._email') with the subject line <strong>Delete my data</strong>. Please write from the email address you used with us (or tell us which address, phone number, or name we should look for) so we can find your records. This applies to anything you gave us through the website, the coverage quiz, text messages, the client portal, or by email, and to information connected to a Facebook or Instagram interaction with our business.</p>

        <h2>What happens next</h2>
        <ol>
            <li>We reply within 5 business days to confirm we received your request. If we are not sure it is you, we may ask you to confirm by replying from the address on file.</li>
            <li>We delete or anonymize your personal information within 30 days of confirming your identity, and we tell you when it is done.</li>
        </ol>

        <h2>What we delete</h2>
        <ul>
            <li>Inquiry details, quiz answers, contact records, notes, and our email history with you.</li>
            <li>Your text-message opt-in and your portal account.</li>
            <li>Your photo galleries, if you ask for that, once you have your files. Please download your photos first, because deleted galleries cannot be restored.</li>
        </ul>

        <h2>What we may have to keep</h2>
        <ul>
            <li>Signed contracts, invoices, and payment records, which we are required to keep for tax and accounting purposes. We keep only what is needed and delete the rest.</li>
            <li>A minimal do-not-contact record (your email address only) so we do not contact you again.</li>
            <li>Information we must keep to resolve a dispute or comply with the law.</li>
        </ul>
        <p>Backups are overwritten on their normal schedule, so a deleted record may remain in a backup for a short time before it is removed.</p>

        <h2>Other places your information may be</h2>
        <ul>
            <li><strong>Payments:</strong> Square and PayPal keep their own payment records. Ask them directly to delete those.</li>
            <li><strong>Facebook and Instagram:</strong> to remove a post or tag of you, email us and we will take it down or remove the tag. Messages and comments on Facebook or Instagram also live on Meta's platforms, where you can delete them yourself in the app or through Meta's own tools.</li>
            <li><strong>Email and calendar:</strong> messages in your own mailbox are yours to delete.</li>
        </ul>

        <h2>Just want fewer emails or texts?</h2>
        <p>Use the unsubscribe link in any email, or reply <strong>STOP</strong> to a text. That stops marketing without deleting your records.</p>

        <p>More detail on what we collect is in our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>

        <h2>Contact</h2>
        @include('legal._contact')
    </x-editorial.reading-section>
@endsection

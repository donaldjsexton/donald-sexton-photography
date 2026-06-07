<?php

namespace App\Services\VenueReferral;

use App\Mail\VenueReferralIntro;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\Venue;
use App\Services\Gmail\GmailReader;
use App\Services\Gmail\ParsedGmailMessage;
use App\Services\WebPushService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VenueReferralIngestor
{
    public const SOURCE_AUTO = 'venue_referral';

    public const SOURCE_PENDING = 'venue_referral_pending';

    public const SOURCE_GATED = 'venue_referral_gated';

    private const NEW_CLIENT_SUBJECT_REGEX = '/^(?:Fwd:\s*)?New Clients?(?:\s+Info)?[\s\-:]/i';

    private const AVAILABILITY_SUBJECT_REGEX = '/\bavailable\b/i';

    private const CONFIDENCE_THRESHOLD = 0.85;

    public function __construct(
        private readonly GmailReader $reader,
        private readonly VenueReferralExtractor $extractor,
    ) {}

    /**
     * @return array{checked: int, created: int, auto_sent: int, queued_for_review: int}
     */
    public function ingest(int $withinDays = 14): array
    {
        $stats = ['checked' => 0, 'created' => 0, 'auto_sent' => 0, 'queued_for_review' => 0];

        if (! $this->reader->isAvailable()) {
            return $stats;
        }

        $venuesByEmail = $this->venuesIndexedByReferralEmail();

        if ($venuesByEmail->isEmpty()) {
            return $stats;
        }

        $query = $this->buildQuery($venuesByEmail, $withinDays);
        $messages = $this->reader->searchMessages($query, 50);

        foreach ($messages as $message) {
            $stats['checked']++;

            if ($this->alreadyProcessed($message)) {
                continue;
            }

            $venue = $venuesByEmail->get(strtolower($message->fromEmail));

            if ($venue === null) {
                continue;
            }

            $subjectMatches = $this->subjectMatchesVenue($message->subject, $venue);
            $gated = $venue->requiresReferralApproval();

            // Non-gated venues only ingest New-Client referrals.
            if (! $gated && ! $subjectMatches) {
                continue;
            }

            // Gated venues (e.g. Gulf Beach Weddings) catch all of their referral
            // mail, not just a subject keyword: a matching "Are You Available?"
            // subject always qualifies, and any other email qualifies once its
            // body carries contact details — so confirmed-booking notifications
            // are picked up while newsletters and chatter are not.
            if ($gated && ! $subjectMatches && ! $this->bodyHasContactDetails($message->bodyPlain)) {
                continue;
            }

            $referral = $this->extractor->extract($message->subject, $message->bodyPlain);

            // The broad gated catch only files a lead once the body actually
            // parses into a usable couple referral.
            if ($gated && ! $subjectMatches && ! $this->looksLikeReferral($referral)) {
                continue;
            }

            $this->process($message, $venue, $referral, $stats);
        }

        return $stats;
    }

    /**
     * Non-gated venues only ingest "New Client" referrals (the existing,
     * auto-send-eligible flow). Gated venues additionally ingest broadcast
     * "Are You Available?" style subjects, which always wait for approval.
     */
    private function subjectMatchesVenue(string $subject, Venue $venue): bool
    {
        if (preg_match(self::NEW_CLIENT_SUBJECT_REGEX, $subject) === 1) {
            return true;
        }

        return $venue->requiresReferralApproval()
            && preg_match(self::AVAILABILITY_SUBJECT_REGEX, $subject) === 1;
    }

    /**
     * @param  array{checked: int, created: int, auto_sent: int, queued_for_review: int}  $stats
     */
    private function process(ParsedGmailMessage $message, Venue $venue, ?ExtractedReferral $referral, array &$stats): void
    {
        $autoSendEligible = $referral !== null
            && $referral->isComplete()
            && $referral->confidence >= self::CONFIDENCE_THRESHOLD
            && $this->isFutureDate($referral->eventDate, $message->sentAt)
            && ! $this->emailAlreadyTrackedAsClient($referral->primaryEmail, $venue);

        // A gated venue never auto-sends, no matter how confident the
        // extraction is — the couple isn't confirmed until Donald replies.
        $shouldAutoSend = $autoSendEligible && ! $venue->requiresReferralApproval();

        $source = match (true) {
            $shouldAutoSend => self::SOURCE_AUTO,
            $venue->requiresReferralApproval() => self::SOURCE_GATED,
            default => self::SOURCE_PENDING,
        };

        $inquiry = $this->createInquiry($message, $venue, $referral, $source);
        $stats['created']++;

        $this->attachInboundMessage($inquiry, $message);

        if ($shouldAutoSend) {
            $this->sendIntro($inquiry, $venue);
            $stats['auto_sent']++;
        } else {
            $this->notifyForReview($inquiry, $venue, $referral);
            $stats['queued_for_review']++;
        }
    }

    private function createInquiry(
        ParsedGmailMessage $message,
        Venue $venue,
        ?ExtractedReferral $referral,
        string $source,
    ): Inquiry {
        $primaryEmail = $referral?->primaryEmail
            ?? sprintf('unknown+%s@%s', Str::random(8), 'venue-referral.local');

        $inquiry = new Inquiry([
            'primary_name' => $referral?->primaryName() ?: 'Unknown — see venue email',
            'partner_name' => $referral?->partnerName(),
            'email' => $primaryEmail,
            'email_secondary' => $referral?->secondaryEmail,
            'phone' => $referral?->phone,
            'event_type' => 'wedding',
            'event_date' => $referral?->eventDate,
            'venue_id' => $venue->id,
            'venue_name' => $venue->name,
            'message' => $this->buildInquiryNote($message, $referral),
            'status' => 'new',
            'source' => $source,
            'gmail_thread_id' => $message->threadId !== '' ? $message->threadId : null,
        ]);

        // File the lead under the venue's own tenant rather than whatever site
        // the command happened to resolve to, so it surfaces in that brand's
        // admin. `site_id` is managed by BelongsToSite (not mass-assignable),
        // so set it directly; the creating hook leaves a non-null value alone.
        $inquiry->site_id = $venue->site_id;
        $inquiry->save();

        return $inquiry;
    }

    private function attachInboundMessage(Inquiry $inquiry, ParsedGmailMessage $message): void
    {
        InquiryMessage::create([
            'inquiry_id' => $inquiry->id,
            'direction' => 'inbound',
            'body' => $message->bodyPlain,
            'sender_name' => $message->fromName,
            'sender_email' => $message->fromEmail,
            'sent_at' => $message->sentAt,
            'gmail_message_id' => $message->id,
        ]);
    }

    private function sendIntro(Inquiry $inquiry, Venue $venue): void
    {
        try {
            $mail = Mail::to($inquiry->email, $inquiry->primary_name);

            if ($inquiry->email_secondary) {
                $mail->cc($inquiry->email_secondary);
            }

            $mail->send(new VenueReferralIntro($inquiry, $venue));

            $inquiry->update(['first_responded_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('VenueReferralIntro send failed: '.$e->getMessage());
        }
    }

    private function notifyForReview(Inquiry $inquiry, Venue $venue, ?ExtractedReferral $referral): void
    {
        $title = $venue->requiresReferralApproval()
            ? 'Availability request needs your approval'
            : 'Venue referral needs review';

        try {
            app(WebPushService::class)->notify(
                $title,
                $referral?->primaryName() ?: 'Could not auto-extract — check inbox.',
                route('admin.inquiries.edit', $inquiry),
            );
        } catch (\Throwable $e) {
            Log::warning('Venue referral review push failed: '.$e->getMessage());
        }
    }

    private function alreadyProcessed(ParsedGmailMessage $message): bool
    {
        return InquiryMessage::query()
            ->where('gmail_message_id', $message->id)
            ->exists();
    }

    private function emailAlreadyTrackedAsClient(?string $email, Venue $venue): bool
    {
        if ($email === null) {
            return false;
        }

        // Scope the dedupe check to the venue's tenant explicitly — the command
        // runs without a resolved site, so the default `site` scope can't be
        // relied on to look in the right brand's inquiries.
        return Inquiry::withoutSiteScope()
            ->where('site_id', $venue->site_id)
            ->where('email', $email)
            ->where('status', '!=', 'archived')
            ->exists();
    }

    private function isFutureDate(?Carbon $eventDate, Carbon $referenceDate): bool
    {
        if ($eventDate === null) {
            return false;
        }

        return $eventDate->greaterThanOrEqualTo($referenceDate->copy()->startOfDay());
    }

    /**
     * Index every referral-enabled venue across all tenants. This runs from a
     * scheduled command, where there is no web request to resolve a tenant, so
     * the global `site` scope would otherwise pin the lookup to the default
     * site and silently hide venues configured under any other brand-site.
     *
     * @return Collection<string, Venue>
     */
    private function venuesIndexedByReferralEmail(): Collection
    {
        $index = collect();

        Venue::withoutSiteScope()
            ->whereNotNull('referral_emails')
            ->get()
            ->each(function (Venue $venue) use ($index): void {
                foreach ((array) $venue->referral_emails as $email) {
                    if (! is_string($email) || $email === '') {
                        continue;
                    }

                    $index->put(strtolower($email), $venue);
                }
            });

        return $index;
    }

    /**
     * Gated venues catch all of their recent mail (no subject restriction) so
     * confirmed-booking notifications are ingested alongside "Are You
     * Available?" broadcasts. Other venues stay scoped to New-Client referrals.
     *
     * @param  Collection<string, Venue>  $venuesByEmail
     */
    private function buildQuery(Collection $venuesByEmail, int $withinDays): string
    {
        $newClientEmails = [];
        $broadEmails = [];

        foreach ($venuesByEmail as $email => $venue) {
            if ($venue->requiresReferralApproval()) {
                $broadEmails[] = $email;
            } else {
                $newClientEmails[] = $email;
            }
        }

        $groups = [];

        if ($newClientEmails !== []) {
            $froms = implode(' OR ', array_map(fn (string $e) => "from:{$e}", $newClientEmails));
            $groups[] = sprintf('((%s) subject:"new client")', $froms);
        }

        if ($broadEmails !== []) {
            $froms = implode(' OR ', array_map(fn (string $e) => "from:{$e}", $broadEmails));
            $groups[] = sprintf('(%s)', $froms);
        }

        return sprintf(
            '(%s) newer_than:%dd -in:sent',
            implode(' OR ', $groups),
            max(1, $withinDays),
        );
    }

    /**
     * Cheap pre-filter for the broad gated catch: only spend an extraction call
     * on venue mail that actually carries a contact email address.
     */
    private function bodyHasContactDetails(string $body): bool
    {
        return preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $body) === 1;
    }

    /**
     * A usable referral has at least a named couple and one way to reach them.
     */
    private function looksLikeReferral(?ExtractedReferral $referral): bool
    {
        return $referral !== null
            && $referral->primaryName() !== ''
            && ($referral->primaryEmail !== null || $referral->phone !== null);
    }

    private function buildInquiryNote(ParsedGmailMessage $message, ?ExtractedReferral $referral): string
    {
        $note = "Auto-imported from venue referral email.\n\n";
        $note .= 'Subject: '.$message->subject."\n";
        $note .= 'From: '.($message->fromName ? $message->fromName.' <'.$message->fromEmail.'>' : $message->fromEmail)."\n";

        if ($referral !== null) {
            $note .= sprintf("Extraction confidence: %.2f\n", $referral->confidence);
        } else {
            $note .= "Extraction failed — review the original email.\n";
        }

        return $note;
    }
}

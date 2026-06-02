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

        $hasGatedVenue = $venuesByEmail->contains(fn (Venue $venue) => $venue->requiresReferralApproval());
        $query = $this->buildQuery($venuesByEmail->keys()->all(), $withinDays, $hasGatedVenue);
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

            if (! $this->subjectMatchesVenue($message->subject, $venue)) {
                continue;
            }

            $this->process($message, $venue, $stats);
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
    private function process(ParsedGmailMessage $message, Venue $venue, array &$stats): void
    {
        $referral = $this->extractor->extract($message->subject, $message->bodyPlain);

        $autoSendEligible = $referral !== null
            && $referral->isComplete()
            && $referral->confidence >= self::CONFIDENCE_THRESHOLD
            && $this->isFutureDate($referral->eventDate, $message->sentAt)
            && ! $this->emailAlreadyTrackedAsClient($referral->primaryEmail);

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

        return Inquiry::create([
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

    private function emailAlreadyTrackedAsClient(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        return Inquiry::query()
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
     * @return Collection<string, Venue>
     */
    private function venuesIndexedByReferralEmail(): Collection
    {
        $index = collect();

        Venue::query()
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
     * @param  array<int, string>  $emails
     */
    private function buildQuery(array $emails, int $withinDays, bool $includeAvailability): string
    {
        $froms = array_map(fn (string $e) => "from:{$e}", $emails);

        $subjects = ['subject:"new client"'];

        if ($includeAvailability) {
            $subjects[] = 'subject:available';
        }

        return sprintf(
            '(%s) (%s) newer_than:%dd -in:sent',
            implode(' OR ', $froms),
            implode(' OR ', $subjects),
            max(1, $withinDays),
        );
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

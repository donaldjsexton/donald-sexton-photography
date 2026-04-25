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

    private const SUBJECT_REGEX = '/^(?:Fwd:\s*)?New Clients?(?:\s+Info)?[\s\-:]/i';

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

        $query = $this->buildQuery($venuesByEmail->keys()->all(), $withinDays);
        $messages = $this->reader->searchMessages($query, 50);

        foreach ($messages as $message) {
            $stats['checked']++;

            if ($this->alreadyProcessed($message)) {
                continue;
            }

            if (! preg_match(self::SUBJECT_REGEX, $message->subject)) {
                continue;
            }

            $venue = $venuesByEmail->get($message->fromEmail);

            if ($venue === null) {
                continue;
            }

            $this->process($message, $venue, $stats);
        }

        return $stats;
    }

    /**
     * @param  array{checked: int, created: int, auto_sent: int, queued_for_review: int}  $stats
     */
    private function process(ParsedGmailMessage $message, Venue $venue, array &$stats): void
    {
        $referral = $this->extractor->extract($message->subject, $message->bodyPlain);

        $shouldAutoSend = $referral !== null
            && $referral->isComplete()
            && $referral->confidence >= self::CONFIDENCE_THRESHOLD
            && $this->isFutureDate($referral->eventDate, $message->sentAt)
            && ! $this->emailAlreadyTrackedAsClient($referral->primaryEmail);

        $inquiry = $this->createInquiry($message, $venue, $referral, $shouldAutoSend);
        $stats['created']++;

        $this->attachInboundMessage($inquiry, $message);

        if ($shouldAutoSend) {
            $this->sendIntro($inquiry, $venue);
            $stats['auto_sent']++;
        } else {
            $this->notifyForReview($inquiry, $referral);
            $stats['queued_for_review']++;
        }
    }

    private function createInquiry(
        ParsedGmailMessage $message,
        Venue $venue,
        ?ExtractedReferral $referral,
        bool $autoSent,
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
            'source' => $autoSent ? self::SOURCE_AUTO : self::SOURCE_PENDING,
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

    private function notifyForReview(Inquiry $inquiry, ?ExtractedReferral $referral): void
    {
        try {
            app(WebPushService::class)->notify(
                'Venue referral needs review',
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
    private function buildQuery(array $emails, int $withinDays): string
    {
        $froms = array_map(fn (string $e) => "from:{$e}", $emails);

        return sprintf(
            '(%s) subject:"new client" newer_than:%dd -in:sent',
            implode(' OR ', $froms),
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

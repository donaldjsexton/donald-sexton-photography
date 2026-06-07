<?php

namespace Tests\Feature;

use App\Mail\VenueReferralIntro;
use App\Models\Inquiry;
use App\Models\Site;
use App\Models\Venue;
use App\Services\Gmail\GmailReader;
use App\Services\Gmail\ParsedGmailMessage;
use App\Services\VenueReferral\ExtractedReferral;
use App\Services\VenueReferral\VenueReferralExtractor;
use App\Services\VenueReferral\VenueReferralIngestor;
use App\Tenancy\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VenueReferralIngestorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow('2026-04-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_high_confidence_email_creates_inquiry_and_auto_sends_intro(): void
    {
        $venue = $this->seedKnottedRoots();

        $reader = $this->fakeReader([
            $this->referralMessage('m1', 'New Client-Clancy Genirs 3.20.27'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Stephanie Clancy', 'Scott Genirs'],
            eventDate: Carbon::parse('2027-03-20'),
            primaryEmail: 'clancy_stephanie@yahoo.com',
            secondaryEmail: null,
            phone: '727-495-9460',
            confidence: 0.95,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        $this->assertSame(1, $stats['created']);
        $this->assertSame(1, $stats['auto_sent']);
        $this->assertSame(0, $stats['queued_for_review']);

        $inquiry = Inquiry::firstWhere('email', 'clancy_stephanie@yahoo.com');
        $this->assertNotNull($inquiry);
        $this->assertSame('Stephanie Clancy', $inquiry->primary_name);
        $this->assertSame('Scott Genirs', $inquiry->partner_name);
        $this->assertSame('727-495-9460', $inquiry->phone);
        $this->assertSame('2027-03-20', $inquiry->event_date->format('Y-m-d'));
        $this->assertSame($venue->id, $inquiry->venue_id);
        $this->assertSame(VenueReferralIngestor::SOURCE_AUTO, $inquiry->source);
        $this->assertNotNull($inquiry->first_responded_at);

        Mail::assertSent(VenueReferralIntro::class, fn (VenueReferralIntro $mail) => $mail->hasTo('clancy_stephanie@yahoo.com'));
    }

    public function test_two_email_referral_ccs_secondary_address(): void
    {
        $this->seedKnottedRoots();
        Carbon::setTestNow('2026-01-17 12:00:00');

        $reader = $this->fakeReader([
            $this->referralMessage('m2', 'Fwd: New Client-Kell Matos 3.16.26'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Cindy Kell', 'Josue Matos'],
            eventDate: Carbon::parse('2026-03-16'),
            primaryEmail: 'clk2007now@yahoo.com',
            secondaryEmail: 'matos787@gmail.com',
            phone: '813-695-0635',
            confidence: 0.92,
        ));

        (new VenueReferralIngestor($reader, $extractor))->ingest();

        $inquiry = Inquiry::firstWhere('email', 'clk2007now@yahoo.com');
        $this->assertNotNull($inquiry);
        $this->assertSame('matos787@gmail.com', $inquiry->email_secondary);

        Mail::assertSent(VenueReferralIntro::class, fn (VenueReferralIntro $mail) => $mail->hasTo('clk2007now@yahoo.com') && $mail->hasCc('matos787@gmail.com'));
    }

    public function test_low_confidence_extraction_routes_to_review_without_sending(): void
    {
        $this->seedKnottedRoots();

        $reader = $this->fakeReader([
            $this->referralMessage('m3', 'New Clients- 10.31.26 Therrien Piazza'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Catherine Therrien'],
            eventDate: Carbon::parse('2026-10-31'),
            primaryEmail: 'cathy.therrien@yahoo.com',
            secondaryEmail: null,
            phone: null,
            confidence: 0.62,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        $this->assertSame(1, $stats['created']);
        $this->assertSame(0, $stats['auto_sent']);
        $this->assertSame(1, $stats['queued_for_review']);

        $inquiry = Inquiry::first();
        $this->assertSame(VenueReferralIngestor::SOURCE_PENDING, $inquiry->source);
        $this->assertNull($inquiry->first_responded_at);

        Mail::assertNothingSent();
    }

    public function test_dedupes_via_gmail_message_id_on_repeat_runs(): void
    {
        $this->seedKnottedRoots();

        $reader = $this->fakeReader([
            $this->referralMessage('m4', 'New Client-Taylor Loan 1/10/27'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['James Taylor', 'Ashley Loan'],
            eventDate: Carbon::parse('2027-01-10'),
            primaryEmail: 'jataylor2012@gmail.com',
            secondaryEmail: 'ashleylillianloan@gmail.com',
            phone: '813-454-2758',
            confidence: 0.95,
        ));

        $ingestor = new VenueReferralIngestor($reader, $extractor);
        $ingestor->ingest();
        $second = $ingestor->ingest();

        $this->assertSame(1, $second['checked']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseCount('inquiries', 1);
    }

    public function test_skips_message_without_matching_subject(): void
    {
        $this->seedKnottedRoots();

        $reader = $this->fakeReader([
            $this->referralMessage('mx', 'Random message about something else'),
        ]);

        $stats = (new VenueReferralIngestor($reader, $this->stubExtractor(null)))->ingest();

        $this->assertSame(0, $stats['created']);
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_past_event_date_routes_to_review(): void
    {
        $this->seedKnottedRoots();

        $reader = $this->fakeReader([
            $this->referralMessage('mp', 'New Client-Old Couple 1.1.20'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Old Couple'],
            eventDate: Carbon::parse('2020-01-01'),
            primaryEmail: 'old@example.com',
            secondaryEmail: null,
            phone: '555-1234',
            confidence: 0.95,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        $this->assertSame(1, $stats['created']);
        $this->assertSame(0, $stats['auto_sent']);
        $this->assertSame(1, $stats['queued_for_review']);
        Mail::assertNothingSent();
    }

    public function test_skips_auto_send_when_couple_email_already_has_open_inquiry(): void
    {
        $this->seedKnottedRoots();

        Inquiry::factory()->create([
            'email' => 'duplicate@example.com',
            'status' => 'active',
        ]);

        $reader = $this->fakeReader([
            $this->referralMessage('md', 'New Client-Existing Couple 5.5.27'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Existing Couple'],
            eventDate: Carbon::parse('2027-05-05'),
            primaryEmail: 'duplicate@example.com',
            secondaryEmail: null,
            phone: '555-9999',
            confidence: 0.95,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        $this->assertSame(1, $stats['created']);
        $this->assertSame(0, $stats['auto_sent']);
        Mail::assertNothingSent();
    }

    public function test_noop_when_reader_unavailable(): void
    {
        $this->seedKnottedRoots();

        $stats = (new VenueReferralIngestor($this->fakeReader([], available: false), $this->stubExtractor(null)))->ingest();

        $this->assertSame(['checked' => 0, 'created' => 0, 'auto_sent' => 0, 'queued_for_review' => 0], $stats);
    }

    public function test_gated_venue_holds_high_confidence_availability_request(): void
    {
        $venue = $this->seedGulfBeachWeddings();

        $reader = $this->fakeReader([
            $this->referralMessage('g1', 'Are You Available? (3hrs)', 'info@gulfbeachweddings.com'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Eric Ellingsberg', 'Tessa Achman'],
            eventDate: Carbon::parse('2027-02-20'),
            primaryEmail: 'ericellingsberg@gmail.com',
            secondaryEmail: null,
            phone: '5072761553',
            confidence: 0.97,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        $this->assertSame(1, $stats['created']);
        $this->assertSame(0, $stats['auto_sent']);
        $this->assertSame(1, $stats['queued_for_review']);

        $inquiry = Inquiry::firstWhere('email', 'ericellingsberg@gmail.com');
        $this->assertNotNull($inquiry);
        $this->assertSame($venue->id, $inquiry->venue_id);
        $this->assertSame(VenueReferralIngestor::SOURCE_GATED, $inquiry->source);
        $this->assertNull($inquiry->first_responded_at);

        Mail::assertNothingSent();
    }

    public function test_non_gated_venue_ignores_availability_subject(): void
    {
        $this->seedKnottedRoots();

        $reader = $this->fakeReader([
            $this->referralMessage('a1', 'Are You Available?'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Someone Here'],
            eventDate: Carbon::parse('2027-05-05'),
            primaryEmail: 'someone@example.com',
            secondaryEmail: null,
            phone: '555-0000',
            confidence: 0.95,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        $this->assertSame(0, $stats['created']);
        $this->assertDatabaseCount('inquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_gated_venue_still_ingests_new_client_subject(): void
    {
        $this->seedGulfBeachWeddings();

        $reader = $this->fakeReader([
            $this->referralMessage('g2', 'New Client-Davis Zacchero 8.21.26', 'info@gulfbeachweddings.com'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Damon Davis', 'Sara Zacchero'],
            eventDate: Carbon::parse('2026-08-21'),
            primaryEmail: 'td6063@gmail.com',
            secondaryEmail: null,
            phone: '4126575212',
            confidence: 0.97,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        // Even a complete, high-confidence "New Client" email is gated for
        // this venue — nothing goes out until Donald approves.
        $this->assertSame(1, $stats['created']);
        $this->assertSame(0, $stats['auto_sent']);
        $this->assertSame(1, $stats['queued_for_review']);

        $inquiry = Inquiry::firstWhere('email', 'td6063@gmail.com');
        $this->assertSame(VenueReferralIngestor::SOURCE_GATED, $inquiry->source);
        Mail::assertNothingSent();
    }

    public function test_gated_venue_ingests_confirmed_booking_with_non_matching_subject(): void
    {
        $venue = $this->seedGulfBeachWeddings();

        // The confirmed-booking notification carries the couple's real contact
        // details but its subject is neither "New Client" nor "available".
        $body = "6/24/26 @ 7:30pm / Sunset Beach (77th Ave) / Simply Beautiful w/ Photo\n"
            ."Name: Shannon MacLeod & Alexander McKenna\n"
            ."Email: mckennawedding2025@gmail.com\n"
            .'Phone #: 5134070779';

        $reader = $this->fakeReader([
            $this->referralMessage('b1', '6/24/26 @ 7:30pm / Sunset Beach', 'info@gulfbeachweddings.com', $body),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Shannon MacLeod', 'Alexander McKenna'],
            eventDate: Carbon::parse('2026-06-24'),
            primaryEmail: 'mckennawedding2025@gmail.com',
            secondaryEmail: null,
            phone: '5134070779',
            confidence: 0.96,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        $this->assertSame(1, $stats['created']);
        $this->assertSame(0, $stats['auto_sent']);
        $this->assertSame(1, $stats['queued_for_review']);

        $inquiry = Inquiry::firstWhere('email', 'mckennawedding2025@gmail.com');
        $this->assertNotNull($inquiry);
        $this->assertSame($venue->id, $inquiry->venue_id);
        $this->assertSame('Shannon MacLeod', $inquiry->primary_name);
        $this->assertSame(VenueReferralIngestor::SOURCE_GATED, $inquiry->source);
        $this->assertNull($inquiry->first_responded_at);

        Mail::assertNothingSent();
    }

    public function test_gated_venue_ignores_non_referral_mail_with_unmatched_subject(): void
    {
        $this->seedGulfBeachWeddings();

        // A newsletter from the venue: contact details present, but nothing the
        // extractor can turn into a couple referral.
        $reader = $this->fakeReader([
            $this->referralMessage('n1', 'June Newsletter', 'info@gulfbeachweddings.com', 'Reply to info@gulfbeachweddings.com to unsubscribe.'),
        ]);

        $stats = (new VenueReferralIngestor($reader, $this->stubExtractor(null)))->ingest();

        $this->assertSame(0, $stats['created']);
        $this->assertDatabaseCount('inquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_query_catches_all_gated_mail_but_restricts_other_venues(): void
    {
        $this->seedKnottedRoots();
        $this->seedGulfBeachWeddings();

        $reader = $this->recordingReader();

        (new VenueReferralIngestor($reader, $this->stubExtractor(null)))->ingest();

        $query = $reader->lastQuery;

        // Non-gated venue stays scoped to New-Client referrals.
        $this->assertStringContainsString('(from:krlakeevents@gmail.com) subject:"new client"', $query);
        // Gated venue is fetched with no subject restriction.
        $this->assertStringContainsString('(from:info@gulfbeachweddings.com)', $query);
        $this->assertStringNotContainsString('subject:available', $query);
        $this->assertStringContainsString('-in:sent', $query);
    }

    public function test_ingests_referral_for_a_venue_on_a_non_default_site(): void
    {
        // The scheduled command runs without a resolved tenant, so CurrentSite
        // falls back to the default site. A venue configured under a different
        // brand-site must still be scanned and filed under its own tenant.
        $otherSite = Site::factory()->create();
        $venue = $this->seedGulfBeachWeddings($otherSite);

        $reader = $this->fakeReader([
            $this->referralMessage('gn', 'Are You Available? (3hrs)', 'info@gulfbeachweddings.com'),
        ]);

        $extractor = $this->stubExtractor(new ExtractedReferral(
            coupleNames: ['Eric Ellingsberg', 'Tessa Achman'],
            eventDate: Carbon::parse('2027-02-20'),
            primaryEmail: 'ericellingsberg@gmail.com',
            secondaryEmail: null,
            phone: '5072761553',
            confidence: 0.97,
        ));

        $stats = (new VenueReferralIngestor($reader, $extractor))->ingest();

        $this->assertSame(1, $stats['created']);

        $inquiry = Inquiry::withoutSiteScope()->firstWhere('email', 'ericellingsberg@gmail.com');
        $this->assertNotNull($inquiry);
        $this->assertSame($otherSite->id, $inquiry->site_id);
        $this->assertSame($venue->id, $inquiry->venue_id);
        $this->assertSame(VenueReferralIngestor::SOURCE_GATED, $inquiry->source);
    }

    public function test_noop_when_no_venues_have_referral_emails(): void
    {
        $reader = $this->fakeReader([
            $this->referralMessage('m1', 'New Client-Foo Bar 1/1/27'),
        ]);

        $stats = (new VenueReferralIngestor($reader, $this->stubExtractor(null)))->ingest();

        $this->assertSame(0, $stats['checked']);
    }

    private function seedKnottedRoots(): Venue
    {
        return Venue::factory()->create([
            'name' => 'Knotted Roots on the Lake',
            'slug' => 'knotted-roots-on-the-lake',
            'website_url' => 'https://knottedrootsonthelake.com',
            'referral_emails' => ['krlakeevents@gmail.com'],
            'referral_contact_name' => 'Tara Hardin',
        ]);
    }

    private function seedGulfBeachWeddings(?Site $site = null): Venue
    {
        $attributes = [
            'name' => 'Gulf Beach Weddings',
            'slug' => 'gulf-beach-weddings',
            'website_url' => 'https://gulfbeachweddings.com',
            'referral_emails' => ['info@gulfbeachweddings.com'],
            'referral_contact_name' => null,
            'referral_requires_approval' => true,
        ];

        if ($site === null) {
            return Venue::factory()->create($attributes);
        }

        // site_id is managed by BelongsToSite (not mass-assignable), so stamp it
        // by resolving the tenant to the target site while the venue is created.
        $currentSite = app(CurrentSite::class);
        $previous = $currentSite->get();
        $currentSite->set($site);

        try {
            return Venue::factory()->create($attributes);
        } finally {
            $currentSite->set($previous);
        }
    }

    /**
     * @param  array<int, ParsedGmailMessage>  $messages
     */
    private function fakeReader(array $messages, bool $available = true): GmailReader
    {
        return new class($messages, $available) implements GmailReader
        {
            /** @param array<int, ParsedGmailMessage> $messages */
            public function __construct(private array $messages, private bool $available) {}

            public function isAvailable(): bool
            {
                return $this->available;
            }

            public function connectedEmail(): ?string
            {
                return 'donald@donaldsextonphotography.com';
            }

            public function findThreadIdsForEmail(string $email, int $withinDays, int $maxThreads = 25): array
            {
                return [];
            }

            public function fetchThreadMessages(string $threadId): array
            {
                return [];
            }

            public function searchMessages(string $query, int $maxResults = 25): array
            {
                return $this->messages;
            }
        };
    }

    private function recordingReader(): GmailReader
    {
        return new class implements GmailReader
        {
            public string $lastQuery = '';

            public function isAvailable(): bool
            {
                return true;
            }

            public function connectedEmail(): ?string
            {
                return 'donald@donaldsextonphotography.com';
            }

            public function findThreadIdsForEmail(string $email, int $withinDays, int $maxThreads = 25): array
            {
                return [];
            }

            public function fetchThreadMessages(string $threadId): array
            {
                return [];
            }

            public function searchMessages(string $query, int $maxResults = 25): array
            {
                $this->lastQuery = $query;

                return [];
            }
        };
    }

    private function stubExtractor(?ExtractedReferral $referral): VenueReferralExtractor
    {
        return new class($referral) extends VenueReferralExtractor
        {
            public function __construct(private ?ExtractedReferral $referral)
            {
                // Bypass parent — no HTTP dependency in stub.
            }

            public function extract(string $subject, string $body): ?ExtractedReferral
            {
                return $this->referral;
            }
        };
    }

    private function referralMessage(string $id, string $subject, string $fromEmail = 'krlakeevents@gmail.com', ?string $body = null): ParsedGmailMessage
    {
        return new ParsedGmailMessage(
            id: $id,
            threadId: 'thr_'.$id,
            fromEmail: $fromEmail,
            fromName: 'Referral Source',
            subject: $subject,
            bodyPlain: $body ?? 'Body for '.$subject,
            sentAt: Carbon::now(),
            hasAttachments: false,
        );
    }
}

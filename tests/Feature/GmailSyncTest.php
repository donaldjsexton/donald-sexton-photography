<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Services\Gmail\GmailReader;
use App\Services\Gmail\GmailSyncService;
use App\Services\Gmail\ParsedGmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GmailSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_links_matching_thread_and_imports_messages(): void
    {
        $inquiry = Inquiry::factory()->create([
            'email' => 'client@example.com',
            'status' => 'new',
            'first_responded_at' => null,
        ]);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            threadIdByEmail: ['client@example.com' => 'thr_1'],
            messagesByThread: [
                'thr_1' => [
                    new ParsedGmailMessage('m1', 'thr_1', 'client@example.com', 'Jane Client', 'Hi', 'Interested in wedding photos.', Carbon::parse('2026-04-10 10:00:00'), false),
                    new ParsedGmailMessage('m2', 'thr_1', 'donald@example.com', 'Donald Sexton', 'Re: Hi', 'Thanks for reaching out!', Carbon::parse('2026-04-10 14:00:00'), false),
                ],
            ],
        );

        $result = (new GmailSyncService($reader))->sync(90);

        $this->assertSame(['checked' => 1, 'linked' => 1, 'new_messages' => 2], $result);

        $inquiry->refresh();
        $this->assertSame('thr_1', $inquiry->gmail_thread_id);
        $this->assertSame('active', $inquiry->status);
        $this->assertNotNull($inquiry->first_responded_at);
        $this->assertSame('2026-04-10 14:00:00', $inquiry->first_responded_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm1',
            'gmail_thread_id' => 'thr_1',
            'subject' => 'Hi',
            'direction' => 'inbound',
            'sender_email' => 'client@example.com',
        ]);
        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm2',
            'gmail_thread_id' => 'thr_1',
            'direction' => 'outbound',
            'sender_email' => 'donald@example.com',
        ]);
    }

    public function test_sync_imports_messages_from_multiple_threads_per_inquiry(): void
    {
        $inquiry = Inquiry::factory()->create([
            'email' => 'client@example.com',
            'status' => 'new',
            'first_responded_at' => null,
        ]);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            threadIdByEmail: ['client@example.com' => ['thr_recent', 'thr_old']],
            messagesByThread: [
                'thr_recent' => [
                    new ParsedGmailMessage('m_new_in', 'thr_recent', 'client@example.com', 'Jane Client', 'Engagement session pricing', 'Need pricing.', Carbon::parse('2026-04-20 09:00:00'), false),
                    new ParsedGmailMessage('m_new_out', 'thr_recent', 'donald@example.com', 'Donald Sexton', 'Re: Engagement session pricing', 'Here you go.', Carbon::parse('2026-04-20 11:00:00'), false),
                ],
                'thr_old' => [
                    new ParsedGmailMessage('m_old_in', 'thr_old', 'client@example.com', 'Jane Client', 'Wedding inquiry', 'Original ask.', Carbon::parse('2026-03-01 09:00:00'), false),
                ],
            ],
        );

        $result = (new GmailSyncService($reader))->sync(90);

        $this->assertSame(3, $result['new_messages']);
        $this->assertSame(1, $result['linked']);

        $inquiry->refresh();
        $this->assertSame('thr_recent', $inquiry->gmail_thread_id);

        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm_new_in',
            'gmail_thread_id' => 'thr_recent',
            'subject' => 'Engagement session pricing',
        ]);
        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm_old_in',
            'gmail_thread_id' => 'thr_old',
            'subject' => 'Wedding inquiry',
        ]);

        $threadIds = $inquiry->messages()->pluck('gmail_thread_id')->unique()->sort()->values()->all();
        $this->assertSame(['thr_old', 'thr_recent'], $threadIds);
    }

    public function test_sync_picks_up_a_new_thread_for_already_linked_inquiry(): void
    {
        $inquiry = Inquiry::factory()->create([
            'email' => 'client@example.com',
            'gmail_thread_id' => 'thr_old',
        ]);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            threadIdByEmail: ['client@example.com' => ['thr_new', 'thr_old']],
            messagesByThread: [
                'thr_old' => [
                    new ParsedGmailMessage('m_old', 'thr_old', 'client@example.com', null, 'Old', 'Body', Carbon::parse('2026-03-01 09:00:00'), false),
                ],
                'thr_new' => [
                    new ParsedGmailMessage('m_new', 'thr_new', 'client@example.com', null, 'Follow up', 'Body', Carbon::parse('2026-04-01 09:00:00'), false),
                ],
            ],
        );

        (new GmailSyncService($reader))->sync(90);

        $inquiry->refresh();
        $this->assertSame('thr_old', $inquiry->gmail_thread_id, 'Existing primary thread is preserved.');

        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm_new',
            'gmail_thread_id' => 'thr_new',
        ]);
        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm_old',
            'gmail_thread_id' => 'thr_old',
        ]);
    }

    public function test_sync_is_idempotent(): void
    {
        $inquiry = Inquiry::factory()->create(['email' => 'client@example.com']);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            threadIdByEmail: ['client@example.com' => 'thr_1'],
            messagesByThread: [
                'thr_1' => [
                    new ParsedGmailMessage('m1', 'thr_1', 'client@example.com', null, 'Hi', 'Hello', Carbon::parse('2026-04-10 10:00:00'), false),
                ],
            ],
        );

        $service = new GmailSyncService($reader);
        $service->sync();
        $second = $service->sync();

        $this->assertSame(0, $second['new_messages']);
        $this->assertDatabaseCount('inquiry_messages', 1);
    }

    public function test_sync_skips_inquiries_with_no_matching_thread(): void
    {
        Inquiry::factory()->create(['email' => 'nobody@example.com']);

        $reader = new FakeGmailReader('donald@example.com');

        $result = (new GmailSyncService($reader))->sync();

        $this->assertSame(0, $result['linked']);
        $this->assertDatabaseCount('inquiry_messages', 0);
    }

    public function test_sync_skips_archived_inquiries(): void
    {
        Inquiry::factory()->create([
            'email' => 'client@example.com',
            'status' => 'archived',
        ]);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            threadIdByEmail: ['client@example.com' => 'thr_1'],
            messagesByThread: [
                'thr_1' => [
                    new ParsedGmailMessage('m1', 'thr_1', 'client@example.com', null, 'Hi', 'Hello', Carbon::parse('2026-04-10 10:00:00'), false),
                ],
            ],
        );

        $result = (new GmailSyncService($reader))->sync();

        $this->assertSame(0, $result['checked']);
    }

    public function test_sync_flags_attachments_in_body(): void
    {
        $inquiry = Inquiry::factory()->create(['email' => 'client@example.com']);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            threadIdByEmail: ['client@example.com' => 'thr_1'],
            messagesByThread: [
                'thr_1' => [
                    new ParsedGmailMessage('m1', 'thr_1', 'client@example.com', null, 'Hi', 'See photo.', Carbon::parse('2026-04-10 10:00:00'), true),
                ],
            ],
        );

        (new GmailSyncService($reader))->sync();

        $message = $inquiry->messages()->first();
        $this->assertStringContainsString('Gmail attachments present', $message->body);
    }

    public function test_sync_noop_when_reader_unavailable(): void
    {
        Inquiry::factory()->create(['email' => 'client@example.com']);

        $reader = new FakeGmailReader('donald@example.com', available: false);

        $result = (new GmailSyncService($reader))->sync();

        $this->assertSame(['checked' => 0, 'linked' => 0, 'new_messages' => 0], $result);
    }

    public function test_sync_claims_locally_created_outbound_message_instead_of_duplicating(): void
    {
        $inquiry = Inquiry::factory()->create([
            'email' => 'client@example.com',
            'gmail_thread_id' => 'thr_1',
        ]);

        $sentAt = Carbon::parse('2026-04-10 14:00:00');

        $local = $inquiry->messages()->create([
            'direction' => 'outbound',
            'body' => 'Thanks for reaching out!',
            'sender_name' => 'Donald Sexton',
            'sender_email' => 'donald@example.com',
            'sent_at' => $sentAt->copy()->subSeconds(30),
        ]);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            messagesByThread: [
                'thr_1' => [
                    new ParsedGmailMessage('m_outbound', 'thr_1', 'donald@example.com', 'Donald Sexton', 'Re: Hi', 'Thanks for reaching out!', $sentAt, false),
                ],
            ],
        );

        $result = (new GmailSyncService($reader))->sync();

        $this->assertSame(0, $result['new_messages']);
        $this->assertDatabaseCount('inquiry_messages', 1);

        $local->refresh();
        $this->assertSame('m_outbound', $local->gmail_message_id);
        $this->assertSame($sentAt->format('Y-m-d H:i:s'), $local->sent_at->format('Y-m-d H:i:s'));
    }

    public function test_sync_does_not_claim_outbound_message_outside_time_window(): void
    {
        $inquiry = Inquiry::factory()->create([
            'email' => 'client@example.com',
            'gmail_thread_id' => 'thr_1',
        ]);

        $inquiry->messages()->create([
            'direction' => 'outbound',
            'body' => 'An older note.',
            'sender_name' => 'Donald Sexton',
            'sender_email' => 'donald@example.com',
            'sent_at' => Carbon::parse('2026-04-10 10:00:00'),
        ]);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            messagesByThread: [
                'thr_1' => [
                    new ParsedGmailMessage('m_outbound', 'thr_1', 'donald@example.com', 'Donald Sexton', 'Re: Hi', 'Newer reply body.', Carbon::parse('2026-04-10 14:00:00'), false),
                ],
            ],
        );

        (new GmailSyncService($reader))->sync();

        $this->assertDatabaseCount('inquiry_messages', 2);
        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm_outbound',
        ]);
    }

    public function test_sync_does_not_claim_inbound_message_with_local_outbound(): void
    {
        $inquiry = Inquiry::factory()->create([
            'email' => 'client@example.com',
            'gmail_thread_id' => 'thr_1',
        ]);

        $sentAt = Carbon::parse('2026-04-10 14:00:00');

        $inquiry->messages()->create([
            'direction' => 'outbound',
            'body' => 'Pending outbound.',
            'sender_name' => 'Donald Sexton',
            'sender_email' => 'donald@example.com',
            'sent_at' => $sentAt->copy()->subSeconds(30),
        ]);

        $reader = new FakeGmailReader(
            connectedEmail: 'donald@example.com',
            messagesByThread: [
                'thr_1' => [
                    new ParsedGmailMessage('m_inbound', 'thr_1', 'client@example.com', 'Jane Client', 'Hi', 'Inbound from client.', $sentAt, false),
                ],
            ],
        );

        (new GmailSyncService($reader))->sync();

        $this->assertDatabaseCount('inquiry_messages', 2);
        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm_inbound',
            'direction' => 'inbound',
        ]);
    }
}

class FakeGmailReader implements GmailReader
{
    /** @var array<string, array<int, string>> */
    private readonly array $threadIdsByEmail;

    /**
     * @param  array<string, string|array<int, string>>  $threadIdByEmail
     * @param  array<string, array<int, ParsedGmailMessage>>  $messagesByThread
     */
    public function __construct(
        private readonly string $connectedEmail,
        array $threadIdByEmail = [],
        private readonly array $messagesByThread = [],
        private readonly bool $available = true,
    ) {
        $normalised = [];

        foreach ($threadIdByEmail as $email => $value) {
            $normalised[$email] = is_array($value) ? array_values($value) : [$value];
        }

        $this->threadIdsByEmail = $normalised;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function connectedEmail(): ?string
    {
        return $this->connectedEmail;
    }

    public function findThreadIdsForEmail(string $email, int $withinDays, int $maxThreads = 25): array
    {
        return $this->threadIdsByEmail[$email] ?? [];
    }

    public function fetchThreadMessages(string $threadId): array
    {
        return $this->messagesByThread[$threadId] ?? [];
    }

    public function searchMessages(string $query, int $maxResults = 25): array
    {
        return [];
    }
}

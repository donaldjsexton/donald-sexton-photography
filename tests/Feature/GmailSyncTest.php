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
            'direction' => 'inbound',
            'sender_email' => 'client@example.com',
        ]);
        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'gmail_message_id' => 'm2',
            'direction' => 'outbound',
            'sender_email' => 'donald@example.com',
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
}

class FakeGmailReader implements GmailReader
{
    /**
     * @param  array<string, string>  $threadIdByEmail
     * @param  array<string, array<int, ParsedGmailMessage>>  $messagesByThread
     */
    public function __construct(
        private readonly string $connectedEmail,
        private readonly array $threadIdByEmail = [],
        private readonly array $messagesByThread = [],
        private readonly bool $available = true,
    ) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function connectedEmail(): ?string
    {
        return $this->connectedEmail;
    }

    public function findThreadIdForEmail(string $email, int $withinDays): ?string
    {
        return $this->threadIdByEmail[$email] ?? null;
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

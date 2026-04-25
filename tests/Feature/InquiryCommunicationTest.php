<?php

namespace Tests\Feature;

use App\Mail\InquiryAcknowledgment;
use App\Mail\InquiryReply;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InquiryCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inquiry_submission_sends_acknowledgment_to_client(): void
    {
        Mail::fake();

        $this->post(route('inquiry.store'), [
            'primary_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'event_type' => 'wedding',
        ]);

        Mail::assertSent(InquiryAcknowledgment::class, function ($mail) {
            return $mail->hasTo('jane@example.com')
                && $mail->inquiry->primary_name === 'Jane Doe';
        });
    }

    public function test_inquiry_submission_with_honeypot_filled_is_silently_dropped(): void
    {
        Mail::fake();

        $response = $this->post(route('inquiry.store'), [
            'primary_name' => 'Bot McBotface',
            'email' => 'bot@example.com',
            'event_type' => 'wedding',
            'website' => 'https://spam.example',
        ]);

        $response->assertRedirect(route('inquiry.thank-you'));

        $this->assertDatabaseMissing('inquiries', ['email' => 'bot@example.com']);
        Mail::assertNothingSent();
    }

    public function test_admin_can_reply_to_inquiry(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['email' => 'client@example.com']);

        $response = $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => 'Thanks for reaching out! I would love to chat.',
        ]);

        $response->assertRedirect(route('admin.inquiries.edit', $inquiry));
        $response->assertSessionHas('status', 'Reply sent.');

        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'direction' => 'outbound',
            'body' => 'Thanks for reaching out! I would love to chat.',
        ]);

        Mail::assertSent(InquiryReply::class, function ($mail) {
            return $mail->hasTo('client@example.com');
        });
    }

    public function test_first_reply_records_response_time_and_activates_inquiry(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['status' => 'new']);

        $this->assertNull($inquiry->first_responded_at);

        $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => 'Looking forward to working together.',
        ]);

        $inquiry->refresh();
        $this->assertNotNull($inquiry->first_responded_at);
        $this->assertSame('active', $inquiry->status);
    }

    public function test_subsequent_replies_do_not_overwrite_first_responded_at(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'status' => 'active',
            'first_responded_at' => now()->subHours(3),
        ]);

        $originalTime = $inquiry->first_responded_at->toIso8601String();

        $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => 'Following up on our chat.',
        ]);

        $inquiry->refresh();
        $this->assertSame($originalTime, $inquiry->first_responded_at->toIso8601String());
    }

    public function test_reply_does_not_downgrade_status_from_follow_up(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->followUp()->create([
            'first_responded_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => 'Checking back in.',
        ]);

        $inquiry->refresh();
        $this->assertSame('follow_up', $inquiry->status);
    }

    public function test_reply_requires_body(): void
    {
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_admin_can_delete_inquiry(): void
    {
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();
        $inquiry->messages()->create([
            'direction' => 'inbound',
            'body' => 'Spam content',
            'sender_name' => 'Spammer',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route('admin.inquiries.destroy', $inquiry));

        $response->assertRedirect(route('admin.inquiries.index'));
        $this->assertDatabaseMissing('inquiries', ['id' => $inquiry->id]);
        $this->assertDatabaseMissing('inquiry_messages', ['inquiry_id' => $inquiry->id]);
    }

    public function test_edit_view_shows_message_timeline(): void
    {
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['message' => 'Interested in wedding photography.']);
        $inquiry->messages()->create([
            'direction' => 'outbound',
            'body' => 'Thanks for your interest!',
            'sender_name' => 'Donald Sexton',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.inquiries.edit', $inquiry));

        $response->assertStatus(200);
        $response->assertSee('Interested in wedding photography.');
        $response->assertSee('Thanks for your interest!');
        $response->assertSee('Reply to');
    }
}

<?php

namespace Tests\Feature;

use App\Mail\InquiryAcknowledgment;
use App\Mail\InquiryReply;
use App\Models\BookedJob;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\CalendarSyncOutcome;
use App\Services\GoogleCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery;
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

    public function test_acknowledgment_says_date_is_open_when_no_confirmed_booking_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-15'));

        $inquiry = Inquiry::factory()->create([
            'event_date' => '2027-09-11',
            'event_type' => 'wedding',
        ]);

        $rendered = (new InquiryAcknowledgment($inquiry, [
            'status' => 'available',
            'event_date' => Carbon::parse('2027-09-11'),
            'nearby_dates' => [],
        ]))->render();

        $this->assertStringContainsString('September 11, 2027 is open on my calendar', $rendered);

        Carbon::setTestNow();
    }

    public function test_acknowledgment_lists_nearby_saturdays_when_requested_date_is_booked(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-15'));

        BookedJob::factory()->create([
            'event_date' => '2027-09-11',
            'status' => 'confirmed',
        ]);

        $inquiry = Inquiry::factory()->create([
            'event_date' => '2027-09-11',
            'event_type' => 'wedding',
        ]);

        $rendered = (new InquiryAcknowledgment($inquiry, [
            'status' => 'unavailable',
            'event_date' => Carbon::parse('2027-09-11'),
            'nearby_dates' => [
                Carbon::parse('2027-09-04'),
                Carbon::parse('2027-09-18'),
            ],
        ]))->render();

        $this->assertStringContainsString('September 11, 2027 is already on the calendar', $rendered);
        $this->assertStringContainsString('Saturday, September 4, 2027', $rendered);
        $this->assertStringContainsString('Saturday, September 18, 2027', $rendered);

        Carbon::setTestNow();
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

    public function test_first_outbound_to_admin_created_lead_uses_fresh_subject_with_event_type_and_date(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'source' => 'admin',
            'event_type' => 'wedding',
            'event_date' => '2026-09-12',
        ]);

        $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => 'Welcome — would love to chat about your day.',
        ]);

        Mail::assertSent(InquiryReply::class, function (InquiryReply $mail): bool {
            return $mail->envelope()->subject === 'Donald Sexton Photography — Wedding on September 12, 2026';
        });
    }

    public function test_first_outbound_to_admin_created_lead_without_event_date_omits_date(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'source' => 'admin',
            'event_type' => 'engagement',
            'event_date' => null,
        ]);

        $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => 'Glad to connect.',
        ]);

        Mail::assertSent(InquiryReply::class, function (InquiryReply $mail): bool {
            return $mail->envelope()->subject === 'Donald Sexton Photography — Engagement inquiry';
        });
    }

    public function test_subsequent_outbound_to_admin_created_lead_uses_reply_subject(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['source' => 'admin']);
        $inquiry->messages()->create([
            'direction' => 'outbound',
            'body' => 'Earlier note.',
            'sender_name' => $user->name,
            'sender_email' => config('mail.from.address'),
            'sent_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => 'Following up.',
        ]);

        Mail::assertSent(InquiryReply::class, function (InquiryReply $mail): bool {
            return $mail->envelope()->subject === 'Re: Your inquiry — Donald Sexton Photography';
        });
    }

    public function test_first_outbound_to_form_submitted_lead_still_uses_reply_subject(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['source' => 'site_form']);

        $this->actingAs($user)->post(route('admin.inquiries.reply', $inquiry), [
            'body' => 'Thanks for reaching out!',
        ]);

        Mail::assertSent(InquiryReply::class, function (InquiryReply $mail): bool {
            return $mail->envelope()->subject === 'Re: Your inquiry — Donald Sexton Photography';
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

    public function test_marking_inquiry_booked_pushes_event_to_google_calendar(): void
    {
        $calendar = Mockery::mock(GoogleCalendar::class);
        $calendar->shouldReceive('upsertBookingEvent')
            ->once()
            ->andReturn(CalendarSyncOutcome::Synced);
        $this->app->instance(GoogleCalendar::class, $calendar);

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->active()->create(['event_date' => '2027-09-11']);

        $response = $this->actingAs($user)->put(route('admin.inquiries.update', $inquiry), [
            'status' => 'booked',
        ]);

        $response->assertRedirect(route('admin.inquiries.edit', $inquiry));
        $response->assertSessionHas('status', 'Inquiry updated and synced to Google Calendar.');
    }

    public function test_resaving_booked_inquiry_still_syncs_calendar(): void
    {
        // The earlier bug: upsert only fired on transition into booked, so
        // re-saving an already-booked inquiry never pushed updates.
        $calendar = Mockery::mock(GoogleCalendar::class);
        $calendar->shouldReceive('upsertBookingEvent')
            ->once()
            ->andReturn(CalendarSyncOutcome::Synced);
        $this->app->instance(GoogleCalendar::class, $calendar);

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => '2027-09-11',
            'calendar_event_id' => 'evt-existing',
        ]);

        $this->actingAs($user)->put(route('admin.inquiries.update', $inquiry), [
            'status' => 'booked',
        ]);
    }

    public function test_booked_save_without_event_date_warns_admin(): void
    {
        $calendar = Mockery::mock(GoogleCalendar::class);
        $calendar->shouldReceive('upsertBookingEvent')
            ->once()
            ->andReturn(CalendarSyncOutcome::MissingEventDate);
        $this->app->instance(GoogleCalendar::class, $calendar);

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->active()->create(['event_date' => null]);

        $response = $this->actingAs($user)->put(route('admin.inquiries.update', $inquiry), [
            'status' => 'booked',
        ]);

        $response->assertSessionHas('status', 'Inquiry updated. Add an event date to sync this booking to Google Calendar.');
    }

    public function test_booked_save_when_calendar_disconnected_warns_admin(): void
    {
        $calendar = Mockery::mock(GoogleCalendar::class);
        $calendar->shouldReceive('upsertBookingEvent')
            ->once()
            ->andReturn(CalendarSyncOutcome::NotConnected);
        $this->app->instance(GoogleCalendar::class, $calendar);

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->active()->create(['event_date' => '2027-09-11']);

        $response = $this->actingAs($user)->put(route('admin.inquiries.update', $inquiry), [
            'status' => 'booked',
        ]);

        $response->assertSessionHas('status', 'Inquiry updated. Connect Google Calendar to sync booked events.');
    }

    public function test_booked_save_surfaces_failure_message(): void
    {
        $calendar = Mockery::mock(GoogleCalendar::class);
        $calendar->shouldReceive('upsertBookingEvent')
            ->once()
            ->andReturn(CalendarSyncOutcome::Failed);
        $this->app->instance(GoogleCalendar::class, $calendar);

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->active()->create(['event_date' => '2027-09-11']);

        $response = $this->actingAs($user)->put(route('admin.inquiries.update', $inquiry), [
            'status' => 'booked',
        ]);

        $response->assertSessionHas('status', 'Inquiry updated, but Google Calendar sync failed. Check the logs and retry.');
    }

    public function test_non_booked_status_change_does_not_call_calendar(): void
    {
        $calendar = Mockery::mock(GoogleCalendar::class);
        $calendar->shouldNotReceive('upsertBookingEvent');
        $this->app->instance(GoogleCalendar::class, $calendar);

        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['status' => 'new']);

        $response = $this->actingAs($user)->put(route('admin.inquiries.update', $inquiry), [
            'status' => 'follow_up',
        ]);

        $response->assertSessionHas('status', 'Inquiry updated.');
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

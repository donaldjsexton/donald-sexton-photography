<?php

namespace Tests\Feature;

use App\Mail\InquiryAcknowledgment;
use App\Mail\QuizResult;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Inquiry;
use App\Models\QuizLead;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Crm\ContactDirectory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CrmContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // CI runs tests before the frontend build, so there is no Vite manifest.
        $this->withoutVite();
    }

    private function directory(): ContactDirectory
    {
        return app(ContactDirectory::class);
    }

    private function quizLead(string $email): QuizLead
    {
        return QuizLead::create([
            'first_name' => 'Sam', 'email' => $email, 'result' => 'essential', 'score' => 3, 'answers' => [],
            'consent_at' => now(), 'unsubscribe_token' => str_repeat('a', 48),
        ]);
    }

    public function test_find_or_create_dedupes_on_normalized_email_and_keeps_source(): void
    {
        $first = $this->directory()->findOrCreate('  Sam@Example.com ', 'coverage_quiz', ['name' => 'Sam']);
        $second = $this->directory()->findOrCreate('sam@example.com', 'site_form', ['name' => 'Other', 'phone' => '555']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Contact::query()->count());
        $this->assertSame('coverage_quiz', $second->fresh()->source);
        $this->assertSame('Sam', $second->fresh()->name);
        $this->assertSame('555', $second->fresh()->phone);
        $this->assertSame('sam@example.com', $second->email);
    }

    public function test_source_is_required_by_the_database(): void
    {
        $this->expectException(QueryException::class);
        Contact::query()->insert(['site_id' => 1, 'email' => 'x@example.com', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_marketing_mail_to_suppressed_address_is_blocked(): void
    {
        $lead = $this->quizLead('sam@example.com');
        $this->directory()->suppress('SAM@example.com', 'manual', 'asked to stop');

        $sent = Mail::mailer('array')->to('sam@example.com')->send(new QuizResult($lead, config('quiz.tiers.essential'), 'why', false, 'https://example.test'));

        $this->assertNull($sent);
    }

    public function test_marketing_mail_to_unsuppressed_address_is_sent(): void
    {
        $lead = $this->quizLead('sam@example.com');

        $sent = Mail::mailer('array')->to('sam@example.com')->send(new QuizResult($lead, config('quiz.tiers.essential'), 'why', false, 'https://example.test'));

        $this->assertNotNull($sent);
    }

    public function test_transactional_mail_is_blocked_for_suppressed_non_client_but_sent_to_active_client(): void
    {
        $inquiry = Inquiry::factory()->create(['email' => 'lead@example.com', 'status' => 'new']);
        $this->directory()->suppress('lead@example.com', 'manual');

        $blocked = Mail::mailer('array')->to('lead@example.com')->send(new InquiryAcknowledgment($inquiry, []));
        $this->assertNull($blocked);

        $client = Client::factory()->create(['email' => 'client@example.com']);
        $contact = $this->directory()->findOrCreate('client@example.com', 'client_portal');
        $contact->update(['client_id' => $client->id]);
        $this->directory()->suppress('client@example.com', 'manual');

        $bookedInquiry = Inquiry::factory()->create(['email' => 'client@example.com', 'status' => 'booked']);
        $sent = Mail::mailer('array')->to('client@example.com')->send(new InquiryAcknowledgment($bookedInquiry, []));
        $this->assertNotNull($sent);
    }

    public function test_quiz_submission_creates_contact_and_unsubscribe_suppresses(): void
    {
        Mail::fake();

        $this->postJson('/quiz', [
            'first_name' => 'Sam', 'email' => 'Sam@Example.com', 'consent' => '1', 'day' => 'full',
            'guests' => '25-75', 'locations' => 'same', 'moments' => ['formals'], 'start' => 'afternoon', 'second' => 'no',
        ])->assertOk();

        $lead = QuizLead::query()->firstOrFail();
        $contact = Contact::query()->where('email', 'sam@example.com')->firstOrFail();
        $this->assertSame($contact->id, $lead->contact_id);
        $this->assertSame('coverage_quiz', $contact->source);

        $this->get('/quiz/unsubscribe/'.$lead->unsubscribe_token)->assertOk();

        $this->assertTrue($this->directory()->isSuppressed('sam@example.com'));
        $this->assertSame('unsubscribe', Suppression::query()->firstOrFail()->source);
    }

    public function test_inquiry_submission_links_contact_and_moves_stage(): void
    {
        Mail::fake();

        $this->post('/inquire', [
            'primary_name' => 'Pat Lee', 'email' => 'pat@example.com', 'event_type' => 'wedding',
        ]);

        $inquiry = Inquiry::query()->where('email', 'pat@example.com')->firstOrFail();
        $contact = Contact::query()->where('email', 'pat@example.com')->firstOrFail();
        $this->assertSame($contact->id, $inquiry->contact_id);
        $this->assertSame('inquiry', $contact->stage);
        $this->assertSame('site_form', $contact->source);
    }

    public function test_import_brain_is_idempotent_and_sets_source_and_dnc(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'brain');
        file_put_contents($file, json_encode(['clients' => [
            ['email' => 'A@Venue.com', 'name' => 'A', 'sources' => ['gmail-sent'], 'dnc' => false, 'first_contact' => '2026-01-02', 'last_contact' => '2026-03-04'],
            ['email' => 'stop@x.com', 'name' => 'Stop', 'sources' => ['gmail-sent'], 'dnc' => true, 'dnc_reason' => 'asked to be removed'],
            ['email' => 'bad@x.con', 'name' => 'Typo', 'sources' => [], 'dnc' => true, 'dnc_reason' => 'hard bounce (invalid address)'],
            ['email' => 'not-an-email', 'name' => 'Bad'],
        ]]));

        $this->artisan('crm:import-brain', ['file' => $file, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Contact::query()->count());

        $this->artisan('crm:import-brain', ['file' => $file])->assertSuccessful();
        $this->artisan('crm:import-brain', ['file' => $file])->assertSuccessful();

        $this->assertSame(3, Contact::query()->count());
        $this->assertSame(2, Suppression::query()->count());
        $this->assertSame('gmail_sent', Contact::query()->where('email', 'a@venue.com')->value('source'));
        $this->assertSame('agent_import', Contact::query()->where('email', 'bad@x.con')->value('source'));
        $this->assertSame('bounce', Suppression::query()->where('email', 'bad@x.con')->value('source'));
        $this->assertSame('imported', Suppression::query()->where('email', 'stop@x.com')->value('source'));
    }

    public function test_backfill_links_existing_records(): void
    {
        $inquiry = Inquiry::factory()->create(['email' => 'old@example.com', 'status' => 'booked']);
        $lead = $this->quizLead('quiz@example.com');
        $lead->update(['unsubscribed_at' => now()]);

        $this->artisan('crm:backfill-contacts')->assertSuccessful();

        $this->assertNotNull($inquiry->fresh()->contact_id);
        $this->assertSame('booked', Contact::query()->where('email', 'old@example.com')->value('stage'));
        $this->assertNotNull($lead->fresh()->contact_id);
        $this->assertTrue($this->directory()->isSuppressed('quiz@example.com'));
    }

    public function test_admin_pages_require_auth_and_work(): void
    {
        $this->get(route('admin.contacts.index'))->assertRedirect(route('admin.login'));

        $admin = User::factory()->create();
        $contact = $this->directory()->findOrCreate('sam@example.com', 'coverage_quiz', ['name' => 'Sam Rivera']);

        $this->actingAs($admin)->get(route('admin.contacts.index'))->assertOk()->assertSee('Sam Rivera')->assertSee('coverage_quiz');
        $this->actingAs($admin)->get(route('admin.contacts.show', $contact))->assertOk()->assertSee('Not on the list');

        $this->actingAs($admin)->post(route('admin.contacts.suppress', $contact), ['reason' => 'phoned'])->assertRedirect();
        $this->assertTrue($this->directory()->isSuppressed('sam@example.com'));
        $this->actingAs($admin)->get(route('admin.contacts.index', ['dnc' => '1']))->assertSee('Sam Rivera');
        $this->actingAs($admin)->get(route('admin.contacts.index', ['dnc' => '0']))->assertDontSee('Sam Rivera');

        $this->actingAs($admin)->post(route('admin.contacts.unsuppress', $contact))->assertRedirect();
        $this->assertFalse($this->directory()->isSuppressed('sam@example.com'));
    }
}

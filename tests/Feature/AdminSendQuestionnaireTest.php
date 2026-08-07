<?php

namespace Tests\Feature;

use App\Mail\QuestionnaireInvitation;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\User;
use App\Models\WeddingQuestionnaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminSendQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_questionnaire_and_emails_the_client(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create(['email' => 'couple@example.com']);
        $inquiry = Inquiry::factory()->create(['client_id' => $client->id]);

        $this->actingAs($admin)
            ->post(route('admin.clients.questionnaire', $client))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('wedding_questionnaires', ['inquiry_id' => $inquiry->id]);

        Mail::assertSent(QuestionnaireInvitation::class, fn ($mail) => $mail->hasTo('couple@example.com'));
    }

    public function test_it_reuses_the_existing_questionnaire_instead_of_duplicating(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $inquiry = Inquiry::factory()->create(['client_id' => $client->id]);
        $existing = $inquiry->ensureQuestionnaire();

        $this->actingAs($admin)
            ->post(route('admin.clients.questionnaire', $client))
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertSame(1, WeddingQuestionnaire::where('inquiry_id', $inquiry->id)->count());
        $this->assertSame($existing->id, $inquiry->questionnaire()->first()->id);
    }

    public function test_it_errors_when_the_client_has_no_inquiry(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.clients.questionnaire', $client))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('wedding_questionnaires', 0);
        Mail::assertNothingSent();
    }

    public function test_it_requires_admin_auth(): void
    {
        $client = Client::factory()->create();

        $this->post(route('admin.clients.questionnaire', $client))
            ->assertRedirect(route('admin.login'));
    }
}

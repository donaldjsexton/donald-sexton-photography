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

    public function test_it_creates_a_client_questionnaire_when_there_is_no_inquiry(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create(['email' => 'solo@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.clients.questionnaire', $client))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('wedding_questionnaires', [
            'client_id' => $client->id,
            'inquiry_id' => null,
        ]);
        $this->assertSame(1, $client->questionnaires()->count());

        Mail::assertSent(QuestionnaireInvitation::class, fn ($mail) => $mail->hasTo('solo@example.com'));
    }

    public function test_it_does_not_duplicate_a_client_questionnaire_on_resend(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)->post(route('admin.clients.questionnaire', $client));
        $this->actingAs($admin)->post(route('admin.clients.questionnaire', $client));

        $this->assertSame(1, $client->questionnaires()->count());
    }

    public function test_it_requires_admin_auth(): void
    {
        $client = Client::factory()->create();

        $this->post(route('admin.clients.questionnaire', $client))
            ->assertRedirect(route('admin.login'));
    }
}

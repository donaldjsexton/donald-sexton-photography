<?php

namespace Tests\Feature\Portal;

use App\Mail\WeddingQuestionnaireReceived;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\PortalActivity;
use App\Models\Venue;
use App\Models\WeddingQuestionnaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuestionnairePortalTest extends TestCase
{
    use RefreshDatabase;

    private function questionnaireFor(Client $client): WeddingQuestionnaire
    {
        return Inquiry::factory()
            ->create(['client_id' => $client->id])
            ->ensureQuestionnaire();
    }

    public function test_a_client_questionnaire_without_an_inquiry_appears_in_the_portal(): void
    {
        $client = Client::factory()->create();
        $questionnaire = $client->questionnaires()->create([]);

        $this->assertNull($questionnaire->inquiry_id);

        $this->actingAs($client, 'client')
            ->get(route('portal.questionnaires.index'))
            ->assertOk()
            ->assertSee(route('portal.questionnaires.show', ['questionnaire' => $questionnaire->token]));

        $this->actingAs($client, 'client')
            ->get(route('portal.questionnaires.show', ['questionnaire' => $questionnaire->token]))
            ->assertOk()
            ->assertSee('Submit Questionnaire');
    }

    public function test_index_lists_only_the_clients_questionnaires(): void
    {
        $client = Client::factory()->create();
        $mine = $this->questionnaireFor($client);
        $theirs = $this->questionnaireFor(Client::factory()->create());

        $this->actingAs($client, 'client')
            ->get(route('portal.questionnaires.index'))
            ->assertOk()
            ->assertSee(route('portal.questionnaires.show', ['questionnaire' => $mine->token]))
            ->assertDontSee(route('portal.questionnaires.show', ['questionnaire' => $theirs->token]));
    }

    public function test_show_renders_form_and_records_activity(): void
    {
        $client = Client::factory()->create();
        $questionnaire = $this->questionnaireFor($client);

        $this->actingAs($client, 'client')
            ->get(route('portal.questionnaires.show', ['questionnaire' => $questionnaire->token]))
            ->assertOk()
            ->assertSee('Wedding Questionnaire')
            ->assertSee('Submit Questionnaire');

        $this->assertDatabaseHas('portal_activities', [
            'actor_id' => $client->id,
            'type' => PortalActivity::TYPE_QUESTIONNAIRE_VIEWED,
            'subject_id' => $questionnaire->id,
        ]);
    }

    public function test_show_404s_for_another_clients_questionnaire(): void
    {
        $client = Client::factory()->create();
        $stranger = $this->questionnaireFor(Client::factory()->create());

        $this->actingAs($client, 'client')
            ->get(route('portal.questionnaires.show', ['questionnaire' => $stranger->token]))
            ->assertNotFound();
    }

    public function test_update_stores_responses_marks_submitted_and_notifies_studio(): void
    {
        Mail::fake();
        config(['mail.inquiry_to' => 'studio@example.com']);

        $client = Client::factory()->create();
        $questionnaire = $this->questionnaireFor($client);

        $this->actingAs($client, 'client')
            ->put(route('portal.questionnaires.update', ['questionnaire' => $questionnaire->token]), [
                'location' => 'Clearwater Beach',
                'shots_reception' => ['Band or DJ', ''],
                'bride_name' => '   ',
            ])
            ->assertRedirect(route('portal.questionnaires.show', ['questionnaire' => $questionnaire->token]));

        $questionnaire->refresh();
        $this->assertNotNull($questionnaire->submitted_at);
        $this->assertSame('Clearwater Beach', $questionnaire->response('location'));
        $this->assertSame(['Band or DJ'], $questionnaire->response('shots_reception'));
        $this->assertNull($questionnaire->response('bride_name'));

        $this->assertDatabaseHas('portal_activities', [
            'actor_id' => $client->id,
            'type' => PortalActivity::TYPE_QUESTIONNAIRE_SUBMITTED,
            'subject_id' => $questionnaire->id,
        ]);

        Mail::assertSent(WeddingQuestionnaireReceived::class);
    }

    public function test_update_does_not_overwrite_an_already_submitted_questionnaire(): void
    {
        $client = Client::factory()->create();
        $questionnaire = $this->questionnaireFor($client);
        $questionnaire->update(['responses' => ['location' => 'Original'], 'submitted_at' => now()->subDay()]);

        $this->actingAs($client, 'client')
            ->put(route('portal.questionnaires.update', ['questionnaire' => $questionnaire->token]), [
                'location' => 'Changed',
            ])
            ->assertRedirect(route('portal.questionnaires.show', ['questionnaire' => $questionnaire->token]));

        $this->assertSame('Original', $questionnaire->fresh()->response('location'));
    }

    public function test_guests_cannot_reach_the_portal_questionnaires(): void
    {
        $this->get(route('portal.questionnaires.index'))
            ->assertRedirect(route('portal.login'));
    }

    public function test_venues_cannot_access_client_questionnaires(): void
    {
        $venue = Venue::factory()->create();

        $this->actingAs($venue, 'venue')
            ->get(route('portal.questionnaires.index'))
            ->assertRedirect(route('portal.login'));
    }
}

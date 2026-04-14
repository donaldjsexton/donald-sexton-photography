<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeddingQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_lead(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.inquiries.store'), [
            'primary_name' => 'Vanessa Carcache',
            'email' => 'vanessacarc2@gmail.com',
            'event_type' => 'wedding',
            'event_date' => '2026-04-04',
        ]);

        $inquiry = Inquiry::where('email', 'vanessacarc2@gmail.com')->firstOrFail();
        $response->assertRedirect(route('admin.inquiries.edit', $inquiry));
        $this->assertSame('admin', $inquiry->source);
    }

    public function test_admin_can_generate_questionnaire_link(): void
    {
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.inquiries.questionnaire.generate', $inquiry))
            ->assertRedirect(route('admin.inquiries.edit', $inquiry));

        $this->assertNotNull($inquiry->fresh()->questionnaire);
        $this->assertNotEmpty($inquiry->fresh()->questionnaire->token);
    }

    public function test_client_can_submit_questionnaire(): void
    {
        $inquiry = Inquiry::factory()->create();
        $questionnaire = $inquiry->ensureQuestionnaire();

        $this->get(route('questionnaire.show', $questionnaire))->assertOk();

        $this->put(route('questionnaire.update', $questionnaire), [
            'bride_name' => 'Vanessa Carcache',
            'groom_name' => 'Daniel Blake',
            'first_look' => 'No',
            'shots_reception' => ['First dance of bride and groom', 'Bride dance with father'],
        ])->assertRedirect(route('questionnaire.thank-you'));

        $fresh = $questionnaire->fresh();
        $this->assertNotNull($fresh->submitted_at);
        $this->assertSame('Vanessa Carcache', $fresh->response('bride_name'));
        $this->assertSame(['First dance of bride and groom', 'Bride dance with father'], $fresh->response('shots_reception'));
    }

    public function test_submitted_questionnaire_rejects_further_updates(): void
    {
        $inquiry = Inquiry::factory()->create();
        $questionnaire = $inquiry->ensureQuestionnaire();
        $questionnaire->update(['submitted_at' => now(), 'responses' => ['bride_name' => 'Original']]);

        $this->put(route('questionnaire.update', $questionnaire), [
            'bride_name' => 'Changed',
        ])->assertRedirect(route('questionnaire.thank-you'));

        $this->assertSame('Original', $questionnaire->fresh()->response('bride_name'));
    }
}

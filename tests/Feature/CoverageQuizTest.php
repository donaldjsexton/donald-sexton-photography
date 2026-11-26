<?php

namespace Tests\Feature;

use App\Mail\QuizResult;
use App\Models\QuizLead;
use App\Services\Quiz\CoverageQuizScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CoverageQuizTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return $over + [
            'first_name' => 'Sam',
            'email' => 'sam@example.com',
            'consent' => '1',
            'day' => 'full',
            'guests' => '25-75',
            'locations' => 'same',
            'moments' => ['formals'],
            'start' => 'afternoon',
            'second' => 'no',
        ];
    }

    public function test_page_renders_noindex(): void
    {
        $this->get('/quiz')->assertOk()->assertSee('Find my coverage', false)->assertSee('noindex', false);
    }

    public function test_scoring_thresholds(): void
    {
        $s = new CoverageQuizScorer;
        $this->assertSame('essential', $s->score(['day' => 'full', 'guests' => 'u25', 'moments' => ['formals']])['result']);
        $this->assertSame('complete', $s->score(['day' => 'full', 'guests' => '75-150', 'locations' => 'short', 'moments' => ['ready', 'firstlook', 'sunset']])['result']); // 2+1+1+3=7
        $this->assertSame('extended', $s->score(['day' => 'full', 'guests' => '150plus', 'locations' => 'far', 'moments' => ['ready', 'sunset', 'rehearsal', 'sendoff'], 'start' => 'evening'])['result']);
        $this->assertSame('elopement', $s->score(['day' => 'small'])['result']);
        $this->assertTrue($s->score(['day' => 'full', 'guests' => '150plus'])['second']);
    }

    public function test_submit_stores_lead_and_emails_result(): void
    {
        Mail::fake();
        $this->postJson('/quiz', $this->payload())
            ->assertOk()
            ->assertJsonPath('result', 'essential')
            ->assertJsonPath('emailed', true);

        $lead = QuizLead::first();
        $this->assertSame('sam@example.com', $lead->email);
        $this->assertNotNull($lead->consent_at);
        Mail::assertSent(QuizResult::class);
    }

    public function test_consent_is_required(): void
    {
        Mail::fake();
        $this->postJson('/quiz', $this->payload(['consent' => null]))->assertStatus(422);
        $this->assertSame(0, QuizLead::count());
        Mail::assertNothingSent();
    }

    public function test_elopement_gets_no_price_and_no_email(): void
    {
        Mail::fake();
        $r = $this->postJson('/quiz', $this->payload(['day' => 'elopement']))->assertOk()->assertJsonPath('result', 'elopement');
        $this->assertStringNotContainsString('$', json_encode($r->json('tier')));
        Mail::assertNothingSent();
    }

    public function test_unsubscribe_marks_lead(): void
    {
        Mail::fake();
        $this->postJson('/quiz', $this->payload());
        $lead = QuizLead::first();
        $this->get(route('quiz.unsubscribe', $lead->unsubscribe_token))->assertOk();
        $this->assertNotNull($lead->fresh()->unsubscribed_at);
    }

    public function test_honeypot_is_ignored(): void
    {
        Mail::fake();
        $this->postJson('/quiz', $this->payload(['website' => 'spam.example']))->assertOk();
        $this->assertSame(0, QuizLead::count());
    }

    public function test_inquiry_form_prefills_from_quiz_link(): void
    {
        $this->get('/inquire?event_type=wedding&venue_name=Hyatt&message='.urlencode('Recommendation: 8 hours'))
            ->assertOk()
            ->assertSee('Hyatt', false)
            ->assertSee('Recommendation: 8 hours', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_render_with_one_h1_and_site_contact_email(): void
    {
        SiteSetting::create(['business_email' => 'legal@example.test', 'business_street_address' => '1 Test Rd', 'business_locality' => 'Clearwater', 'business_region' => 'FL']);

        foreach (['/privacy-policy', '/terms-of-service', '/accessibility', '/data-deletion'] as $path) {
            $content = $this->get($path)->assertOk()->assertSee('legal@example.test')->assertSee('1 Test Rd, Clearwater, FL')->getContent();

            $this->assertSame(1, preg_match_all('/<h1\b/i', $content), $path);
            $this->assertStringContainsString('Effective November 27, 2026', $content, $path);
        }
    }

    public function test_data_deletion_and_accessibility_describe_the_process(): void
    {
        $this->get('/data-deletion')->assertSee('Delete my data')->assertSee('30 days');
        $this->get('/accessibility')->assertSee('WCAG 2.1')->assertSee('Known gaps');
    }

    public function test_footer_and_sitemap_link_the_new_pages(): void
    {
        $this->get('/')->assertOk()
            ->assertSee(route('legal.accessibility'), false)
            ->assertSee(route('legal.data-deletion'), false);

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<loc>'.route('legal.accessibility').'</loc>', false)
            ->assertSee('<loc>'.route('legal.data-deletion').'</loc>', false);
    }

    public function test_inquiry_and_quiz_forms_link_privacy_and_do_not_pre_check_consent(): void
    {
        $inquiry = $this->get('/inquire')->assertOk()->assertSee(route('legal.privacy'), false)->getContent();
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="checkbox"[^>]*sms_opt_in[^>]*\schecked/i', $inquiry);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="sms_opt_in_transactional"[^>]*type="checkbox"[^>]*\schecked/i', $inquiry);

        $quiz = $this->get('/quiz')->assertOk()->assertSee(route('legal.privacy'), false)->getContent();
        $this->assertDoesNotMatchRegularExpression('/name="consent"[^>]*\schecked/i', $quiz);
    }
}

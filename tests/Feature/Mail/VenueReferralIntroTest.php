<?php

namespace Tests\Feature\Mail;

use App\Mail\VenueReferralIntro;
use App\Models\Inquiry;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VenueReferralIntroTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_email_includes_preheader_named_route_cta_and_response_promise(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Knotted Roots on the Lake',
            'referral_contact_name' => 'Tara Hardin',
        ]);

        $inquiry = Inquiry::factory()->create([
            'primary_name' => 'Stephanie Clancy',
            'email' => 'clancy_stephanie@yahoo.com',
            'event_date' => Carbon::parse('2027-03-20'),
            'venue_id' => $venue->id,
        ]);

        $html = (new VenueReferralIntro($inquiry, $venue))->render();

        $this->assertStringContainsString('Tara Hardin at Knotted Roots on the Lake just connected us', $html);
        $this->assertStringContainsString('href="'.route('weddings.index').'"', $html);
        $this->assertStringContainsString('within one business day', $html);
        $this->assertStringContainsString('Hi Stephanie,', $html);
        $this->assertStringContainsString('March 20, 2027', $html);
        $this->assertStringContainsString('name="viewport"', $html);
    }

    public function test_rendered_email_falls_back_when_contact_name_missing(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Sandpiper Hall',
            'referral_contact_name' => null,
        ]);

        $inquiry = Inquiry::factory()->create([
            'primary_name' => 'Jamie Rivera',
            'event_date' => null,
            'venue_id' => $venue->id,
        ]);

        $html = (new VenueReferralIntro($inquiry, $venue))->render();

        $this->assertStringContainsString('The team at Sandpiper Hall', $html);
        $this->assertStringContainsString('within one business day', $html);
    }
}

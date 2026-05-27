<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueFaqsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_form_persists_faqs_parsed_from_pipe_delimited_lines(): void
    {
        $venue = Venue::create([
            'name' => 'Lakeside Estate',
            'slug' => 'lakeside-estate',
        ]);

        $this->actingAsAdmin()
            ->put(route('admin.venues.update', $venue), [
                'name' => 'Lakeside Estate',
                'faqs_text' => "Has anyone gotten married here? | Yes, we've covered multiple weddings here.\nWhat time of day looks best? | Late afternoon through sunset.\nblank line below should be ignored\n\n",
            ])
            ->assertRedirect(route('admin.venues.edit', $venue));

        $venue->refresh();

        $this->assertSame([
            ['question' => 'Has anyone gotten married here?', 'answer' => "Yes, we've covered multiple weddings here."],
            ['question' => 'What time of day looks best?', 'answer' => 'Late afternoon through sunset.'],
        ], $venue->faqs);
    }

    public function test_venue_page_renders_visible_faq_section_and_faq_page_schema(): void
    {
        $venue = Venue::create([
            'name' => 'Powel Crosley Estate',
            'slug' => 'powel-crosley-estate',
            'faqs' => [
                ['question' => 'What time of day looks best at this venue?', 'answer' => 'Late afternoon through golden hour.'],
                ['question' => 'Is there an indoor backup?', 'answer' => 'Yes, the ballroom and covered loggia both work.'],
            ],
        ]);

        $this->get(route('venues.show', $venue->slug))
            ->assertOk()
            ->assertSee('class="venue-faqs"', false)
            ->assertSee('What time of day looks best at this venue?', false)
            ->assertSee('Late afternoon through golden hour.', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"Question"', false)
            ->assertSee('"@type":"Answer"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_venue_page_skips_faq_schema_when_no_faqs_configured(): void
    {
        $venue = Venue::create([
            'name' => 'Bare Venue',
            'slug' => 'bare-venue',
        ]);

        $this->get(route('venues.show', $venue->slug))
            ->assertOk()
            ->assertDontSee('"@type":"FAQPage"', false)
            ->assertDontSee('class="venue-faqs"', false);
    }

    public function test_venue_page_shows_nearby_venues_in_the_same_city(): void
    {
        $venue = Venue::create([
            'name' => 'Beachside Hotel',
            'slug' => 'beachside-hotel',
            'city' => 'Clearwater',
            'state' => 'FL',
        ]);

        $sameCity = Venue::create([
            'name' => 'Clearwater Garden',
            'slug' => 'clearwater-garden',
            'city' => 'Clearwater',
            'state' => 'FL',
        ]);

        $otherCity = Venue::create([
            'name' => 'Faraway Hall',
            'slug' => 'faraway-hall',
            'city' => 'Miami',
            'state' => 'FL',
        ]);

        $response = $this->get(route('venues.show', $venue->slug))->assertOk();

        $response->assertSee('Nearby Venues', false)
            ->assertSee($sameCity->name);
    }

    public function test_structured_faqs_drops_entries_missing_question_or_answer(): void
    {
        $venue = Venue::create([
            'name' => 'Test Venue',
            'slug' => 'test-venue',
            'faqs' => [
                ['question' => 'Real?', 'answer' => 'Yes.'],
                ['question' => '', 'answer' => 'no question'],
                ['question' => 'no answer', 'answer' => ''],
                'not even an array',
            ],
        ]);

        $this->assertSame([
            ['question' => 'Real?', 'answer' => 'Yes.'],
        ], $venue->structuredFaqs());
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
    }
}

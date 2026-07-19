<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageFaqsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_form_persists_page_faqs_parsed_from_pipe_delimited_lines(): void
    {
        $page = Page::create([
            'title' => 'Tampa Wedding Photographer',
            'slug' => 'tampa',
            'template' => 'location',
            'status' => 'published',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.pages.update', $page), [
                'title' => 'Tampa Wedding Photographer',
                'slug' => 'tampa',
                'template' => 'location',
                'status' => 'published',
                'faqs_text' => "Do you photograph weddings in Tampa? | Yes, Tampa is part of my regular coverage area.\nDo you offer elopement packages? | Yes, shorter collections cover elopements.\nline without a pipe is ignored\n\n",
            ])
            ->assertRedirect(route('admin.pages.edit', $page));

        $page->refresh();

        $this->assertSame([
            ['question' => 'Do you photograph weddings in Tampa?', 'answer' => 'Yes, Tampa is part of my regular coverage area.'],
            ['question' => 'Do you offer elopement packages?', 'answer' => 'Yes, shorter collections cover elopements.'],
        ], $page->faqs);
    }

    public function test_location_page_renders_visible_faq_section_and_faq_page_schema(): void
    {
        $page = Page::create([
            'title' => 'Tampa Wedding Photographer',
            'slug' => 'tampa',
            'template' => 'location',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'faqs' => [
                ['question' => 'Do you photograph weddings in Tampa?', 'answer' => 'Yes — Tampa is part of my regular coverage area.'],
                ['question' => 'Do you offer elopement packages in Tampa?', 'answer' => 'Yes, shorter collections are built for elopements.'],
            ],
        ]);

        $this->get(route('pages.location', $page->slug))
            ->assertOk()
            ->assertSee('class="venue-faqs"', false)
            ->assertSee('Do you photograph weddings in Tampa?', false)
            ->assertSee('Yes — Tampa is part of my regular coverage area.', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"Question"', false)
            ->assertSee('"@type":"Answer"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_location_page_skips_faq_schema_when_no_faqs_configured(): void
    {
        $page = Page::create([
            'title' => 'Sarasota Wedding Photographer',
            'slug' => 'sarasota',
            'template' => 'location',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('pages.location', $page->slug))
            ->assertOk()
            ->assertDontSee('"@type":"FAQPage"', false)
            ->assertDontSee('class="venue-faqs"', false);
    }

    public function test_generated_location_page_ships_with_starter_faqs(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.pages.generate-location'), [
                'city' => 'Tampa',
                'state' => 'FL',
            ])
            ->assertRedirect();

        $page = Page::query()->where('slug', 'tampa')->firstOrFail();

        $this->assertNotEmpty($page->structuredFaqs());
        $this->assertSame('Do you photograph weddings in Tampa?', $page->structuredFaqs()[0]['question']);
    }
}

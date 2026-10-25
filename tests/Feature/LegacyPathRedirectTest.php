<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyPathRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_wordpress_pages_redirect_to_their_closest_equivalent(): void
    {
        $this->get('/contact/')->assertRedirect('/inquire')->assertStatus(301);
        $this->get('/engagement-photography/')->assertRedirect('/weddings')->assertStatus(301);
        $this->get('/documentary-wedding-photographer/')->assertRedirect('/weddings')->assertStatus(301);
        $this->get('/minis/')->assertRedirect('/inquire')->assertStatus(301);
        $this->get('/preferred-vendors/')->assertRedirect('/venues')->assertStatus(301);
        $this->get('/your-story/')->assertRedirect('/about')->assertStatus(301);
    }

    public function test_blog_index_and_pagination_redirect_to_the_journal(): void
    {
        $this->get('/blog/')->assertRedirect('/journal')->assertStatus(301);
        $this->get('/blog/page/27/')->assertRedirect('/journal')->assertStatus(301);
    }

    public function test_known_category_archives_redirect_to_the_journal_category(): void
    {
        Category::create(['name' => 'Tips', 'slug' => 'tips']);
        Category::create(['name' => 'Wedding advice', 'slug' => 'wedding-advice']);

        $this->get('/category/tips/page/3/')->assertRedirect('/journal/category/tips')->assertStatus(301);
        $this->get('/category/wedding/wedding-advice/')->assertRedirect('/journal/category/wedding-advice')->assertStatus(301);
    }

    public function test_unknown_category_archives_fall_back_to_the_journal(): void
    {
        $this->get('/category/maternity/')->assertRedirect('/journal')->assertStatus(301);
    }

    public function test_known_tag_archives_redirect_to_the_journal_tag(): void
    {
        Tag::create(['name' => 'beach', 'slug' => 'beach']);

        $this->get('/tag/beach/')->assertRedirect('/journal/tag/beach')->assertStatus(301);
        $this->get('/tag/beach/page/2/')->assertRedirect('/journal/tag/beach')->assertStatus(301);
    }

    public function test_unknown_tag_archives_are_gone(): void
    {
        $this->get('/tag/24-70mm/')->assertStatus(410);
    }

    public function test_author_archives_redirect_to_about(): void
    {
        $this->get('/author/admin/')->assertRedirect('/about')->assertStatus(301);
        $this->get('/author/admin/page/2/')->assertRedirect('/about')->assertStatus(301);
    }

    public function test_old_wordpress_sitemaps_redirect_to_the_current_sitemap(): void
    {
        foreach (['/sitemap_index.xml', '/post-sitemap2.xml', '/page-sitemap.xml', '/category-sitemap.xml', '/elementor-hf-sitemap.xml', '/wp-sitemap.xml'] as $path) {
            $this->get($path)->assertRedirect('/sitemap.xml')->assertStatus(301);
        }
    }

    public function test_a_stored_redirect_wins_over_the_structural_rules(): void
    {
        Redirect::create([
            'from_path' => '/contact',
            'to_path' => '/weddings',
            'status_code' => 302,
        ]);

        $this->get('/contact')->assertRedirect('/weddings')->assertStatus(302);
    }

    public function test_a_published_page_wins_over_the_structural_rules(): void
    {
        Page::create([
            'title' => 'Minis',
            'slug' => 'minis',
            'template' => 'default',
            'status' => 'published',
            'body' => '<p>Mini sessions.</p>',
            'published_at' => now(),
        ]);

        $this->get('/minis')->assertOk()->assertSee('Mini sessions.');
    }

    public function test_unrelated_unknown_paths_still_404(): void
    {
        $this->get('/blogger-tools')->assertNotFound();
        $this->get('/categories')->assertNotFound();
        $this->get('/definitely-not-a-page')->assertNotFound();
    }
}

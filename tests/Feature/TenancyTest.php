<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Site;
use App\Tenancy\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_a_default_site(): void
    {
        $default = Site::default();

        $this->assertNotNull($default);
        $this->assertTrue($default->is_default);
        $this->assertSame($default->id, app(CurrentSite::class)->id());
    }

    public function test_models_are_stamped_with_the_current_site(): void
    {
        $page = Page::factory()->create();

        $this->assertSame(Site::default()->id, $page->site_id);
    }

    public function test_global_scope_isolates_tenants(): void
    {
        $default = Site::default();
        $siteB = Site::factory()->create(['subdomain' => 'studio']);

        app(CurrentSite::class)->set($default);
        $pageA = Page::factory()->create(['slug' => 'a-page']);

        app(CurrentSite::class)->set($siteB);
        $pageB = Page::factory()->create(['slug' => 'b-page']);

        $this->assertSame([$pageB->id], Page::query()->pluck('id')->all());

        app(CurrentSite::class)->set($default);
        $this->assertSame([$pageA->id], Page::query()->pluck('id')->all());

        // The escape hatch sees everything.
        $this->assertSame(2, Page::withoutSiteScope()->count());
    }

    public function test_subdomain_request_only_serves_that_tenants_content(): void
    {
        config(['app.domain' => 'example.com']);

        $siteB = Site::factory()->create(['subdomain' => 'studio']);

        app(CurrentSite::class)->set($siteB);
        $page = Page::factory()->create(['slug' => 'studio-page', 'status' => 'published']);

        $this->get('http://studio.example.com/studio-page')
            ->assertOk()
            ->assertSee($page->title);

        // The apex (default tenant) must not see site B's page.
        $this->get('http://example.com/studio-page')->assertNotFound();
    }

    public function test_tls_check_endpoint_validates_known_hosts(): void
    {
        config(['app.domain' => 'example.com']);
        Site::factory()->create(['subdomain' => 'studio']);

        $this->get('/tenancy/tls-check?domain=studio.example.com')->assertOk();
        $this->get('/tenancy/tls-check?domain=example.com')->assertOk();
        $this->get('/tenancy/tls-check?domain=nope.example.com')->assertNotFound();
        $this->get('/tenancy/tls-check')->assertStatus(400);
    }
}

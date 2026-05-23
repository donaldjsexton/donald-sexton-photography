<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Collection;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
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

    public function test_crm_and_content_models_are_tenant_scoped(): void
    {
        $default = Site::default();
        $siteB = Site::factory()->create(['subdomain' => 'second']);

        app(CurrentSite::class)->set($default);
        $collectionA = Collection::create(['name' => 'Package A', 'slug' => 'package-a']);
        $inquiryA = Inquiry::create(['primary_name' => 'A', 'email' => 'a@example.com', 'status' => 'new', 'source' => 'site_form']);

        app(CurrentSite::class)->set($siteB);
        $collectionB = Collection::create(['name' => 'Package B', 'slug' => 'package-b']);
        $inquiryB = Inquiry::create(['primary_name' => 'B', 'email' => 'b@example.com', 'status' => 'new', 'source' => 'site_form']);

        $this->assertSame([$collectionB->id], Collection::query()->pluck('id')->all());
        $this->assertSame([$inquiryB->id], Inquiry::query()->pluck('id')->all());

        app(CurrentSite::class)->set($default);
        $this->assertSame([$collectionA->id], Collection::query()->pluck('id')->all());
        $this->assertSame([$inquiryA->id], Inquiry::query()->pluck('id')->all());

        $this->assertSame(2, Collection::withoutSiteScope()->count());
    }

    public function test_authenticatables_and_billing_are_tenant_scoped(): void
    {
        $default = Site::default();
        $siteB = Site::factory()->create(['subdomain' => 'second']);

        app(CurrentSite::class)->set($default);
        $userA = User::factory()->create();
        $clientA = Client::factory()->create();

        app(CurrentSite::class)->set($siteB);
        $userB = User::factory()->create();
        $clientB = Client::factory()->create();

        $this->assertSame([$userB->id], User::query()->pluck('id')->all());
        $this->assertSame([$clientB->id], Client::query()->pluck('id')->all());

        app(CurrentSite::class)->set($default);
        $this->assertSame([$userA->id], User::query()->pluck('id')->all());
        $this->assertSame([$clientA->id], Client::query()->pluck('id')->all());
    }

    public function test_invoices_resolve_across_tenants_for_webhooks(): void
    {
        $default = Site::default();
        $siteB = Site::factory()->create(['subdomain' => 'second']);

        app(CurrentSite::class)->set($siteB);
        $invoice = Invoice::factory()->create();

        // A webhook runs without that tenant's context (default fallback).
        app(CurrentSite::class)->set($default);

        $this->assertNull(Invoice::find($invoice->id));
        $this->assertNotNull(Invoice::withoutSiteScope()->find($invoice->id));
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

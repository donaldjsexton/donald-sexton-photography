<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Tenancy\CurrentSite;
use App\Tenancy\DomainVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithPage(string $slug): Site
    {
        $site = Site::factory()->create(['subdomain' => 'studio']);

        app(CurrentSite::class)->set($site);
        Page::factory()->create(['slug' => $slug, 'status' => 'published']);
        app(CurrentSite::class)->set(Site::default());

        return $site;
    }

    public function test_request_on_a_verified_custom_domain_resolves_to_its_tenant(): void
    {
        $site = $this->tenantWithPage('work');
        SiteDomain::create([
            'site_id' => $site->id,
            'host' => 'studio.example.net',
            'verification_token' => 'tok',
            'verified_at' => now(),
        ]);

        $this->get('http://studio.example.net/work')->assertOk();
        $this->get('http://stranger.example.net/work')->assertNotFound();
    }

    public function test_unverified_custom_domain_does_not_resolve(): void
    {
        $site = $this->tenantWithPage('work');
        SiteDomain::create([
            'site_id' => $site->id,
            'host' => 'pending.example.net',
            'verification_token' => 'tok',
            'verified_at' => null,
        ]);

        // Falls back to the default tenant, which has no "work" page.
        $this->get('http://pending.example.net/work')->assertNotFound();
    }

    public function test_tls_check_accepts_only_verified_custom_domains(): void
    {
        $site = Site::factory()->create(['subdomain' => 'studio']);
        SiteDomain::create(['site_id' => $site->id, 'host' => 'live.example.net', 'verification_token' => 't', 'verified_at' => now()]);
        SiteDomain::create(['site_id' => $site->id, 'host' => 'pending.example.net', 'verification_token' => 't', 'verified_at' => null]);

        $this->get('/tenancy/tls-check?domain=live.example.net')->assertOk();
        $this->get('/tenancy/tls-check?domain=pending.example.net')->assertNotFound();
    }

    public function test_admin_can_add_and_verify_a_domain(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.domains.store'), ['host' => 'mystudio.example.net'])
            ->assertRedirect(route('admin.domains.index'));

        $domain = SiteDomain::query()->where('host', 'mystudio.example.net')->firstOrFail();
        $this->assertSame(Site::default()->id, $domain->site_id);
        $this->assertNull($domain->verified_at);

        $this->app->instance(DomainVerifier::class, new class implements DomainVerifier
        {
            public function verify(SiteDomain $domain): bool
            {
                return true;
            }
        });

        $this->actingAs($user)->post(route('admin.domains.verify', $domain))
            ->assertRedirect(route('admin.domains.index'));

        $this->assertNotNull($domain->refresh()->verified_at);
    }

    public function test_failed_verification_leaves_domain_pending(): void
    {
        $user = User::factory()->create();
        $domain = SiteDomain::create([
            'site_id' => Site::default()->id,
            'host' => 'notyet.example.net',
            'verification_token' => 'tok',
        ]);

        $this->app->instance(DomainVerifier::class, new class implements DomainVerifier
        {
            public function verify(SiteDomain $domain): bool
            {
                return false;
            }
        });

        $this->actingAs($user)->post(route('admin.domains.verify', $domain));

        $this->assertNull($domain->refresh()->verified_at);
    }

    public function test_admin_cannot_verify_another_tenants_domain(): void
    {
        $user = User::factory()->create();
        $other = Site::factory()->create(['subdomain' => 'studio']);
        $domain = SiteDomain::create([
            'site_id' => $other->id,
            'host' => 'theirs.example.net',
            'verification_token' => 'tok',
        ]);

        $this->actingAs($user)->post(route('admin.domains.verify', $domain))->assertNotFound();
    }

    public function test_store_rejects_subdomains_of_the_app_domain(): void
    {
        config(['app.domain' => 'example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.domains.store'), ['host' => 'foo.example.com'])
            ->assertSessionHasErrors('host');
    }
}

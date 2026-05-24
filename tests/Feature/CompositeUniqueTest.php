<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Tenancy\CurrentSite;
use App\Tenancy\SiteProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompositeUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_tenants_can_share_a_page_slug(): void
    {
        $default = Site::default();
        $siteB = Site::factory()->create(['subdomain' => 'second']);

        app(CurrentSite::class)->set($default);
        Page::factory()->create(['slug' => 'about']);

        app(CurrentSite::class)->set($siteB);
        Page::factory()->create(['slug' => 'about']);

        $this->assertSame(2, Page::withoutSiteScope()->where('slug', 'about')->count());
    }

    public function test_slug_is_still_unique_within_a_single_tenant(): void
    {
        app(CurrentSite::class)->set(Site::default());
        Page::factory()->create(['slug' => 'about']);

        $this->expectException(QueryException::class);
        Page::factory()->create(['slug' => 'about']);
    }

    public function test_two_tenants_can_share_an_admin_email(): void
    {
        $default = Site::default();
        $siteB = Site::factory()->create(['subdomain' => 'second']);

        app(CurrentSite::class)->set($default);
        User::factory()->create(['email' => 'owner@example.com']);

        app(CurrentSite::class)->set($siteB);
        User::factory()->create(['email' => 'owner@example.com']);

        $this->assertSame(2, User::withoutSiteScope()->where('email', 'owner@example.com')->count());
    }

    public function test_provisioning_a_second_tenant_with_the_same_starter_page_succeeds(): void
    {
        $provisioner = app(SiteProvisioner::class);

        $first = $provisioner->provision([
            'name' => 'One', 'subdomain' => 'one', 'admin_name' => 'A',
            'admin_email' => 'a@one.test', 'admin_password' => 'password1234',
        ]);
        $second = $provisioner->provision([
            'name' => 'Two', 'subdomain' => 'two', 'admin_name' => 'B',
            'admin_email' => 'b@two.test', 'admin_password' => 'password1234',
        ]);

        app(CurrentSite::class)->set($first);
        $this->assertNotNull(Page::query()->where('slug', 'about')->first());

        app(CurrentSite::class)->set($second);
        $this->assertNotNull(Page::query()->where('slug', 'about')->first());
    }
}

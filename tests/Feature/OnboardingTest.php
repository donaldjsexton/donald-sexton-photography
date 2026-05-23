<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Support\HomepageBlocksSeeder;
use App\Tenancy\CurrentSite;
use App\Tenancy\SiteProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioner_creates_an_isolated_tenant_with_admin_and_content(): void
    {
        $site = app(SiteProvisioner::class)->provision([
            'name' => 'Coastal Studio',
            'subdomain' => 'coastal',
            'admin_name' => 'Dana',
            'admin_email' => 'dana@coastal.test',
            'admin_password' => 'super-secret',
        ]);

        $this->assertFalse($site->is_default);

        app(CurrentSite::class)->set($site);

        $admin = User::query()->where('email', 'dana@coastal.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame($site->id, $admin->site_id);
        $this->assertTrue(Hash::check('super-secret', $admin->password));

        $this->assertNotNull(Page::query()->where('slug', 'about')->first());

        $settings = HomepageSetting::query()->first();
        $this->assertNotNull($settings);
        $this->assertCount(count(HomepageBlocksSeeder::DEFAULT_TYPES), $settings->allBlocks()->get());

        // None of it leaks into the default tenant.
        app(CurrentSite::class)->set(Site::default());
        $this->assertSame(0, User::query()->where('email', 'dana@coastal.test')->count());
        $this->assertSame(0, Page::query()->where('slug', 'about')->count());
    }

    public function test_signup_endpoint_provisions_a_site_and_redirects_to_its_admin_login(): void
    {
        config(['app.domain' => 'example.com']);

        $response = $this->post('/start', [
            'name' => 'Harbor Photography',
            'subdomain' => 'harbor',
            'admin_name' => 'Sam',
            'admin_email' => 'sam@harbor.test',
            'admin_password' => 'password1234',
            'admin_password_confirmation' => 'password1234',
        ]);

        $response->assertRedirect('http://harbor.example.com/admin/login');

        $site = Site::query()->where('subdomain', 'harbor')->first();
        $this->assertNotNull($site);

        app(CurrentSite::class)->set($site);
        $this->assertSame(1, User::query()->where('email', 'sam@harbor.test')->count());
    }

    public function test_signup_rejects_reserved_subdomain(): void
    {
        $this->post('/start', [
            'name' => 'X',
            'subdomain' => 'www',
            'admin_name' => 'X',
            'admin_email' => 'x@example.com',
            'admin_password' => 'password1234',
            'admin_password_confirmation' => 'password1234',
        ])->assertSessionHasErrors('subdomain');

        $this->assertSame(0, Site::query()->where('subdomain', 'www')->where('is_default', false)->count());
    }

    public function test_signup_rejects_taken_subdomain(): void
    {
        Site::factory()->create(['subdomain' => 'taken']);

        $this->post('/start', [
            'name' => 'X',
            'subdomain' => 'taken',
            'admin_name' => 'X',
            'admin_email' => 'x@example.com',
            'admin_password' => 'password1234',
            'admin_password_confirmation' => 'password1234',
        ])->assertSessionHasErrors('subdomain');
    }

    public function test_create_tenant_command_provisions_a_site(): void
    {
        $exit = Artisan::call('tenant:create', [
            'name' => 'Command Studio',
            'subdomain' => 'commandco',
            'admin_email' => 'owner@commandco.test',
            '--password' => 'password1234',
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('sites', ['subdomain' => 'commandco', 'is_default' => false]);
    }
}

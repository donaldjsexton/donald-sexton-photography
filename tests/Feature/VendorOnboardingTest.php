<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\Site;
use App\Tenancy\CurrentSite;
use App\Tenancy\SiteProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class VendorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_site_is_a_photographer(): void
    {
        $this->assertSame('photographer', Site::default()->vendor_type);
    }

    public function test_provisioner_applies_the_vendor_preset(): void
    {
        $site = app(SiteProvisioner::class)->provision([
            'name' => 'Beat Drop',
            'vendor_type' => 'dj',
            'subdomain' => 'beatdrop',
            'admin_name' => 'Jo',
            'admin_email' => 'jo@beatdrop.test',
            'admin_password' => 'password1234',
        ]);

        $this->assertSame('dj', $site->vendor_type);

        app(CurrentSite::class)->set($site);

        $about = Page::query()->where('slug', 'about')->firstOrFail();
        $this->assertStringContainsString('read a room', $about->body);

        $blocks = HomepageSetting::query()->first()->allBlocks()->get()->keyBy('type');
        $this->assertStringContainsString('keeps your celebration moving', (string) $blocks['home_hero']->body);
        $this->assertSame('Nights worth replaying.', $blocks['home_portfolio']->heading);
    }

    public function test_unknown_vendor_type_falls_back_to_default(): void
    {
        $site = app(SiteProvisioner::class)->provision([
            'name' => 'Mystery',
            'vendor_type' => 'baker',
            'subdomain' => 'mystery',
            'admin_name' => 'Sam',
            'admin_email' => 'sam@mystery.test',
            'admin_password' => 'password1234',
        ]);

        $this->assertSame('photographer', $site->vendor_type);
    }

    public function test_signup_accepts_a_vendor_type(): void
    {
        config(['app.domain' => 'example.com']);

        $this->post('/start', [
            'name' => 'Vows Co',
            'vendor_type' => 'officiant',
            'subdomain' => 'vows',
            'admin_name' => 'Lee',
            'admin_email' => 'lee@vows.test',
            'admin_password' => 'password1234',
            'admin_password_confirmation' => 'password1234',
        ])->assertRedirect('http://vows.example.com/admin/login');

        $this->assertSame('officiant', Site::query()->where('subdomain', 'vows')->value('vendor_type'));
    }

    public function test_signup_rejects_an_invalid_vendor_type(): void
    {
        $this->post('/start', [
            'name' => 'X',
            'vendor_type' => 'baker',
            'subdomain' => 'bakery',
            'admin_name' => 'X',
            'admin_email' => 'x@example.com',
            'admin_password' => 'password1234',
            'admin_password_confirmation' => 'password1234',
        ])->assertSessionHasErrors('vendor_type');
    }

    public function test_create_tenant_command_accepts_a_type(): void
    {
        $exit = Artisan::call('tenant:create', [
            'name' => 'Officiant Co',
            'subdomain' => 'officiantco',
            'admin_email' => 'owner@officiantco.test',
            '--type' => 'officiant',
            '--password' => 'password1234',
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('sites', ['subdomain' => 'officiantco', 'vendor_type' => 'officiant']);
    }
}

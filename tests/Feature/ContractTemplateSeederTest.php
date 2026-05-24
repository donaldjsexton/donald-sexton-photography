<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\Site;
use App\Tenancy\CurrentSite;
use Database\Seeders\ContractTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_photographer_default_for_the_default_site(): void
    {
        $this->seed(ContractTemplateSeeder::class);

        app(CurrentSite::class)->set(Site::default());
        $defaults = ContractTemplate::where('is_default', true)->get();

        $this->assertCount(1, $defaults);
        $this->assertSame('Wedding Photography Agreement', $defaults->first()->name);
    }

    public function test_photographer_template_uses_online_gallery_and_fee_wording(): void
    {
        $this->seed(ContractTemplateSeeder::class);

        app(CurrentSite::class)->set(Site::default());
        $body = ContractTemplate::where('is_default', true)->first()->body;

        $this->assertStringContainsString('online gallery', $body);
        $this->assertStringContainsString('booking fee', $body);
        $this->assertStringNotContainsStringIgnoringCase('retainer', $body);
        $this->assertStringNotContainsStringIgnoringCase('security deposit', $body);
    }

    public function test_each_vendor_type_gets_its_own_scoped_default(): void
    {
        $dj = Site::factory()->create(['subdomain' => 'beatdrop', 'vendor_type' => 'dj']);

        $this->seed(ContractTemplateSeeder::class);

        app(CurrentSite::class)->set(Site::default());
        $photographerDefaults = ContractTemplate::where('is_default', true)->get();
        $this->assertCount(1, $photographerDefaults);
        $this->assertSame('Wedding Photography Agreement', $photographerDefaults->first()->name);

        app(CurrentSite::class)->set($dj);
        $djDefaults = ContractTemplate::where('is_default', true)->get();
        $this->assertCount(1, $djDefaults);
        $this->assertSame('DJ Services Agreement', $djDefaults->first()->name);
        $this->assertStringNotContainsStringIgnoringCase('online gallery', $djDefaults->first()->body);

        // Two defaults across the platform proves the unique index is per-site.
        $this->assertSame(2, ContractTemplate::withoutSiteScope()->where('is_default', true)->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(ContractTemplateSeeder::class);
        $this->seed(ContractTemplateSeeder::class);

        app(CurrentSite::class)->set(Site::default());

        $this->assertCount(1, ContractTemplate::where('name', 'Wedding Photography Agreement')->get());
        $this->assertCount(1, ContractTemplate::where('is_default', true)->get());
    }
}

<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use Database\Seeders\ContractTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_single_default_wedding_template(): void
    {
        $this->seed(ContractTemplateSeeder::class);

        $defaults = ContractTemplate::where('is_default', true)->get();

        $this->assertCount(1, $defaults);
        $this->assertSame('Wedding Photography Agreement', $defaults->first()->name);
    }

    public function test_template_uses_modern_online_gallery_and_fee_wording(): void
    {
        $this->seed(ContractTemplateSeeder::class);

        $body = ContractTemplate::where('is_default', true)->first()->body;

        $this->assertStringContainsString('online gallery', $body);
        $this->assertStringContainsString('booking fee', $body);
        $this->assertStringNotContainsStringIgnoringCase('retainer', $body);
        $this->assertStringNotContainsStringIgnoringCase('security deposit', $body);
        $this->assertStringNotContainsStringIgnoringCase('DVD/CD', $body);
        $this->assertStringContainsString('{{client_name}}', $body);
        $this->assertStringContainsString('{{event_date}}', $body);
    }

    public function test_seeding_replaces_an_existing_default(): void
    {
        $previous = ContractTemplate::factory()->default()->create(['name' => 'Old default']);

        $this->seed(ContractTemplateSeeder::class);

        $this->assertFalse($previous->fresh()->is_default);
        $this->assertCount(1, ContractTemplate::where('is_default', true)->get());
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(ContractTemplateSeeder::class);
        $this->seed(ContractTemplateSeeder::class);

        $this->assertCount(1, ContractTemplate::where('name', 'Wedding Photography Agreement')->get());
        $this->assertCount(1, ContractTemplate::where('is_default', true)->get());
    }
}

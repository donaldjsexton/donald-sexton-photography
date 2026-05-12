<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTemplateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.contract-templates.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_store_creates_template(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.contract-templates.store'), [
            'name' => 'Wedding Default',
            'title' => 'Wedding Photography Agreement',
            'body' => 'Hi {{client_name}}.',
            'is_default' => '1',
        ])->assertRedirect(route('admin.contract-templates.index'));

        $template = ContractTemplate::first();
        $this->assertNotNull($template);
        $this->assertSame('Wedding Default', $template->name);
        $this->assertTrue($template->is_default);
    }

    public function test_setting_default_clears_other_defaults(): void
    {
        $admin = User::factory()->create();
        $existing = ContractTemplate::factory()->default()->create(['name' => 'Old default']);

        $this->actingAs($admin)->post(route('admin.contract-templates.store'), [
            'name' => 'New default',
            'title' => 'Wedding Photography Agreement',
            'body' => 'Body',
            'is_default' => '1',
        ])->assertRedirect();

        $this->assertFalse($existing->fresh()->is_default);
        $this->assertTrue(ContractTemplate::where('name', 'New default')->first()->is_default);
    }

    public function test_update_replaces_template_content(): void
    {
        $admin = User::factory()->create();
        $template = ContractTemplate::factory()->create();

        $this->actingAs($admin)->put(route('admin.contract-templates.update', $template), [
            'name' => 'Renamed',
            'title' => 'Wedding Agreement v2',
            'body' => 'New body.',
        ])->assertRedirect(route('admin.contract-templates.index'));

        $template->refresh();
        $this->assertSame('Renamed', $template->name);
        $this->assertSame('New body.', $template->body);
    }

    public function test_destroy_removes_template(): void
    {
        $admin = User::factory()->create();
        $template = ContractTemplate::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.contract-templates.destroy', $template))
            ->assertRedirect(route('admin.contract-templates.index'));

        $this->assertDatabaseMissing('contract_templates', ['id' => $template->id]);
    }
}

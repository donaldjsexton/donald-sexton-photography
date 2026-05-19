<?php

namespace Tests\Feature;

use App\Mail\ContractSent;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContractAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.contracts.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_lists_contracts_and_filters_by_status(): void
    {
        $admin = User::factory()->create();
        $draft = Contract::factory()->create();
        $sent = Contract::factory()->sent()->create();

        $this->actingAs($admin)
            ->get(route('admin.contracts.index'))
            ->assertOk()
            ->assertSee($draft->number)
            ->assertSee($sent->number);

        $this->actingAs($admin)
            ->get(route('admin.contracts.index', ['status' => 'sent']))
            ->assertOk()
            ->assertSee($sent->number)
            ->assertDontSee($draft->number);
    }

    public function test_create_prefills_client_when_query_present(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.contracts.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee($client->displayName());
    }

    public function test_store_creates_contract(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.contracts.store'), [
            'billable_type' => 'client',
            'billable_id' => $client->id,
            'issue_date' => '2026-05-01',
            'expires_at' => '2026-06-01',
            'title' => 'Wedding Agreement',
            'body' => "Photography services covering the wedding day.\n\nTerms apply.",
        ]);

        $contract = Contract::first();
        $this->assertNotNull($contract);
        $response->assertRedirect(route('admin.contracts.show', $contract));

        $this->assertSame($client->id, $contract->billable_id);
        $this->assertSame(Client::class, $contract->billable_type);
        $this->assertSame('Wedding Agreement', $contract->title);
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNotEmpty($contract->uuid);
        $this->assertNotEmpty($contract->number);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.contracts.store'), [
                'billable_type' => 'client',
                'billable_id' => $client->id,
                'issue_date' => '2026-05-01',
            ])
            ->assertSessionHasErrors(['title', 'body']);
    }

    public function test_update_replaces_body_for_draft_contract(): void
    {
        $admin = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($admin)->put(route('admin.contracts.update', $contract), [
            'billable_type' => 'client',
            'billable_id' => $contract->billable_id,
            'issue_date' => '2026-05-01',
            'title' => 'Updated Title',
            'body' => 'Updated body content.',
        ])->assertRedirect(route('admin.contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('Updated Title', $contract->title);
        $this->assertSame('Updated body content.', $contract->body);
    }

    public function test_update_blocked_for_sent_contract(): void
    {
        $admin = User::factory()->create();
        $contract = Contract::factory()->sent()->create();
        $originalTitle = $contract->title;

        $response = $this->actingAs($admin)->put(route('admin.contracts.update', $contract), [
            'billable_type' => 'client',
            'billable_id' => $contract->billable_id,
            'issue_date' => '2026-05-01',
            'title' => 'Hacked',
            'body' => 'Hacked',
        ]);

        $response->assertForbidden();
        $this->assertSame($originalTitle, $contract->fresh()->title);
    }

    public function test_send_emails_contract_and_marks_sent(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.contracts.send', $contract))
            ->assertRedirect(route('admin.contracts.show', $contract));

        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->sent_at);
        Mail::assertSent(ContractSent::class);
    }

    public function test_void_marks_contract_as_void(): void
    {
        $admin = User::factory()->create();
        $contract = Contract::factory()->sent()->create();

        $this->actingAs($admin)
            ->post(route('admin.contracts.void', $contract))
            ->assertRedirect(route('admin.contracts.show', $contract));

        $this->assertSame(Contract::STATUS_VOID, $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->voided_at);
    }

    public function test_destroy_only_works_for_drafts(): void
    {
        $admin = User::factory()->create();
        $draft = Contract::factory()->create();
        $sent = Contract::factory()->sent()->create();

        $this->actingAs($admin)
            ->delete(route('admin.contracts.destroy', $draft))
            ->assertRedirect(route('admin.contracts.index'));
        $this->assertSoftDeleted($draft);

        $this->actingAs($admin)
            ->delete(route('admin.contracts.destroy', $sent))
            ->assertForbidden();
        $this->assertNotSoftDeleted($sent);
    }

    public function test_preview_resolves_merge_variables_for_template(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Maddie', 'last_name' => 'Q.']);
        $template = ContractTemplate::factory()->create([
            'body' => 'Hello {{client_name}}, contract {{contract_title}}.',
            'title' => 'Wedding Agreement',
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.contracts.preview'), [
            'template_id' => $template->id,
            'billable_type' => 'client',
            'billable_id' => $client->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('title', 'Wedding Agreement');
        $this->assertStringContainsString($client->displayName(), $response->json('body'));
        $this->assertStringContainsString('contract Wedding Agreement', $response->json('body'));
    }

    public function test_show_displays_contract(): void
    {
        $admin = User::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'title' => 'Wedding Coverage Agreement',
            'body' => 'Full-day coverage included.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contracts.show', $contract))
            ->assertOk()
            ->assertSee($contract->number)
            ->assertSee('Wedding Coverage Agreement')
            ->assertSee('Full-day coverage included.');
    }
}

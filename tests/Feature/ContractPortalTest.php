<?php

namespace Tests\Feature;

use App\Mail\ContractSigned;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContractPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_own_contracts_and_hides_drafts(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();

        $mine = Contract::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);
        $draft = Contract::factory()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);
        $theirs = Contract::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $other->id,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.contracts.index'))
            ->assertOk()
            ->assertSee($mine->number)
            ->assertDontSee($draft->number)
            ->assertDontSee($theirs->number);
    }

    public function test_show_marks_viewed_and_renders_body(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'viewed_at' => null,
            'body' => 'This contract covers wedding photography.',
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.contracts.show', ['contract' => $contract->uuid]))
            ->assertOk()
            ->assertSee('This contract covers wedding photography.');

        $this->assertNotNull($contract->fresh()->viewed_at);
    }

    public function test_show_404s_when_owned_by_other_client(): void
    {
        $client = Client::factory()->create();
        $stranger = Contract::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => Client::factory()->create()->id,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.contracts.show', ['contract' => $stranger->uuid]))
            ->assertNotFound();
    }

    public function test_show_404s_for_draft_contracts(): void
    {
        $client = Client::factory()->create();
        $draft = Contract::factory()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.contracts.show', ['contract' => $draft->uuid]))
            ->assertNotFound();
    }

    public function test_sign_records_signature_and_notifies_studio(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.sign', ['contract' => $contract->uuid]), [
                'signer_name' => 'Maddie Q.',
                'agreement' => '1',
            ])
            ->assertRedirect(route('portal.contracts.show', ['contract' => $contract->uuid]));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);
        $this->assertSame('Maddie Q.', $contract->signer_name);
        $this->assertNotNull($contract->signed_at);
        $this->assertNotNull($contract->signer_ip);
        Mail::assertSent(ContractSigned::class);
    }

    public function test_sign_requires_agreement_checkbox(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.sign', ['contract' => $contract->uuid]), [
                'signer_name' => 'Maddie Q.',
            ])
            ->assertSessionHasErrors(['agreement']);

        $this->assertNotSame(Contract::STATUS_SIGNED, $contract->fresh()->status);
    }

    public function test_sign_blocked_for_already_signed_contract(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->signed()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);
        $originalSigner = $contract->signer_name;

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.sign', ['contract' => $contract->uuid]), [
                'signer_name' => 'Imposter',
                'agreement' => '1',
            ])
            ->assertRedirect(route('portal.contracts.show', ['contract' => $contract->uuid]));

        $this->assertSame($originalSigner, $contract->fresh()->signer_name);
    }

    public function test_sign_blocked_when_offer_has_expired(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.sign', ['contract' => $contract->uuid]), [
                'signer_name' => 'Maddie Q.',
                'agreement' => '1',
            ])
            ->assertRedirect(route('portal.contracts.show', ['contract' => $contract->uuid]));

        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
    }

    public function test_routes_redirect_guests_to_portal_login(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);

        $this->get(route('portal.contracts.show', ['contract' => $contract->uuid]))
            ->assertRedirect(route('portal.login'));
    }
}

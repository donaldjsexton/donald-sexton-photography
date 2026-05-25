<?php

namespace Tests\Feature;

use App\Mail\ContractCountersigned;
use App\Models\Client;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContractCountersignTest extends TestCase
{
    use RefreshDatabase;

    private function signedContractFor(Client $client): Contract
    {
        return Contract::factory()->signed()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);
    }

    public function test_admin_can_countersign_a_signed_contract_and_client_is_emailed(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create(['email' => 'client@example.com']);
        $contract = $this->signedContractFor($client);

        $this->actingAs($admin)
            ->post(route('admin.contracts.countersign', $contract), [
                'countersigner_name' => 'Donald Sexton Photography',
                'agreement' => '1',
            ])
            ->assertRedirect(route('admin.contracts.show', $contract));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_COUNTERSIGNED, $contract->status);
        $this->assertTrue($contract->isCountersigned());
        $this->assertSame('Donald Sexton Photography', $contract->countersigner_name);
        $this->assertSame($admin->email, $contract->countersigner_email);
        $this->assertSame($admin->id, $contract->countersigned_by);
        $this->assertNotNull($contract->countersigner_ip);
        $this->assertNotNull($contract->countersigned_at);

        Mail::assertSent(ContractCountersigned::class, fn (ContractCountersigned $m) => $m->hasTo('client@example.com'));
    }

    public function test_countersign_requires_name_and_agreement(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $contract = $this->signedContractFor($client);

        $this->actingAs($admin)
            ->post(route('admin.contracts.countersign', $contract), [
                'countersigner_name' => '',
            ])
            ->assertSessionHasErrors(['countersigner_name', 'agreement']);

        $this->assertSame(Contract::STATUS_SIGNED, $contract->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_cannot_countersign_a_contract_that_is_not_yet_signed(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.countersign', $contract), [
                'countersigner_name' => 'Studio',
                'agreement' => '1',
            ])
            ->assertForbidden();

        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
    }

    public function test_cannot_countersign_an_already_countersigned_contract(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->countersigned()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.countersign', $contract), [
                'countersigner_name' => 'Studio Again',
                'agreement' => '1',
            ])
            ->assertForbidden();
    }

    public function test_countersign_requires_admin_authentication(): void
    {
        $client = Client::factory()->create();
        $contract = $this->signedContractFor($client);

        $this->post(route('admin.contracts.countersign', $contract), [
            'countersigner_name' => 'Studio',
            'agreement' => '1',
        ])->assertRedirect(route('admin.login'));

        $this->assertSame(Contract::STATUS_SIGNED, $contract->fresh()->status);
    }

    public function test_admin_show_offers_countersign_form_after_client_signs(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $contract = $this->signedContractFor($client);

        $this->actingAs($admin)
            ->get(route('admin.contracts.show', $contract))
            ->assertOk()
            ->assertSee('Counter-sign &amp; execute', false)
            ->assertSee('countersigner_name', false);
    }

    public function test_portal_shows_fully_executed_state_to_client(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->countersigned()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.contracts.show', ['contract' => $contract->uuid]))
            ->assertOk()
            ->assertSee('Fully executed')
            ->assertSee($contract->countersigner_name);
    }
}

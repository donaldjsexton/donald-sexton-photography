<?php

namespace Tests\Feature;

use App\Mail\ContractDeclined;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContractDeclineTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_decline_awaiting_contract(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.decline', ['contract' => $contract->uuid]), [
                'reason' => 'Scope no longer matches our event.',
            ])
            ->assertRedirect(route('portal.contracts.show', ['contract' => $contract->uuid]));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DECLINED, $contract->status);
        $this->assertNotNull($contract->declined_at);
        Mail::assertSent(ContractDeclined::class, fn (ContractDeclined $m) => $m->reason === 'Scope no longer matches our event.');
    }

    public function test_decline_rejected_when_contract_already_signed(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        $contract = Contract::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'status' => Contract::STATUS_SIGNED,
            'signed_at' => now()->subDay(),
        ]);

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.decline', ['contract' => $contract->uuid]))
            ->assertRedirect(route('portal.contracts.show', ['contract' => $contract->uuid]));

        $this->assertSame(Contract::STATUS_SIGNED, $contract->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_decline_404s_when_contract_belongs_to_another_client(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        $strangers = Contract::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => Client::factory()->create()->id,
        ]);

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.decline', ['contract' => $strangers->uuid]))
            ->assertNotFound();
    }

    public function test_decline_validates_reason_length(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.decline', ['contract' => $contract->uuid]), [
                'reason' => str_repeat('x', 2001),
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
    }
}

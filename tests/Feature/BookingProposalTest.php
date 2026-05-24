<?php

namespace Tests\Feature;

use App\Mail\PortalInvite;
use App\Mail\ProposalSent;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingProposalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Client, 1: Contract, 2: Invoice}
     */
    private function makeProposal(string $contractState = 'draft', string $invoiceStatus = Invoice::STATUS_DRAFT): array
    {
        $client = Client::factory()->create(['email' => 'sarah@example.com']);
        $invoice = Invoice::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'status' => $invoiceStatus,
            'total_cents' => 500000,
        ]);

        $factory = Contract::factory();
        if ($contractState !== 'draft') {
            $factory = $factory->{$contractState}();
        }

        $contract = $factory->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'invoice_id' => $invoice->id,
        ]);

        return [$client, $contract, $invoice];
    }

    public function test_send_proposal_marks_contract_and_invoice_sent_and_emails(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        [, $contract, $invoice] = $this->makeProposal();

        $this->actingAs($admin)
            ->post(route('admin.contracts.send-proposal', $contract))
            ->assertRedirect(route('admin.contracts.show', $contract));

        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->sent_at);
        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->sent_at);
        Mail::assertSent(ProposalSent::class, fn (ProposalSent $m) => $m->hasTo('sarah@example.com'));
    }

    public function test_send_proposal_includes_portal_invite_for_client_without_access(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        [$client, $contract] = $this->makeProposal();

        $this->actingAs($admin)
            ->post(route('admin.contracts.send-proposal', $contract))
            ->assertRedirect(route('admin.contracts.show', $contract));

        Mail::assertSent(ProposalSent::class);
        Mail::assertSent(PortalInvite::class, fn (PortalInvite $m) => $m->hasTo('sarah@example.com') && $m->client->is($client));
    }

    public function test_send_proposal_skips_portal_invite_when_client_already_has_access(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->withPortalAccess()->create(['email' => 'sarah@example.com']);
        $invoice = Invoice::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);
        $contract = Contract::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'invoice_id' => $invoice->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.send-proposal', $contract))
            ->assertRedirect(route('admin.contracts.show', $contract));

        Mail::assertSent(ProposalSent::class);
        Mail::assertNotSent(PortalInvite::class);
    }

    public function test_send_proposal_rejected_without_linked_invoice(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.send-proposal', $contract))
            ->assertForbidden();

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    public function test_send_proposal_requires_client_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create(['email' => '']);
        $invoice = Invoice::factory()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);
        $contract = Contract::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'invoice_id' => $invoice->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.send-proposal', $contract));

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_portal_proposal_page_shows_both_steps(): void
    {
        [$client, $contract, $invoice] = $this->makeProposal('sent', Invoice::STATUS_SENT);

        $this->actingAs($client, 'client')
            ->get(route('portal.proposals.show', ['contract' => $contract->uuid]))
            ->assertOk()
            ->assertSee('Step 1')
            ->assertSee('Step 2')
            ->assertSee($contract->title)
            ->assertSee($invoice->number)
            ->assertSee('Sign the agreement above to unlock payment');
    }

    public function test_portal_proposal_404s_for_non_proposal_contract(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.proposals.show', ['contract' => $contract->uuid]))
            ->assertNotFound();
    }

    public function test_signing_a_proposal_redirects_to_proposal_page(): void
    {
        Mail::fake();
        [$client, $contract] = $this->makeProposal('sent', Invoice::STATUS_SENT);

        $this->actingAs($client, 'client')
            ->post(route('portal.contracts.sign', ['contract' => $contract->uuid]), [
                'signer_name' => 'Sarah Lee',
                'agreement' => '1',
            ])
            ->assertRedirect(route('portal.proposals.show', ['contract' => $contract->uuid]));

        $this->assertSame(Contract::STATUS_SIGNED, $contract->fresh()->status);
    }

    public function test_proposal_page_unlocks_payment_after_signing(): void
    {
        [$client, $contract, $invoice] = $this->makeProposal('signed', Invoice::STATUS_SENT);

        $this->actingAs($client, 'client')
            ->get(route('portal.proposals.show', ['contract' => $contract->uuid]))
            ->assertOk()
            ->assertSee(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertDontSee('Sign the agreement above to unlock payment');
    }

    public function test_proposal_page_redirects_guest_to_login(): void
    {
        [, $contract] = $this->makeProposal('sent', Invoice::STATUS_SENT);

        $this->get(route('portal.proposals.show', ['contract' => $contract->uuid]))
            ->assertRedirect(route('portal.login'));
    }
}

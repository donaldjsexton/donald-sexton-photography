<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_allowed_only_for_draft_contracts(): void
    {
        $user = User::factory()->create();
        $draft = Contract::factory()->create();
        $sent = Contract::factory()->sent()->create();

        $this->assertTrue($user->can('update', $draft));
        $this->assertFalse($user->can('update', $sent));
    }

    public function test_delete_allowed_only_for_draft_contracts(): void
    {
        $user = User::factory()->create();
        $draft = Contract::factory()->create();
        $sent = Contract::factory()->sent()->create();

        $this->assertTrue($user->can('delete', $draft));
        $this->assertFalse($user->can('delete', $sent));
    }

    public function test_send_blocked_for_signed_or_void_contracts(): void
    {
        $user = User::factory()->create();
        $void = Contract::factory()->create(['status' => Contract::STATUS_VOID]);
        $signed = Contract::factory()->create(['status' => Contract::STATUS_SIGNED]);
        $draft = Contract::factory()->create();
        $sent = Contract::factory()->sent()->create();

        $this->assertFalse($user->can('send', $void));
        $this->assertFalse($user->can('send', $signed));
        $this->assertTrue($user->can('send', $draft));
        $this->assertTrue($user->can('send', $sent));
    }

    public function test_send_proposal_requires_attached_invoice_in_sendable_state(): void
    {
        $user = User::factory()->create();

        $plainContract = Contract::factory()->create();
        $this->assertFalse($user->can('sendProposal', $plainContract));

        $voidInvoice = Invoice::factory()->void()->create();
        $proposalWithVoid = Contract::factory()->create(['invoice_id' => $voidInvoice->id]);
        $this->assertFalse($user->can('sendProposal', $proposalWithVoid));

        $paidInvoice = Invoice::factory()->sent()->create([
            'total_cents' => 10000,
            'amount_paid_cents' => 10000,
        ]);
        $proposalWithPaid = Contract::factory()->create(['invoice_id' => $paidInvoice->id]);
        $this->assertFalse($user->can('sendProposal', $proposalWithPaid));

        $invoice = Invoice::factory()->create();
        $proposal = Contract::factory()->create(['invoice_id' => $invoice->id]);
        $this->assertTrue($user->can('sendProposal', $proposal));
    }

    public function test_void_blocked_for_already_void_contracts(): void
    {
        $user = User::factory()->create();
        $void = Contract::factory()->create(['status' => Contract::STATUS_VOID]);
        $sent = Contract::factory()->sent()->create();

        $this->assertFalse($user->can('void', $void));
        $this->assertTrue($user->can('void', $sent));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_allowed_only_for_draft_invoices(): void
    {
        $user = User::factory()->create();
        $draft = Invoice::factory()->create();
        $sent = Invoice::factory()->sent()->create();

        $this->assertTrue($user->can('update', $draft));
        $this->assertFalse($user->can('update', $sent));
    }

    public function test_delete_allowed_only_for_draft_invoices(): void
    {
        $user = User::factory()->create();
        $draft = Invoice::factory()->create();
        $sent = Invoice::factory()->sent()->create();

        $this->assertTrue($user->can('delete', $draft));
        $this->assertFalse($user->can('delete', $sent));
    }

    public function test_send_blocked_for_void_or_paid_invoices(): void
    {
        $user = User::factory()->create();
        $void = Invoice::factory()->void()->create();
        $draft = Invoice::factory()->create();
        $sent = Invoice::factory()->sent()->create();

        $this->assertFalse($user->can('send', $void));
        $this->assertTrue($user->can('send', $draft));
        $this->assertTrue($user->can('send', $sent));
    }

    public function test_void_blocked_for_already_void_invoices(): void
    {
        $user = User::factory()->create();
        $void = Invoice::factory()->void()->create();
        $sent = Invoice::factory()->sent()->create();

        $this->assertFalse($user->can('void', $void));
        $this->assertTrue($user->can('void', $sent));
    }

    public function test_record_payment_blocked_for_draft_void_or_paid_invoices(): void
    {
        $user = User::factory()->create();
        $draft = Invoice::factory()->create();
        $void = Invoice::factory()->void()->create();
        $sent = Invoice::factory()->sent()->create(['total_cents' => 10000, 'amount_paid_cents' => 0]);
        $paid = Invoice::factory()->sent()->create(['total_cents' => 10000, 'amount_paid_cents' => 10000]);

        $this->assertFalse($user->can('recordPayment', $draft));
        $this->assertFalse($user->can('recordPayment', $void));
        $this->assertFalse($user->can('recordPayment', $paid));
        $this->assertTrue($user->can('recordPayment', $sent));
    }
}

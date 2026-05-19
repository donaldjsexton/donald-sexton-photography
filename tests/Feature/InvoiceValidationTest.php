<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceValidationTest extends TestCase
{
    use RefreshDatabase;

    private function basePayload(Client $client, array $overrides = []): array
    {
        return array_replace_recursive([
            'billable_type' => 'client',
            'billable_id' => $client->id,
            'issue_date' => '2026-05-01',
            'discount' => 0,
            'line_items' => [
                ['description' => 'Coverage', 'quantity' => 1, 'unit_price' => 500.00, 'tax_rate' => 0],
            ],
        ], $overrides);
    }

    public function test_store_rejects_zero_total_invoice(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->basePayload($client, [
                'line_items' => [
                    ['description' => 'Free', 'quantity' => 1, 'unit_price' => 0, 'tax_rate' => 0],
                ],
            ]))
            ->assertSessionHasErrors('line_items');
    }

    public function test_store_rejects_discount_greater_than_subtotal(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->basePayload($client, [
                'discount' => 600.00,
            ]))
            ->assertSessionHasErrors('discount');
    }

    public function test_store_rejects_installments_exceeding_total(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->basePayload($client, [
                'installments' => [
                    ['label' => 'Deposit', 'amount' => 400.00, 'due_date' => '2026-05-15'],
                    ['label' => 'Final', 'amount' => 400.00, 'due_date' => '2026-06-15'],
                ],
            ]))
            ->assertSessionHasErrors('installments');
    }

    public function test_store_rejects_installment_due_before_issue_date(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->basePayload($client, [
                'installments' => [
                    ['label' => 'Deposit', 'amount' => 100.00, 'due_date' => '2026-04-15'],
                ],
            ]))
            ->assertSessionHasErrors('installments.0.due_date');
    }

    public function test_store_accepts_valid_payload_with_installments_within_total(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.store'), $this->basePayload($client, [
                'installments' => [
                    ['label' => 'Deposit', 'amount' => 250.00, 'due_date' => '2026-05-15'],
                    ['label' => 'Final', 'amount' => 250.00, 'due_date' => '2026-06-15'],
                ],
            ]))
            ->assertSessionHasNoErrors();
    }
}

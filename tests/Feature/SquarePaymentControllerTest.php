<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\SquareGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SquarePaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unauthenticated_clients_cannot_pay(): void
    {
        $invoice = Invoice::factory()->sent()->create();

        $this->post(route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]), [
            'source_id' => 'cnon:1',
        ])->assertRedirect(route('portal.login'));
    }

    public function test_charge_creates_payment_and_marks_invoice_paid(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'currency' => 'USD',
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('mode')->andReturn('sandbox');
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn(PaymentResult::completed(
                    gatewayPaymentId: 'sq-pay-123',
                    payload: ['payment' => ['id' => 'sq-pay-123']],
                    gatewayOrderId: 'sq-order-1',
                ));
        });

        $this->actingAs($client, 'client')
            ->post(route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]), [
                'source_id' => 'cnon:abc',
                'verification_token' => 'verify-token',
            ])
            ->assertRedirect(route('portal.invoices.show', ['invoice' => $invoice->uuid]));

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);
        $this->assertSame('sq-pay-123', $payment->gateway_payment_id);
        $this->assertSame(Payment::GATEWAY_SQUARE, $payment->gateway);
        $this->assertSame(50000, $payment->amount_cents);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status);

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_charge_falls_through_with_flash_when_gateway_fails(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 50000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('mode')->andReturn('sandbox');
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn(PaymentResult::failed('Card declined.'));
        });

        $this->actingAs($client, 'client')
            ->from(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->post(route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]), [
                'source_id' => 'cnon:bad',
            ])
            ->assertRedirect(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertSessionHas('status');

        $this->assertSame(0, $invoice->fresh()->payments()->count());
        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);
    }

    public function test_charge_404s_for_other_clients_invoice(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $stranger = Invoice::factory()->sent()->create(['billable_type' => Client::class, 'billable_id' => $other->id, 'total_cents' => 10000]);

        $this->bindGatewayMock(fn ($mock) => $mock->shouldReceive('isConfigured')->andReturnTrue());

        $this->actingAs($client, 'client')
            ->post(route('portal.invoices.pay.square', ['invoice' => $stranger->uuid]), [
                'source_id' => 'cnon:abc',
            ])
            ->assertNotFound();
    }

    public function test_charge_redirects_when_gateway_not_configured(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create(['billable_type' => Client::class, 'billable_id' => $client->id]);

        $this->bindGatewayMock(fn ($mock) => $mock->shouldReceive('isConfigured')->andReturnFalse());

        $this->actingAs($client, 'client')
            ->from(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->post(route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]), [
                'source_id' => 'cnon:abc',
            ])
            ->assertRedirect(route('portal.invoices.show', ['invoice' => $invoice->uuid]));

        $this->assertSame(0, $invoice->fresh()->payments()->count());
    }

    public function test_charge_marks_installment_paid_when_balance_was_one_installment(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 30000,
            'amount_paid_cents' => 0,
        ]);
        $installment = InvoiceInstallment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 30000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('mode')->andReturn('sandbox');
            $mock->shouldReceive('charge')->once()->andReturn(PaymentResult::completed('sq-pay-99'));
        });

        $this->actingAs($client, 'client')
            ->post(route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]), [
                'source_id' => 'cnon:abc',
            ])
            ->assertRedirect();

        $this->assertSame(InvoiceInstallment::STATUS_PAID, $installment->fresh()->status);
    }

    public function test_validation_requires_source_id(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create(['billable_type' => Client::class, 'billable_id' => $client->id]);

        $this->actingAs($client, 'client')
            ->post(route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]), [])
            ->assertSessionHasErrors(['source_id']);
    }

    public function test_portal_show_renders_card_form_when_square_configured(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 50000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('applicationId')->andReturn('app-id-from-test');
            $mock->shouldReceive('locationId')->andReturn('loc-id-from-test');
            $mock->shouldReceive('isLive')->andReturnFalse();
            $mock->shouldReceive('mode')->andReturn('sandbox');
        });

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertOk()
            ->assertSee('Pay with card')
            ->assertSee('app-id-from-test', false)
            ->assertSee('loc-id-from-test', false)
            ->assertSee('sandbox.web.squarecdn.com', false);
    }

    public function test_portal_show_hides_card_form_when_not_configured(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 50000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnFalse();
            $mock->shouldReceive('applicationId')->andReturnNull();
            $mock->shouldReceive('locationId')->andReturnNull();
            $mock->shouldReceive('isLive')->andReturnFalse();
        });

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertOk()
            ->assertDontSee('Pay with card')
            ->assertSee('Online payments coming soon');
    }

    private function bindGatewayMock(callable $configure): void
    {
        $mock = Mockery::mock(SquareGateway::class);
        $configure($mock);
        $this->app->instance(SquareGateway::class, $mock);
    }
}

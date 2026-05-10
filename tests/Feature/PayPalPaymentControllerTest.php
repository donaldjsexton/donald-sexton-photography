<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PayPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PayPalPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_order_requires_auth(): void
    {
        $invoice = Invoice::factory()->sent()->create();

        $this->postJson(route('portal.invoices.pay.paypal.create', ['invoice' => $invoice->uuid]))
            ->assertStatus(401);
    }

    public function test_create_order_returns_order_id_when_gateway_succeeds(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 50000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createOrder')->once()->andReturn(['order_id' => 'PP-ORDER-1']);
        });

        $this->actingAs($client, 'client')
            ->postJson(route('portal.invoices.pay.paypal.create', ['invoice' => $invoice->uuid]))
            ->assertOk()
            ->assertExactJson(['order_id' => 'PP-ORDER-1']);
    }

    public function test_create_order_returns_422_when_gateway_failed(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create(['billable_type' => Client::class, 'billable_id' => $client->id]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createOrder')->once()->andReturn(PaymentResult::failed('Boom.'));
        });

        $this->actingAs($client, 'client')
            ->postJson(route('portal.invoices.pay.paypal.create', ['invoice' => $invoice->uuid]))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Boom.']);
    }

    public function test_create_order_returns_422_when_not_configured(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create(['billable_type' => Client::class, 'billable_id' => $client->id]);

        $this->bindGatewayMock(fn ($mock) => $mock->shouldReceive('isConfigured')->andReturnFalse());

        $this->actingAs($client, 'client')
            ->postJson(route('portal.invoices.pay.paypal.create', ['invoice' => $invoice->uuid]))
            ->assertStatus(422);
    }

    public function test_create_order_404s_for_other_clients_invoice(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $stranger = Invoice::factory()->sent()->create(['billable_type' => Client::class, 'billable_id' => $other->id]);

        $this->bindGatewayMock(fn ($mock) => $mock->shouldReceive('isConfigured')->andReturnTrue());

        $this->actingAs($client, 'client')
            ->postJson(route('portal.invoices.pay.paypal.create', ['invoice' => $stranger->uuid]))
            ->assertNotFound();
    }

    public function test_capture_creates_payment_and_marks_invoice_paid(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 50000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('mode')->andReturn('sandbox');
            $mock->shouldReceive('captureOrder')
                ->once()
                ->andReturn(PaymentResult::completed(
                    gatewayPaymentId: 'PP-CAPTURE-1',
                    payload: ['order' => ['id' => 'PP-ORDER-1']],
                    gatewayOrderId: 'PP-ORDER-1',
                ));
        });

        $this->actingAs($client, 'client')
            ->postJson(route('portal.invoices.pay.paypal.capture', ['invoice' => $invoice->uuid]), [
                'order_id' => 'PP-ORDER-1',
            ])
            ->assertOk()
            ->assertJsonFragment(['status' => 'ok']);

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);
        $this->assertSame(Payment::GATEWAY_PAYPAL, $payment->gateway);
        $this->assertSame('PP-CAPTURE-1', $payment->gateway_payment_id);
        $this->assertSame('PP-ORDER-1', $payment->gateway_order_id);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_capture_marks_installments_paid_when_invoice_fully_paid(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 30000,
        ]);
        $installment = InvoiceInstallment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 30000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('mode')->andReturn('sandbox');
            $mock->shouldReceive('captureOrder')->once()->andReturn(PaymentResult::completed('PP-CAP'));
        });

        $this->actingAs($client, 'client')
            ->postJson(route('portal.invoices.pay.paypal.capture', ['invoice' => $invoice->uuid]), [
                'order_id' => 'PP-ORDER',
            ])
            ->assertOk();

        $this->assertSame(InvoiceInstallment::STATUS_PAID, $installment->fresh()->status);
    }

    public function test_capture_returns_422_when_gateway_fails(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 50000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('captureOrder')->once()->andReturn(PaymentResult::failed('Card declined.'));
        });

        $this->actingAs($client, 'client')
            ->postJson(route('portal.invoices.pay.paypal.capture', ['invoice' => $invoice->uuid]), [
                'order_id' => 'PP-ORDER-FAIL',
            ])
            ->assertStatus(422);

        $this->assertSame(0, $invoice->fresh()->payments()->count());
    }

    public function test_capture_validates_order_id(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create(['billable_type' => Client::class, 'billable_id' => $client->id]);

        $this->actingAs($client, 'client')
            ->postJson(route('portal.invoices.pay.paypal.capture', ['invoice' => $invoice->uuid]), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
    }

    public function test_portal_show_renders_paypal_button_when_configured(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 50000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('jsSdkUrl')->andReturn('https://www.paypal.com/sdk/js?client-id=app-id-from-test&currency=USD&intent=capture');
        });

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertOk()
            ->assertSee('Pay with PayPal')
            ->assertSee('paypal-button-container', false)
            ->assertSee('client-id=app-id-from-test', false);
    }

    public function test_portal_show_hides_paypal_button_when_not_configured(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'total_cents' => 50000,
        ]);

        $this->bindGatewayMock(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnFalse();
            $mock->shouldReceive('jsSdkUrl')->andReturn('');
        });

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertOk()
            ->assertDontSee('Pay with PayPal');
    }

    private function bindGatewayMock(callable $configure): void
    {
        $mock = Mockery::mock(PayPalGateway::class);
        $configure($mock);
        $this->app->instance(PayPalGateway::class, $mock);
    }
}

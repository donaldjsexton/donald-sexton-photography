<?php

namespace Tests\Feature;

use App\Mail\InvoicePaid;
use App\Mail\InvoicePaidAdminNotification;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\SquareGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class InvoicePaidNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_marking_invoice_paid_sends_receipt_to_client(): void
    {
        Mail::fake();
        config(['payments.business.email' => 'studio@example.test']);

        $client = Client::factory()->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
        ]);

        $invoice->forceFill(['status' => Invoice::STATUS_PAID, 'paid_at' => now()])->save();

        Mail::assertSent(InvoicePaid::class, function (InvoicePaid $mail) use ($invoice) {
            return $mail->hasTo('client@example.com')
                && $mail->invoice->is($invoice)
                && str_contains($mail->viewUrl, $invoice->uuid);
        });
    }

    public function test_marking_invoice_paid_sends_admin_notification(): void
    {
        Mail::fake();
        config(['payments.business.email' => 'studio@example.test']);

        $client = Client::factory()->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 25000,
        ]);

        $invoice->forceFill(['status' => Invoice::STATUS_PAID, 'paid_at' => now()])->save();

        Mail::assertSent(InvoicePaidAdminNotification::class, function (InvoicePaidAdminNotification $mail) use ($invoice) {
            return $mail->hasTo('studio@example.test')
                && $mail->invoice->is($invoice);
        });
    }

    public function test_admin_notification_falls_back_to_mail_from_address(): void
    {
        Mail::fake();
        config(['payments.business.email' => null]);
        config(['mail.from.address' => 'from@example.test']);

        $invoice = Invoice::factory()->sent()->create([
            'total_cents' => 10000,
        ]);

        $invoice->forceFill(['status' => Invoice::STATUS_PAID, 'paid_at' => now()])->save();

        Mail::assertSent(InvoicePaidAdminNotification::class, fn ($mail) => $mail->hasTo('from@example.test'));
    }

    public function test_does_not_send_when_status_changes_to_non_paid(): void
    {
        Mail::fake();

        $invoice = Invoice::factory()->sent()->create(['total_cents' => 10000]);

        $invoice->forceFill(['status' => Invoice::STATUS_PARTIALLY_PAID])->save();

        Mail::assertNothingSent();
    }

    public function test_does_not_send_when_invoice_was_already_paid(): void
    {
        Mail::fake();

        $invoice = Invoice::factory()->paid()->create(['total_cents' => 10000]);

        $invoice->forceFill(['notes' => 'updated notes'])->save();

        Mail::assertNothingSent();
    }

    public function test_skips_client_email_when_billable_has_no_email(): void
    {
        Mail::fake();
        config(['payments.business.email' => 'studio@example.test']);

        $client = Client::factory()->create(['email' => '']);
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 10000,
        ]);

        $invoice->forceFill(['status' => Invoice::STATUS_PAID, 'paid_at' => now()])->save();

        Mail::assertNotSent(InvoicePaid::class);
        Mail::assertSent(InvoicePaidAdminNotification::class);
    }

    public function test_portal_square_payment_triggers_notifications(): void
    {
        Mail::fake();
        config(['payments.business.email' => 'studio@example.test']);

        $client = Client::factory()->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'currency' => 'USD',
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
        ]);

        $mock = Mockery::mock(SquareGateway::class);
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('mode')->andReturn('sandbox');
        $mock->shouldReceive('charge')->once()->andReturn(PaymentResult::completed('sq-pay-7'));
        $this->app->instance(SquareGateway::class, $mock);

        $this->actingAs($client, 'client')
            ->post(route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]), [
                'source_id' => 'cnon:abc',
            ])
            ->assertRedirect();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);

        Mail::assertSent(InvoicePaid::class, fn ($mail) => $mail->hasTo('client@example.com'));
        Mail::assertSent(InvoicePaidAdminNotification::class, fn ($mail) => $mail->hasTo('studio@example.test'));
    }

    public function test_admin_manual_record_payment_triggers_notifications(): void
    {
        Mail::fake();
        config(['payments.business.email' => 'studio@example.test']);

        $admin = User::factory()->create();
        $client = Client::factory()->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 25000,
            'amount_paid_cents' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => 250.00,
                'gateway' => Payment::GATEWAY_MANUAL,
            ])
            ->assertRedirect();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);

        Mail::assertSent(InvoicePaid::class, fn ($mail) => $mail->hasTo('client@example.com'));
        Mail::assertSent(InvoicePaidAdminNotification::class, fn ($mail) => $mail->hasTo('studio@example.test'));
    }

    public function test_webhook_marking_payment_paid_triggers_notifications(): void
    {
        Mail::fake();
        config(['payments.business.email' => 'studio@example.test']);

        $client = Client::factory()->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'currency' => 'USD',
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
        ]);

        $invoice->payments()->create([
            'gateway' => Payment::GATEWAY_SQUARE,
            'mode' => 'sandbox',
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => 50000,
            'currency' => 'USD',
            'gateway_payment_id' => 'sq-hook-1',
        ]);

        $mock = Mockery::mock(SquareGateway::class);
        $mock->shouldReceive('verifyWebhookSignature')->andReturnTrue();
        $this->app->instance(SquareGateway::class, $mock);

        $payload = [
            'type' => 'payment.updated',
            'data' => [
                'object' => [
                    'payment' => [
                        'id' => 'sq-hook-1',
                        'status' => 'COMPLETED',
                    ],
                ],
            ],
        ];

        $this->postJson(route('webhooks.square'), $payload, [
            'x-square-hmacsha256-signature' => 'whatever',
        ])->assertOk();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);

        Mail::assertSent(InvoicePaid::class, fn ($mail) => $mail->hasTo('client@example.com'));
        Mail::assertSent(InvoicePaidAdminNotification::class, fn ($mail) => $mail->hasTo('studio@example.test'));
    }
}

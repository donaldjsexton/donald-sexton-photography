<?php

namespace Tests\Feature;

use App\Mail\InvoiceSent;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class InvoiceMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_dispatches_mailable_to_client(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.send', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice));

        Mail::assertSent(InvoiceSent::class, function (InvoiceSent $mail) use ($invoice) {
            return $mail->hasTo('client@example.com')
                && $mail->invoice->is($invoice)
                && str_contains($mail->payUrl, $invoice->uuid);
        });
    }

    public function test_send_blocked_when_client_has_no_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create(['email' => '']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.send', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice));

        Mail::assertNothingSent();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status);
    }

    public function test_resend_works_for_already_sent_invoice(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->sent()->create();
        $originalSentAt = $invoice->sent_at;

        $this->actingAs($admin)
            ->post(route('admin.invoices.send', $invoice))
            ->assertRedirect();

        Mail::assertSent(InvoiceSent::class);
        $this->assertSame($originalSentAt->toDateTimeString(), $invoice->fresh()->sent_at->toDateTimeString());
    }

    public function test_send_blocked_for_void_invoice(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->void()->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.send', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice));

        Mail::assertNothingSent();
    }

    public function test_mailable_subject_includes_invoice_number_and_brand(): void
    {
        config(['payments.business.name' => 'Test Studio']);
        $invoice = Invoice::factory()->create();

        $mailable = new InvoiceSent($invoice, 'https://example.test/pay');

        $this->assertSame(
            'Invoice '.$invoice->number.' from Test Studio',
            $mailable->envelope()->subject,
        );
    }

    public function test_mailable_content_passes_pay_url_and_invoice_to_view(): void
    {
        $invoice = Invoice::factory()->create();
        $mailable = new InvoiceSent($invoice, 'https://example.test/pay/abc');

        $content = $mailable->content();

        $this->assertSame('emails.invoices.sent', $content->view);
        $this->assertSame('https://example.test/pay/abc', $content->with['payUrl']);
        $this->assertTrue($content->with['invoice']->is($invoice));
    }

    public function test_mailable_attaches_pdf(): void
    {
        Pdf::fake();
        $invoice = Invoice::factory()->create();

        $mailable = new InvoiceSent($invoice, 'https://example.test/pay');
        $attachments = $mailable->attachments();

        $this->assertCount(1, $attachments);
    }
}

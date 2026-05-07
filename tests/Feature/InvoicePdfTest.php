<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pdf_route_requires_auth(): void
    {
        $invoice = Invoice::factory()->create();

        $this->get(route('admin.invoices.pdf', $invoice))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_pdf_route_invokes_renderer(): void
    {
        Pdf::fake();
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.invoices.pdf', $invoice))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->viewName === 'invoices.pdf');
    }

    public function test_public_show_requires_signed_url(): void
    {
        $invoice = Invoice::factory()->create();

        $this->get(route('invoices.public.show', ['invoice' => $invoice->uuid]))
            ->assertForbidden();
    }

    public function test_public_show_with_signed_url_displays_invoice_and_marks_viewed(): void
    {
        $invoice = Invoice::factory()->create(['viewed_at' => null]);
        $invoice->lineItems()->create([
            'sort_order' => 0,
            'description' => 'Coverage',
            'quantity' => 1,
            'unit_price_cents' => 10000,
            'tax_rate' => 0,
        ]);

        $url = URL::temporarySignedRoute(
            'invoices.public.show',
            now()->addDays(30),
            ['invoice' => $invoice->uuid],
        );

        $this->get($url)
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Coverage');

        $this->assertNotNull($invoice->fresh()->viewed_at);
    }

    public function test_public_pdf_with_signed_url_invokes_renderer(): void
    {
        Pdf::fake();
        $invoice = Invoice::factory()->create();

        $url = URL::temporarySignedRoute(
            'invoices.public.pdf',
            now()->addDays(30),
            ['invoice' => $invoice->uuid],
        );

        $this->get($url)->assertOk();

        Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->viewName === 'invoices.pdf');
    }

    public function test_renderer_filename_uses_invoice_number(): void
    {
        $invoice = Invoice::factory()->create();
        $renderer = new InvoicePdfRenderer;

        $this->assertSame('invoice-'.$invoice->number.'.pdf', $renderer->filename($invoice));
    }

    public function test_renderer_view_data_includes_brand_and_pay_url(): void
    {
        config(['payments.business.name' => 'Test Studio']);
        $invoice = Invoice::factory()->create();

        $data = (new InvoicePdfRenderer)->viewData($invoice);

        $this->assertSame('Test Studio', $data['brandName']);
        $this->assertSame($invoice->id, $data['invoice']->id);
        $this->assertStringContainsString($invoice->uuid, $data['payUrl']);
        $this->assertStringContainsString('signature=', $data['payUrl']);
    }
}

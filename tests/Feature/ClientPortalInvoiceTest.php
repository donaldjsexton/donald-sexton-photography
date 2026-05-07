<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class ClientPortalInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summarises_outstanding_balance_and_next_installment(): void
    {
        $client = Client::factory()->create();
        $sentInvoice = Invoice::factory()->sent()->create([
            'client_id' => $client->id,
            'total_cents' => 100000,
            'amount_paid_cents' => 30000,
        ]);
        InvoiceInstallment::factory()->create([
            'invoice_id' => $sentInvoice->id,
            'amount_cents' => 70000,
            'due_date' => now()->addDays(7),
            'label' => 'Final balance',
        ]);
        Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Hi, '.$client->first_name)
            ->assertSee('$700.00')
            ->assertSee('Final balance')
            ->assertSee($sentInvoice->number);
    }

    public function test_dashboard_hides_draft_invoices(): void
    {
        $client = Client::factory()->create();
        $draft = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee($draft->number);
    }

    public function test_invoices_index_lists_only_own_invoices(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();

        $mine = Invoice::factory()->sent()->create(['client_id' => $client->id]);
        $theirs = Invoice::factory()->sent()->create(['client_id' => $other->id]);

        $response = $this->actingAs($client, 'client')->get(route('portal.invoices.index'));

        $response->assertOk();
        $response->assertSee($mine->number);
        $response->assertDontSee($theirs->number);
    }

    public function test_invoice_show_marks_viewed_at_and_renders(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'client_id' => $client->id,
            'viewed_at' => null,
        ]);
        $invoice->lineItems()->create([
            'sort_order' => 0,
            'description' => 'Coverage',
            'quantity' => 1,
            'unit_price_cents' => 50000,
            'tax_rate' => 0,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertOk()
            ->assertSee('Coverage')
            ->assertSee($invoice->number);

        $this->assertNotNull($invoice->fresh()->viewed_at);
    }

    public function test_invoice_show_404s_when_invoice_belongs_to_other_client(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $stranger = Invoice::factory()->sent()->create(['client_id' => $other->id]);

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $stranger->uuid]))
            ->assertNotFound();
    }

    public function test_invoice_show_404s_for_draft_invoices(): void
    {
        $client = Client::factory()->create();
        $draft = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $draft->uuid]))
            ->assertNotFound();
    }

    public function test_invoice_pdf_route_invokes_renderer_for_owner(): void
    {
        Pdf::fake();
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create(['client_id' => $client->id]);

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.pdf', ['invoice' => $invoice->uuid]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->viewName === 'invoices.pdf');
    }

    public function test_invoice_routes_redirect_guests_to_portal_login(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create(['client_id' => $client->id]);

        $this->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertRedirect(route('portal.login'));
    }
}

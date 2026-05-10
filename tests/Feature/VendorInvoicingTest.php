<?php

namespace Tests\Feature;

use App\Mail\InvoiceSent;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VendorInvoicingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_invoice_for_a_venue(): void
    {
        $admin = User::factory()->create();
        $venue = Venue::factory()->billable()->create([
            'name' => 'Hidden Bay Estate',
            'business_name' => 'Hidden Bay Events LLC',
        ]);

        $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'billable_type' => 'venue',
            'billable_id' => $venue->id,
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-31',
            'net_terms' => 'Net 30',
            'discount' => 0,
            'line_items' => [
                ['description' => 'Editorial shoot for venue marketing', 'quantity' => 1, 'unit_price' => 1500.00, 'tax_rate' => 0],
            ],
        ])->assertRedirect();

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertSame(Venue::class, $invoice->billable_type);
        $this->assertSame($venue->id, $invoice->billable_id);
        $this->assertSame('Net 30', $invoice->net_terms);
        $this->assertSame(150000, $invoice->total_cents);
        $this->assertTrue($invoice->isVendorInvoice());
        $this->assertFalse($invoice->canPayOnline());
    }

    public function test_admin_invoice_create_lists_billable_venues_only(): void
    {
        $admin = User::factory()->create();
        $billableVenue = Venue::factory()->billable()->create([
            'name' => 'Billing Venue',
            'business_name' => 'Billing Venue LLC',
        ]);
        Venue::factory()->create(['name' => 'Content Only Venue', 'billing_email' => null]);

        $this->actingAs($admin)
            ->get(route('admin.invoices.create', ['venue_id' => $billableVenue->id]))
            ->assertOk()
            ->assertSee('Billing Venue LLC')
            ->assertDontSee('Content Only Venue');
    }

    public function test_admin_send_emails_to_venue_billing_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $venue = Venue::factory()->billable()->create(['billing_email' => 'billing@hiddenbay.test']);
        $invoice = Invoice::factory()->forVenue($venue)->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.send', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice));

        Mail::assertSent(InvoiceSent::class, fn ($mail) => $mail->hasTo('billing@hiddenbay.test'));
        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);
    }

    public function test_admin_send_blocked_when_venue_has_no_billing_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $venue = Venue::factory()->create(['billing_email' => null]);
        $invoice = Invoice::factory()->forVenue($venue)->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.send', $invoice))
            ->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status);
    }

    public function test_admin_invoice_validates_billable_id_exists_in_chosen_table(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.store'), [
                'billable_type' => 'venue',
                'billable_id' => $client->id,
                'issue_date' => '2026-05-01',
                'discount' => 0,
                'line_items' => [
                    ['description' => 'X', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0],
                ],
            ])
            ->assertSessionHasErrors(['billable_id']);
    }

    public function test_venue_can_log_into_portal(): void
    {
        $venue = Venue::factory()->billable()->create([
            'billing_email' => 'billing@example.test',
            'password' => Hash::make('venue-pass'),
        ]);

        $this->post(route('portal.login.store'), [
            'email' => 'billing@example.test',
            'password' => 'venue-pass',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertTrue(Auth::guard('venue')->check());
        $this->assertSame($venue->id, Auth::guard('venue')->id());
        $this->assertNotNull($venue->fresh()->last_login_at);
    }

    public function test_venue_dashboard_shows_their_invoices_and_uses_portal_greeting(): void
    {
        $venue = Venue::factory()->billable()->create(['business_name' => 'Hidden Bay LLC', 'billing_contact_name' => 'Dana Bay']);
        $invoice = Invoice::factory()->forVenue($venue)->sent()->create([
            'total_cents' => 250000,
            'amount_paid_cents' => 0,
        ]);

        $this->actingAs($venue, 'venue')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Hi, Dana Bay')
            ->assertSee($invoice->number);
    }

    public function test_venue_can_view_their_invoice_but_not_anothers(): void
    {
        $venue = Venue::factory()->billable()->create();
        $other = Venue::factory()->billable()->create();
        $mine = Invoice::factory()->forVenue($venue)->sent()->create();
        $theirs = Invoice::factory()->forVenue($other)->sent()->create();

        $this->actingAs($venue, 'venue')
            ->get(route('portal.invoices.show', ['invoice' => $mine->uuid]))
            ->assertOk()
            ->assertSee($mine->number);

        $this->actingAs($venue, 'venue')
            ->get(route('portal.invoices.show', ['invoice' => $theirs->uuid]))
            ->assertNotFound();
    }

    public function test_venue_invoice_show_does_not_render_online_payment_buttons(): void
    {
        config([
            'payments.gateways.square.enabled' => true,
            'payments.gateways.square.sandbox' => [
                'access_token' => 't',
                'application_id' => 'a',
                'location_id' => 'l',
                'webhook_signature_key' => 'k',
            ],
            'payments.gateways.paypal.enabled' => true,
            'payments.gateways.paypal.sandbox' => [
                'client_id' => 'pp-id',
                'client_secret' => 'pp-secret',
                'webhook_id' => 'wh',
            ],
        ]);

        $venue = Venue::factory()->billable()->create();
        $invoice = Invoice::factory()->forVenue($venue)->sent()->create([
            'total_cents' => 50000,
            'net_terms' => 'Net 30',
        ]);

        $this->actingAs($venue, 'venue')
            ->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertOk()
            ->assertDontSee('Pay with card')
            ->assertDontSee('Pay with PayPal')
            ->assertSee('Pay by check or ACH')
            ->assertSee('Net 30');
    }

    public function test_venue_cannot_use_square_payment_endpoint(): void
    {
        $venue = Venue::factory()->billable()->create();
        $invoice = Invoice::factory()->forVenue($venue)->sent()->create(['total_cents' => 50000]);

        $this->actingAs($venue, 'venue')
            ->post(route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]), [
                'source_id' => 'cnon:1',
            ])
            ->assertNotFound();
    }

    public function test_venue_cannot_use_paypal_payment_endpoints(): void
    {
        $venue = Venue::factory()->billable()->create();
        $invoice = Invoice::factory()->forVenue($venue)->sent()->create(['total_cents' => 50000]);

        $this->actingAs($venue, 'venue')
            ->postJson(route('portal.invoices.pay.paypal.create', ['invoice' => $invoice->uuid]))
            ->assertNotFound();
    }

    public function test_admin_save_venue_with_portal_password_enables_login(): void
    {
        $admin = User::factory()->create();
        $venue = Venue::factory()->create(['name' => 'New Vendor']);

        $this->actingAs($admin)->put(route('admin.venues.update', $venue), [
            'name' => $venue->name,
            'business_name' => 'New Vendor LLC',
            'billing_email' => 'billing@new-vendor.test',
            'billing_country' => 'US',
            'net_payment_terms' => 'Net 30',
            'portal_password' => 'first-password',
        ])->assertRedirect();

        $venue->refresh();
        $this->assertTrue(Hash::check('first-password', $venue->password));
        $this->assertTrue($venue->isBillable());
    }

    public function test_admin_save_venue_without_portal_password_keeps_existing(): void
    {
        $admin = User::factory()->create();
        $venue = Venue::factory()->billable()->create(['password' => Hash::make('original')]);

        $this->actingAs($admin)->put(route('admin.venues.update', $venue), [
            'name' => $venue->name,
            'business_name' => $venue->business_name,
            'billing_email' => $venue->billing_email,
            'billing_country' => 'US',
            'portal_password' => '',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('original', $venue->fresh()->password));
    }
}

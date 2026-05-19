<?php

namespace Tests\Feature;

use App\Mail\InvoiceOverdueReminder;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendOverdueInvoiceRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_emails_past_due_unpaid_client_invoices_once(): void
    {
        Mail::fake();
        $client = Client::factory()->create(['email' => 'pay@example.com']);
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
            'issue_date' => now()->subDays(20),
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();

        Mail::assertSent(InvoiceOverdueReminder::class, fn (InvoiceOverdueReminder $m) => $m->invoice->is($invoice));
        $this->assertNotNull($invoice->fresh()->overdue_reminder_sent_at);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_command_skips_invoices_not_yet_due(): void
    {
        Mail::fake();
        $client = Client::factory()->create(['email' => 'pay@example.com']);
        Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
            'issue_date' => now()->subDays(2),
            'due_date' => now()->addDays(10),
        ]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_command_skips_paid_invoices(): void
    {
        Mail::fake();
        $client = Client::factory()->create(['email' => 'pay@example.com']);
        Invoice::factory()->paid()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 50000,
            'amount_paid_cents' => 50000,
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_command_skips_void_invoices(): void
    {
        Mail::fake();
        $client = Client::factory()->create(['email' => 'pay@example.com']);
        Invoice::factory()->void()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 50000,
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_command_skips_vendor_invoices(): void
    {
        Mail::fake();
        $venue = Venue::factory()->create(['billing_email' => 'venue@example.com']);
        Invoice::factory()->sent()->forVenue($venue)->create([
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_dry_run_does_not_send_or_mark(): void
    {
        Mail::fake();
        $client = Client::factory()->create(['email' => 'pay@example.com']);
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('invoices:send-overdue-reminders', ['--dry-run' => true])->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($invoice->fresh()->overdue_reminder_sent_at);
    }
}

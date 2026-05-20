<?php

namespace App\Console\Commands;

use App\Mail\InvoiceOverdueReminder;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('invoices:send-overdue-reminders {--dry-run : Show what would be sent without dispatching emails}')]
#[Description('Email a one-time reminder for each client invoice that is past due and unpaid.')]
class SendOverdueInvoiceReminders extends Command
{
    public function handle(InvoicePdfRenderer $renderer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = now()->startOfDay();

        $invoices = Invoice::query()
            ->with('billable')
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->whereNull('overdue_reminder_sent_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereColumn('amount_paid_cents', '<', 'total_cents')
            ->where('billable_type', Client::class)
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            $recipient = $invoice->billableEmail();

            if (! $recipient) {
                $skipped++;
                $this->warn("Invoice {$invoice->number}: no email on file, skipped.");

                continue;
            }

            if ($dryRun) {
                $this->line("Would email {$recipient} about invoice {$invoice->number} (due {$invoice->due_date?->toDateString()}).");
                $sent++;

                continue;
            }

            $now = now();

            $claimed = Invoice::query()
                ->whereKey($invoice->id)
                ->whereNull('overdue_reminder_sent_at')
                ->update(['overdue_reminder_sent_at' => $now]);

            if ($claimed === 0) {
                $skipped++;
                $this->line("Invoice {$invoice->number}: reminder already claimed by another run, skipped.");

                continue;
            }

            Mail::to($recipient)->send(new InvoiceOverdueReminder(
                invoice: $invoice,
                payUrl: $renderer->signedPayUrl($invoice),
            ));

            $invoice->overdue_reminder_sent_at = $now;
            $sent++;

            $this->info("Reminder sent for invoice {$invoice->number} to {$recipient}.");
        }

        $this->newLine();
        $this->info(sprintf('Done. %d %s, %d skipped.', $sent, $dryRun ? 'would be sent' : 'sent', $skipped));

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Http\Requests\Admin\UpdateInvoiceRequest;
use App\Mail\InvoiceSent;
use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\Venue;
use App\Services\Invoicing\InvoiceComposer;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceComposer $composer,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $statusOptions = Invoice::statusOptions();

        if ($status !== 'all' && ! array_key_exists($status, $statusOptions)) {
            $status = 'all';
        }

        $query = Invoice::query()->with('billable');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $invoices = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'currentStatus' => $status,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function create(Request $request): View
    {
        $client = $request->query('client_id')
            ? Client::find($request->integer('client_id'))
            : null;
        $venue = $request->query('venue_id')
            ? Venue::find($request->integer('venue_id'))
            : null;

        $billableType = $venue ? 'venue' : 'client';
        $billable = $venue ?: $client;

        $netTerms = $venue?->net_payment_terms;

        $invoice = new Invoice([
            'currency' => config('payments.currency', 'USD'),
            'default_tax_rate' => (float) config('payments.default_tax_rate', 0),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'billable_type' => $billable ? $billable::class : null,
            'billable_id' => $billable?->id,
            'net_terms' => $netTerms,
        ]);

        return view('admin.invoices.form', [
            'invoice' => $invoice,
            'billableType' => $billableType,
            'clients' => Client::orderBy('last_name')->orderBy('first_name')->get(),
            'venues' => Venue::query()->whereNotNull('billing_email')->orderBy('name')->get(),
            'bookedJobs' => $client
                ? BookedJob::whereIn('inquiry_id', $client->inquiries()->pluck('id'))->get()
                : collect(),
            'lineItems' => collect([(new InvoiceLineItem([
                'quantity' => 1,
                'tax_rate' => (float) config('payments.default_tax_rate', 0),
            ]))]),
            'installments' => collect(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $billableClass = $request->billableClass();

        $invoice = DB::transaction(function () use ($data, $billableClass) {
            $invoice = Invoice::create([
                'billable_type' => $billableClass,
                'billable_id' => (int) $data['billable_id'],
                'booked_job_id' => $data['booked_job_id'] ?? null,
                'status' => Invoice::STATUS_DRAFT,
                'currency' => config('payments.currency', 'USD'),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'default_tax_rate' => $data['default_tax_rate'] ?? 0,
                'discount_cents' => $this->composer->dollarsToCents($data['discount'] ?? 0),
                'net_terms' => $data['net_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $this->composer->syncLineItems($invoice, $data['line_items']);
            $this->composer->syncInstallments($invoice, $data['installments'] ?? []);
            $invoice->recalculateTotals();

            return $invoice;
        });

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice created.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['billable', 'bookedJob', 'lineItems', 'installments', 'payments']);

        return view('admin.invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        if (! $invoice->isEditable()) {
            return view('admin.invoices.show', [
                'invoice' => $invoice->load(['billable', 'lineItems', 'installments', 'payments']),
            ]);
        }

        $invoice->load(['lineItems', 'installments', 'billable']);

        $billable = $invoice->billable;
        $billableType = $billable instanceof Venue ? 'venue' : 'client';

        return view('admin.invoices.form', [
            'invoice' => $invoice,
            'billableType' => $billableType,
            'clients' => Client::orderBy('last_name')->orderBy('first_name')->get(),
            'venues' => Venue::query()->whereNotNull('billing_email')->orderBy('name')->get(),
            'bookedJobs' => $billable instanceof Client
                ? BookedJob::whereIn('inquiry_id', $billable->inquiries()->pluck('id'))->get()
                : collect(),
            'lineItems' => $invoice->lineItems,
            'installments' => $invoice->installments,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        if (! $invoice->isEditable()) {
            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('status', 'Sent or paid invoices cannot be edited. Void it first to make changes.');
        }

        $data = $request->validated();
        $billableClass = $request->billableClass();

        DB::transaction(function () use ($invoice, $data, $billableClass) {
            $invoice->update([
                'billable_type' => $billableClass,
                'billable_id' => (int) $data['billable_id'],
                'booked_job_id' => $data['booked_job_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'default_tax_rate' => $data['default_tax_rate'] ?? 0,
                'discount_cents' => $this->composer->dollarsToCents($data['discount'] ?? 0),
                'net_terms' => $data['net_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $invoice->lineItems()->delete();
            $invoice->installments()->delete();
            $this->composer->syncLineItems($invoice, $data['line_items']);
            $this->composer->syncInstallments($invoice, $data['installments'] ?? []);
            $invoice->recalculateTotals();
        });

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        if (! $invoice->isEditable()) {
            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('status', 'Only draft invoices can be deleted. Void it instead.');
        }

        $invoice->delete();

        return redirect()
            ->route('admin.invoices.index')
            ->with('status', 'Invoice deleted.');
    }

    public function send(Invoice $invoice, InvoicePdfRenderer $renderer): RedirectResponse
    {
        if (! in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT], true)) {
            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('status', 'This invoice cannot be sent in its current state.');
        }

        $invoice->loadMissing('billable');
        $recipientEmail = $invoice->billableEmail();

        if (! $recipientEmail) {
            $kind = $invoice->isVendorInvoice() ? 'venue' : 'client';

            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('status', "Cannot send: {$kind} has no billing email on file.");
        }

        Mail::to($recipientEmail)->send(new InvoiceSent(
            invoice: $invoice,
            payUrl: $renderer->signedPayUrl($invoice),
        ));

        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => $invoice->sent_at ?? now(),
        ]);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice emailed to '.$recipientEmail.'.');
    }

    public function void(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === Invoice::STATUS_VOID) {
            return redirect()->route('admin.invoices.show', $invoice);
        }

        $invoice->update([
            'status' => Invoice::STATUS_VOID,
            'voided_at' => now(),
        ]);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice voided.');
    }

    public function downloadPdf(Invoice $invoice, InvoicePdfRenderer $renderer)
    {
        return $renderer->build($invoice)->download();
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($invoice, $data) {
            $payment = $invoice->payments()->create([
                'invoice_installment_id' => $data['invoice_installment_id'] ?? null,
                'gateway' => $data['gateway'],
                'mode' => config('payments.mode', 'sandbox'),
                'status' => Payment::STATUS_COMPLETED,
                'amount_cents' => $this->composer->dollarsToCents($data['amount']),
                'currency' => $invoice->currency,
                'gateway_payment_id' => $data['gateway_payment_id'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
            ]);

            $invoice->syncStatusFromPayments();

            if ($payment->invoice_installment_id) {
                InvoiceInstallment::find($payment->invoice_installment_id)?->syncStatusFromPayments();
            }
        });

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Payment recorded.');
    }
}

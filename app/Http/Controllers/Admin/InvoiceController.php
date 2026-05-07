<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Http\Requests\Admin\UpdateInvoiceRequest;
use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $statusOptions = Invoice::statusOptions();

        if ($status !== 'all' && ! array_key_exists($status, $statusOptions)) {
            $status = 'all';
        }

        $query = Invoice::query()->with('client');

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

        $invoice = new Invoice([
            'currency' => config('payments.currency', 'USD'),
            'default_tax_rate' => (float) config('payments.default_tax_rate', 0),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'client_id' => $client?->id,
        ]);

        return view('admin.invoices.form', [
            'invoice' => $invoice,
            'client' => $client,
            'clients' => $client ? collect([$client]) : Client::orderBy('last_name')->orderBy('first_name')->get(),
            'bookedJobs' => $client && $client->inquiry
                ? BookedJob::where('inquiry_id', $client->inquiry_id)->get()
                : collect(),
            'lineItems' => collect([new InvoiceLineItem([
                'quantity' => 1,
                'tax_rate' => (float) config('payments.default_tax_rate', 0),
            ])]),
            'installments' => collect(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $invoice = DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'client_id' => $data['client_id'],
                'booked_job_id' => $data['booked_job_id'] ?? null,
                'status' => Invoice::STATUS_DRAFT,
                'currency' => config('payments.currency', 'USD'),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'default_tax_rate' => $data['default_tax_rate'] ?? 0,
                'discount_cents' => $this->dollarsToCents($data['discount'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $this->syncLineItems($invoice, $data['line_items']);
            $this->syncInstallments($invoice, $data['installments'] ?? []);
            $invoice->recalculateTotals();

            return $invoice;
        });

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice created.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['client', 'bookedJob', 'lineItems', 'installments', 'payments']);

        return view('admin.invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        if (! $invoice->isEditable()) {
            return view('admin.invoices.show', [
                'invoice' => $invoice->load(['client', 'lineItems', 'installments', 'payments']),
            ]);
        }

        $invoice->load(['lineItems', 'installments']);

        return view('admin.invoices.form', [
            'invoice' => $invoice,
            'client' => $invoice->client,
            'clients' => Client::orderBy('last_name')->orderBy('first_name')->get(),
            'bookedJobs' => $invoice->client?->inquiry
                ? BookedJob::where('inquiry_id', $invoice->client->inquiry_id)->get()
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

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'client_id' => $data['client_id'],
                'booked_job_id' => $data['booked_job_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'default_tax_rate' => $data['default_tax_rate'] ?? 0,
                'discount_cents' => $this->dollarsToCents($data['discount'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $invoice->lineItems()->delete();
            $invoice->installments()->delete();
            $this->syncLineItems($invoice, $data['line_items']);
            $this->syncInstallments($invoice, $data['installments'] ?? []);
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

    public function send(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('status', 'Only draft invoices can be sent.');
        }

        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
        ]);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Invoice marked as sent.');
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
                'amount_cents' => $this->dollarsToCents($data['amount']),
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

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncLineItems(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $index => $row) {
            $invoice->lineItems()->create([
                'sort_order' => $index,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price_cents' => $this->dollarsToCents($row['unit_price']),
                'tax_rate' => $row['tax_rate'] ?? 0,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncInstallments(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $index => $row) {
            $amount = isset($row['amount']) ? $this->dollarsToCents($row['amount']) : 0;
            if ($amount <= 0) {
                continue;
            }

            $invoice->installments()->create([
                'sequence' => $index + 1,
                'label' => $row['label'] ?? null,
                'due_date' => $row['due_date'] ?? null,
                'amount_cents' => $amount,
            ]);
        }
    }

    private function dollarsToCents(float|int|string|null $dollars): int
    {
        if ($dollars === null || $dollars === '') {
            return 0;
        }

        return (int) round(((float) $dollars) * 100);
    }
}

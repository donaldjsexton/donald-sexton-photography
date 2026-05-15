<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBookingProposalRequest;
use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Services\Contracts\ContractVariableResolver;
use App\Services\Invoicing\InvoiceComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingProposalController extends Controller
{
    public function __construct(
        private readonly ContractVariableResolver $variables,
        private readonly InvoiceComposer $composer,
    ) {}

    public function create(Request $request): View
    {
        $client = $request->query('client_id')
            ? Client::findOrFail($request->integer('client_id'))
            : null;
        $bookedJob = $request->query('booked_job_id')
            ? BookedJob::find($request->integer('booked_job_id'))
            : null;

        $template = ContractTemplate::query()->where('is_default', true)->first();

        $title = $template?->title ?? 'Photography Agreement';
        $body = $template?->body ?? '';

        if ($template) {
            $body = $this->variables->render(
                $template->body,
                $this->variables->variablesFor(
                    billable: $client,
                    bookedJob: $bookedJob,
                    contractTitle: $title,
                    issueDate: now()->toDateString(),
                ),
            );
        }

        return view('admin.proposals.create', [
            'client' => $client,
            'clients' => Client::orderBy('last_name')->orderBy('first_name')->get(),
            'bookedJob' => $bookedJob,
            'bookedJobs' => $client
                ? BookedJob::whereIn('inquiry_id', $client->inquiries()->pluck('id'))->get()
                : collect(),
            'templates' => ContractTemplate::orderBy('name')->get(),
            'defaultTemplate' => $template,
            'title' => $title,
            'body' => $body,
            'lineItems' => collect([new InvoiceLineItem([
                'quantity' => 1,
                'tax_rate' => (float) config('payments.default_tax_rate', 0),
            ])]),
            'availableVariables' => ContractVariableResolver::availableVariables(),
        ]);
    }

    public function store(StoreBookingProposalRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $contract = DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'billable_type' => Client::class,
                'billable_id' => (int) $data['client_id'],
                'booked_job_id' => $data['booked_job_id'] ?? null,
                'status' => Invoice::STATUS_DRAFT,
                'currency' => config('payments.currency', 'USD'),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'default_tax_rate' => $data['default_tax_rate'] ?? 0,
                'discount_cents' => $this->composer->dollarsToCents($data['discount'] ?? 0),
                'notes' => $data['invoice_notes'] ?? null,
                'terms' => $data['invoice_terms'] ?? null,
            ]);

            $this->composer->syncLineItems($invoice, $data['line_items']);
            $this->composer->syncInstallments($invoice, $data['installments'] ?? []);
            $invoice->recalculateTotals();

            return Contract::create([
                'billable_type' => Client::class,
                'billable_id' => (int) $data['client_id'],
                'booked_job_id' => $data['booked_job_id'] ?? null,
                'invoice_id' => $invoice->id,
                'contract_template_id' => $data['contract_template_id'] ?? null,
                'status' => Contract::STATUS_DRAFT,
                'title' => $data['title'],
                'body' => $data['body'],
                'issue_date' => $data['issue_date'],
                'expires_at' => $data['expires_at'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('admin.contracts.show', $contract)
            ->with('status', 'Proposal drafted. Review the contract and invoice, then use “Send as Proposal”.');
    }
}

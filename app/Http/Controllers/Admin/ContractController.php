<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContractRequest;
use App\Http\Requests\Admin\UpdateContractRequest;
use App\Mail\ContractSent;
use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Invoice;
use App\Models\Venue;
use App\Services\Contracts\ContractPdfRenderer;
use App\Services\Contracts\ContractVariableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractVariableResolver $variables,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $statusOptions = Contract::statusOptions();

        if ($status !== 'all' && ! array_key_exists($status, $statusOptions)) {
            $status = 'all';
        }

        $query = Contract::query()->with('billable');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $contracts = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.contracts.index', [
            'contracts' => $contracts,
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
        $bookedJob = $request->query('booked_job_id')
            ? BookedJob::find($request->integer('booked_job_id'))
            : null;

        $billableType = $venue ? 'venue' : 'client';
        $billable = $venue ?: $client;

        $template = ContractTemplate::query()->where('is_default', true)->first();

        $defaults = [
            'title' => $template?->title ?? 'Photography Agreement',
            'body' => $template?->body ?? '',
            'contract_template_id' => $template?->id,
        ];

        if ($template) {
            $defaults['body'] = $this->variables->render(
                $template->body,
                $this->variables->variablesFor(
                    billable: $billable,
                    bookedJob: $bookedJob,
                    contractTitle: $defaults['title'],
                    issueDate: now()->toDateString(),
                ),
            );
        }

        $contract = new Contract([
            'status' => Contract::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
            'billable_type' => $billable ? $billable::class : null,
            'billable_id' => $billable?->id,
            'booked_job_id' => $bookedJob?->id,
            'contract_template_id' => $defaults['contract_template_id'],
            'title' => $defaults['title'],
            'body' => $defaults['body'],
        ]);

        return view('admin.contracts.form', [
            'contract' => $contract,
            'billableType' => $billableType,
            'clients' => Client::orderBy('last_name')->orderBy('first_name')->get(),
            'venues' => Venue::query()->whereNotNull('billing_email')->orderBy('name')->get(),
            'bookedJobs' => $this->bookedJobsFor($billable),
            'invoices' => $this->invoicesFor($billable),
            'templates' => ContractTemplate::orderBy('name')->get(),
            'availableVariables' => ContractVariableResolver::availableVariables(),
        ]);
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $billableClass = $request->billableClass();

        $contract = Contract::create([
            'billable_type' => $billableClass,
            'billable_id' => (int) $data['billable_id'],
            'booked_job_id' => $data['booked_job_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'contract_template_id' => $data['contract_template_id'] ?? null,
            'status' => Contract::STATUS_DRAFT,
            'title' => $data['title'],
            'body' => $data['body'],
            'issue_date' => $data['issue_date'],
            'expires_at' => $data['expires_at'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.contracts.show', $contract)
            ->with('status', 'Contract created.');
    }

    public function show(Contract $contract): View
    {
        $contract->load(['billable', 'bookedJob', 'invoice', 'template']);

        return view('admin.contracts.show', [
            'contract' => $contract,
        ]);
    }

    public function edit(Contract $contract): View
    {
        if (! $contract->isEditable()) {
            return view('admin.contracts.show', [
                'contract' => $contract->load(['billable', 'bookedJob', 'invoice', 'template']),
            ]);
        }

        $contract->load(['billable']);
        $billable = $contract->billable;
        $billableType = $billable instanceof Venue ? 'venue' : 'client';

        return view('admin.contracts.form', [
            'contract' => $contract,
            'billableType' => $billableType,
            'clients' => Client::orderBy('last_name')->orderBy('first_name')->get(),
            'venues' => Venue::query()->whereNotNull('billing_email')->orderBy('name')->get(),
            'bookedJobs' => $this->bookedJobsFor($billable),
            'invoices' => $this->invoicesFor($billable),
            'templates' => ContractTemplate::orderBy('name')->get(),
            'availableVariables' => ContractVariableResolver::availableVariables(),
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): RedirectResponse
    {
        if (! $contract->isEditable()) {
            return redirect()
                ->route('admin.contracts.show', $contract)
                ->with('status', 'Sent or signed contracts cannot be edited. Void it first to make changes.');
        }

        $data = $request->validated();
        $billableClass = $request->billableClass();

        $contract->update([
            'billable_type' => $billableClass,
            'billable_id' => (int) $data['billable_id'],
            'booked_job_id' => $data['booked_job_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'contract_template_id' => $data['contract_template_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'issue_date' => $data['issue_date'],
            'expires_at' => $data['expires_at'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.contracts.show', $contract)
            ->with('status', 'Contract updated.');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        if (! $contract->isEditable()) {
            return redirect()
                ->route('admin.contracts.show', $contract)
                ->with('status', 'Only draft contracts can be deleted. Void it instead.');
        }

        $contract->delete();

        return redirect()
            ->route('admin.contracts.index')
            ->with('status', 'Contract deleted.');
    }

    public function send(Contract $contract, ContractPdfRenderer $renderer): RedirectResponse
    {
        if (! in_array($contract->status, [Contract::STATUS_DRAFT, Contract::STATUS_SENT], true)) {
            return redirect()
                ->route('admin.contracts.show', $contract)
                ->with('status', 'This contract cannot be sent in its current state.');
        }

        $contract->loadMissing('billable');
        $recipientEmail = $contract->billableEmail();

        if (! $recipientEmail) {
            return redirect()
                ->route('admin.contracts.show', $contract)
                ->with('status', 'Cannot send: the counterparty has no email on file.');
        }

        Mail::to($recipientEmail)->send(new ContractSent(
            contract: $contract,
            signUrl: $renderer->signedSignUrl($contract),
        ));

        $contract->update([
            'status' => Contract::STATUS_SENT,
            'sent_at' => $contract->sent_at ?? now(),
        ]);

        return redirect()
            ->route('admin.contracts.show', $contract)
            ->with('status', 'Contract emailed to '.$recipientEmail.'.');
    }

    public function void(Contract $contract): RedirectResponse
    {
        if ($contract->status === Contract::STATUS_VOID) {
            return redirect()->route('admin.contracts.show', $contract);
        }

        $contract->update([
            'status' => Contract::STATUS_VOID,
            'voided_at' => now(),
        ]);

        return redirect()
            ->route('admin.contracts.show', $contract)
            ->with('status', 'Contract voided.');
    }

    public function downloadPdf(Contract $contract, ContractPdfRenderer $renderer)
    {
        return $renderer->build($contract)->download();
    }

    public function preview(Request $request): JsonResponse
    {
        $template = ContractTemplate::findOrFail((int) $request->input('template_id'));

        $billable = null;
        if ($request->filled('billable_type') && $request->filled('billable_id')) {
            $billable = $request->input('billable_type') === 'venue'
                ? Venue::find($request->integer('billable_id'))
                : Client::find($request->integer('billable_id'));
        }

        $bookedJob = $request->filled('booked_job_id')
            ? BookedJob::find($request->integer('booked_job_id'))
            : null;

        $invoice = $request->filled('invoice_id')
            ? Invoice::find($request->integer('invoice_id'))
            : null;

        $body = $this->variables->render(
            $template->body,
            $this->variables->variablesFor(
                billable: $billable,
                bookedJob: $bookedJob,
                invoice: $invoice,
                contractTitle: $template->title,
                issueDate: now()->toDateString(),
            ),
        );

        return response()->json([
            'title' => $template->title,
            'body' => $body,
        ]);
    }

    private function bookedJobsFor(?object $billable): Collection
    {
        if ($billable instanceof Client && $billable->inquiry_id) {
            return BookedJob::where('inquiry_id', $billable->inquiry_id)->get();
        }

        return collect();
    }

    private function invoicesFor(?object $billable): Collection
    {
        if (! $billable) {
            return collect();
        }

        return Invoice::query()
            ->where('billable_type', $billable::class)
            ->where('billable_id', $billable->id)
            ->orderByDesc('issue_date')
            ->get();
    }
}

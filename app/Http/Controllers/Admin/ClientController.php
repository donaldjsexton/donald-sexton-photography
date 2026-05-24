<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Payment;
use App\Services\ClientFromInquirySync;
use App\Services\Portal\PortalInviteSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientFromInquirySync $clientSync,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $clients = Client::query()
            ->withCount('invoices')
            ->search($search ?: null)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.clients.index', [
            'clients' => $clients,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.clients.form', [
            'client' => new Client,
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', 'Client created.');
    }

    public function show(Client $client): View
    {
        $client->load([
            'inquiries' => fn ($q) => $q->latest('created_at'),
            'inquiries.bookedJob',
            'inquiries.venue',
            'invoices' => fn ($q) => $q->latest('issue_date'),
            'invoices.bookedJob',
            'invoices.payments',
            'contracts' => fn ($q) => $q->latest('issue_date'),
            'contracts.bookedJob',
        ]);

        return view('admin.clients.show', [
            'client' => $client,
            'timeline' => $this->buildTimeline($client),
        ]);
    }

    /**
     * Reverse-chronological activity feed across the client's whole history.
     *
     * @return array<int, array{at: Carbon, kind: string, icon: string, title: string, meta: string, url: ?string}>
     */
    private function buildTimeline(Client $client): array
    {
        $events = [];

        foreach ($client->inquiries as $inquiry) {
            $events[] = [
                'at' => $inquiry->created_at,
                'kind' => 'inquiry',
                'icon' => '✦',
                'title' => 'New inquiry',
                'meta' => collect([
                    Inquiry::statusOptions()[$inquiry->status] ?? $inquiry->status,
                    $inquiry->event_type ? ucfirst((string) $inquiry->event_type) : null,
                    $inquiry->event_date?->format('M j, Y'),
                ])->filter()->implode(' · '),
                'url' => route('admin.inquiries.edit', $inquiry),
            ];
        }

        foreach ($client->contracts as $contract) {
            $label = $contract->isProposal() ? 'Proposal' : 'Contract';
            $url = route('admin.contracts.show', $contract);

            $events[] = [
                'at' => $contract->created_at,
                'kind' => 'contract',
                'icon' => '📝',
                'title' => $label.' drafted',
                'meta' => $contract->number,
                'url' => $url,
            ];
            if ($contract->sent_at) {
                $events[] = [
                    'at' => $contract->sent_at,
                    'kind' => 'contract',
                    'icon' => '📨',
                    'title' => $label.' sent',
                    'meta' => $contract->number,
                    'url' => $url,
                ];
            }
            if ($contract->signed_at) {
                $events[] = [
                    'at' => $contract->signed_at,
                    'kind' => 'signed',
                    'icon' => '✍️',
                    'title' => $label.' signed',
                    'meta' => trim($contract->number.' · '.(string) $contract->signer_name, ' ·'),
                    'url' => $url,
                ];
            }
        }

        foreach ($client->invoices as $invoice) {
            $url = route('admin.invoices.show', $invoice);

            if ($invoice->sent_at) {
                $events[] = [
                    'at' => $invoice->sent_at,
                    'kind' => 'invoice',
                    'icon' => '🧾',
                    'title' => 'Invoice sent',
                    'meta' => $invoice->number.' · $'.number_format($invoice->total_cents / 100, 2),
                    'url' => $url,
                ];
            }

            foreach ($invoice->payments as $payment) {
                if ($payment->status !== Payment::STATUS_COMPLETED) {
                    continue;
                }
                $events[] = [
                    'at' => $payment->received_at ?? $payment->created_at,
                    'kind' => 'payment',
                    'icon' => '💰',
                    'title' => 'Payment received',
                    'meta' => '$'.number_format($payment->amount_cents / 100, 2).' · '.$invoice->number,
                    'url' => $url,
                ];
            }
        }

        usort($events, fn ($a, $b) => $b['at'] <=> $a['at']);

        return $events;
    }

    public function edit(Client $client): View
    {
        return view('admin.clients.form', [
            'client' => $client,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()
            ->route('admin.clients.index')
            ->with('status', 'Client deleted.');
    }

    public function convertFromInquiry(Inquiry $inquiry): RedirectResponse
    {
        if ($inquiry->client) {
            return redirect()
                ->route('admin.clients.show', $inquiry->client)
                ->with('status', 'Client already exists for this inquiry.');
        }

        $client = $this->clientSync->syncFromInquiry($inquiry);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', 'Client created from inquiry.');
    }

    public function sendPortalInvite(Client $client, PortalInviteSender $inviteSender): RedirectResponse
    {
        if ($client->password !== null) {
            return redirect()
                ->route('admin.clients.show', $client)
                ->with('error', 'This client already has portal access. Use the password reset flow instead.');
        }

        try {
            $inviteSender->send($client);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('error', 'Portal invite email failed to send. Check the logs and retry.');
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', 'Portal invite sent to '.$client->email.'.');
    }
}

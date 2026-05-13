<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Mail\PortalInvite;
use App\Models\Client;
use App\Models\Inquiry;
use App\Services\ClientFromInquirySync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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
            'inquiry' => null,
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
        $client->load(['inquiry', 'invoices' => fn ($q) => $q->latest('issue_date')]);

        return view('admin.clients.show', [
            'client' => $client,
        ]);
    }

    public function edit(Client $client): View
    {
        return view('admin.clients.form', [
            'client' => $client,
            'inquiry' => $client->inquiry,
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

    public function sendPortalInvite(Client $client): RedirectResponse
    {
        if ($client->password !== null) {
            return redirect()
                ->route('admin.clients.show', $client)
                ->with('error', 'This client already has portal access. Use the password reset flow instead.');
        }

        $setupUrl = URL::temporarySignedRoute(
            'portal.invite.show',
            now()->addDays(7),
            ['client' => $client->uuid],
        );

        try {
            Mail::to($client->email, $client->displayName())
                ->send(new PortalInvite($client, $setupUrl));
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

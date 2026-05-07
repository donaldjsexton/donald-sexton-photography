<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\Client;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
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

        [$firstName, $lastName] = $this->splitName($inquiry->primary_name);
        [$partnerFirst, $partnerLast] = $this->splitName($inquiry->partner_name);

        $client = Client::create([
            'inquiry_id' => $inquiry->id,
            'first_name' => $firstName ?: 'Client',
            'last_name' => $lastName,
            'partner_first_name' => $partnerFirst,
            'partner_last_name' => $partnerLast,
            'email' => $inquiry->email,
            'phone' => $inquiry->phone,
            'city' => $inquiry->location_city,
            'country' => 'US',
        ]);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', 'Client created from inquiry.');
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitName(?string $fullName): array
    {
        $name = trim((string) $fullName);
        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [$name];

        return [$parts[0] ?? null, $parts[1] ?? null];
    }
}

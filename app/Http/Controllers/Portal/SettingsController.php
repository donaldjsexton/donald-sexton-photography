<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View|RedirectResponse
    {
        $client = $this->client();

        if (! $client) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.settings', [
            'client' => $client,
            'communicationChannels' => Client::COMMUNICATION_CHANNELS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $client = $this->client();

        if (! $client) {
            return redirect()->route('portal.dashboard');
        }

        $validated = $request->validate([
            'partner_first_name' => ['nullable', 'string', 'max:255'],
            'partner_last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'communication_preferences' => ['nullable', 'array'],
            'communication_preferences.*' => [Rule::in(array_keys(Client::COMMUNICATION_CHANNELS))],
            'social_media_consent' => ['nullable', 'boolean'],
        ]);

        $client->fill([
            'partner_first_name' => $validated['partner_first_name'] ?? null,
            'partner_last_name' => $validated['partner_last_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? $client->country,
            'communication_preferences' => array_values($validated['communication_preferences'] ?? []),
            'social_media_consent' => $request->boolean('social_media_consent'),
        ])->save();

        return redirect()
            ->route('portal.settings.edit')
            ->with('status', 'Your profile has been updated.');
    }

    private function client(): ?Client
    {
        $user = Portal::user();

        return $user instanceof Client ? $user : null;
    }
}

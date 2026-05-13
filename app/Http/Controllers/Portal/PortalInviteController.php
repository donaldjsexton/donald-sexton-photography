<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PortalInviteController extends Controller
{
    public function show(Client $client): View|RedirectResponse
    {
        if ($client->password !== null) {
            return redirect()
                ->route('portal.login')
                ->with('status', 'Your portal account is already set up. Sign in below.');
        }

        return view('portal.auth.invite', [
            'client' => $client,
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        if ($client->password !== null) {
            return redirect()
                ->route('portal.login')
                ->with('status', 'Your portal account is already set up. Sign in below.');
        }

        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $client->forceFill([
            'password' => $request->string('password')->toString(),
            'email_verified_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        Auth::guard('client')->login($client);
        $client->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()
            ->route('portal.dashboard')
            ->with('status', 'Welcome — your portal is ready.');
    }
}

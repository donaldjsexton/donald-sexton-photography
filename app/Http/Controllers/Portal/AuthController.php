<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        $authenticated = Auth::guard('client')->attempt($credentials, $remember);

        if (! $authenticated) {
            $authenticated = Auth::guard('venue')->attempt([
                'billing_email' => $credentials['email'],
                'password' => $credentials['password'],
            ], $remember);
        }

        if (! $authenticated) {
            return back()
                ->withErrors(['email' => 'Those credentials do not match a portal account.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::guard('client')->user() ?? Auth::guard('venue')->user();

        if ($user !== null) {
            $user->forceFill(['last_login_at' => now()])->save();
            PortalActivity::record($user, PortalActivity::TYPE_LOGIN, $request);
        }

        $intended = $request->session()->pull('url.intended');

        return $intended
            ? redirect($intended)
            : redirect()->route('portal.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();
        Auth::guard('venue')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
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

        if (! Auth::guard('client')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Those credentials do not match a client account.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        Auth::guard('client')->user()->forceFill([
            'last_login_at' => now(),
        ])->save();

        $intended = $request->session()->pull('url.intended');

        return $intended
            ? redirect($intended)
            : redirect()->route('portal.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}

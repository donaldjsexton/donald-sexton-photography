<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteDomain;
use App\Tenancy\CurrentSite;
use App\Tenancy\DomainVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DomainController extends Controller
{
    public function index(CurrentSite $currentSite): View
    {
        $site = $currentSite->get();

        return view('admin.domains.index', [
            'site' => $site,
            'domains' => SiteDomain::query()->where('site_id', $site?->id)->latest()->get(),
        ]);
    }

    public function store(Request $request, CurrentSite $currentSite): RedirectResponse
    {
        $appDomain = strtolower((string) config('app.domain'));

        $validated = $request->validate([
            'host' => [
                'required', 'string', 'max:253',
                'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.[a-z0-9-]{1,63})+$/i',
                'unique:site_domains,host',
                function (string $attribute, mixed $value, callable $fail) use ($appDomain): void {
                    $host = strtolower((string) $value);

                    if ($appDomain !== '' && ($host === $appDomain || str_ends_with($host, '.'.$appDomain))) {
                        $fail('Use the site address field for subdomains of '.$appDomain.'.');
                    }
                },
            ],
        ]);

        SiteDomain::create([
            'site_id' => $currentSite->id(),
            'host' => strtolower(trim($validated['host'])),
            'verification_token' => Str::random(40),
        ]);

        return redirect()
            ->route('admin.domains.index')
            ->with('status', 'Domain added. Add the DNS record below, then verify.');
    }

    public function verify(SiteDomain $siteDomain, CurrentSite $currentSite, DomainVerifier $verifier): RedirectResponse
    {
        $this->authorizeDomain($siteDomain, $currentSite);

        if ($verifier->verify($siteDomain)) {
            $siteDomain->update(['verified_at' => now()]);

            return redirect()->route('admin.domains.index')->with('status', 'Domain verified and live.');
        }

        return redirect()->route('admin.domains.index')
            ->with('status', 'Could not find the verification record yet. DNS can take a few minutes.');
    }

    public function destroy(SiteDomain $siteDomain, CurrentSite $currentSite): RedirectResponse
    {
        $this->authorizeDomain($siteDomain, $currentSite);

        $siteDomain->delete();

        return redirect()->route('admin.domains.index')->with('status', 'Domain removed.');
    }

    private function authorizeDomain(SiteDomain $siteDomain, CurrentSite $currentSite): void
    {
        abort_unless((int) $siteDomain->site_id === (int) $currentSite->id(), 404);
    }
}

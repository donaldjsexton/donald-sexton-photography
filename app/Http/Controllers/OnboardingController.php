<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Tenancy\SiteProvisioner;
use App\Tenancy\VendorPresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function create(): View
    {
        return view('onboarding.create', [
            'vendorOptions' => VendorPresets::options(),
        ]);
    }

    public function store(Request $request, SiteProvisioner $provisioner): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                'unique:sites,subdomain',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (Site::isReservedSubdomain((string) $value)) {
                        $fail('That subdomain is reserved.');
                    }
                },
            ],
            'vendor_type' => ['nullable', Rule::in(VendorPresets::types())],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $site = $provisioner->provision([
            'name' => $validated['name'],
            'vendor_type' => $validated['vendor_type'] ?? null,
            'subdomain' => strtolower(trim($validated['subdomain'])),
            'admin_name' => $validated['admin_name'],
            'admin_email' => $validated['admin_email'],
            'admin_password' => $validated['admin_password'],
        ]);

        return redirect()->away($this->adminLoginUrl($request, $site))
            ->with('status', 'Your site is ready. Sign in to start building.');
    }

    private function adminLoginUrl(Request $request, Site $site): string
    {
        $host = $site->subdomain.'.'.config('app.domain');

        return $request->getScheme().'://'.$host.'/admin/login';
    }
}

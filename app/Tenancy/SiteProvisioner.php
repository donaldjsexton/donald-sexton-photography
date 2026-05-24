<?php

namespace App\Tenancy;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Support\HomepageBlocksSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Provisions a new tenant: the Site, its first admin, and starter content,
 * all stamped with the new site. The current tenant context is switched for
 * the duration so the BelongsToSite hook and homepage seeder write into the
 * new site, then restored.
 */
class SiteProvisioner
{
    public function __construct(private CurrentSite $currentSite) {}

    /**
     * @param  array{name: string, subdomain: string, admin_name: string, admin_email: string, admin_password: string, vendor_type?: ?string}  $attributes
     */
    public function provision(array $attributes): Site
    {
        $vendorType = VendorPresets::normalize($attributes['vendor_type'] ?? null);
        $onboarding = VendorPresets::onboarding($vendorType);

        return DB::transaction(function () use ($attributes, $vendorType, $onboarding): Site {
            $site = Site::create([
                'name' => $attributes['name'],
                'vendor_type' => $vendorType,
                'subdomain' => strtolower(trim($attributes['subdomain'])),
                'is_default' => false,
                'status' => 'active',
            ]);

            $previous = $this->currentSite->get();
            $this->currentSite->set($site);

            try {
                User::create([
                    'name' => $attributes['admin_name'],
                    'email' => $attributes['admin_email'],
                    'password' => $attributes['admin_password'],
                ]);

                Page::create([
                    'title' => $onboarding['about']['title'] ?? 'About',
                    'slug' => 'about',
                    'template' => 'about',
                    'status' => 'published',
                    'excerpt' => 'A short introduction.',
                    'body' => $onboarding['about']['body']
                        ?? '<p>Welcome to '.e($attributes['name']).'. Edit this page in the admin to introduce yourself.</p>',
                    'published_at' => now(),
                ]);

                HomepageBlocksSeeder::seed($onboarding['home'] ?? []);
            } finally {
                $this->currentSite->set($previous);
            }

            return $site;
        });
    }
}

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
     * @param  array{name: string, subdomain: string, admin_name: string, admin_email: string, admin_password: string}  $attributes
     */
    public function provision(array $attributes): Site
    {
        return DB::transaction(function () use ($attributes): Site {
            $site = Site::create([
                'name' => $attributes['name'],
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
                    'title' => 'About',
                    'slug' => 'about',
                    'template' => 'about',
                    'status' => 'published',
                    'excerpt' => 'Tell visitors who you are and what you photograph.',
                    'body' => '<p>Welcome to '.e($attributes['name']).'. Edit this page in the admin to introduce yourself.</p>',
                    'published_at' => now(),
                ]);

                HomepageBlocksSeeder::seed();
            } finally {
                $this->currentSite->set($previous);
            }

            return $site;
        });
    }
}

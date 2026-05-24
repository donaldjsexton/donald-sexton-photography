<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use App\Models\Site;
use App\Tenancy\ContractTemplatePresets;
use App\Tenancy\CurrentSite;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractTemplateSeeder extends Seeder
{
    /**
     * Give every existing site the default contract template for its vendor
     * type, marked as the default for new contracts. Idempotent: re-running
     * refreshes the body in place and leaves exactly one default per site.
     */
    public function run(): void
    {
        $currentSite = app(CurrentSite::class);
        $previous = $currentSite->get();

        try {
            Site::all()->each(function (Site $site) use ($currentSite): void {
                $currentSite->set($site);

                $preset = ContractTemplatePresets::forVendorType($site->vendor_type);

                DB::transaction(function () use ($preset, $site): void {
                    ContractTemplate::query()
                        ->where('is_default', true)
                        ->update(['is_default' => false]);

                    ContractTemplate::updateOrCreate(
                        ['name' => $preset['name']],
                        [
                            'site_id' => $site->id,
                            'title' => $preset['title'],
                            'description' => $preset['description'],
                            'body' => $preset['body'],
                            'is_default' => true,
                        ],
                    );
                });
            });
        } finally {
            $currentSite->set($previous);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueReferralSeeder extends Seeder
{
    /**
     * Idempotently apply venue referral whitelist data.
     * Run on prod with: php artisan db:seed --class=VenueReferralSeeder
     */
    public function run(): void
    {
        $venues = [
            [
                'name' => 'Knotted Roots on the Lake',
                'slug' => 'knotted-roots-on-the-lake',
                'website_url' => 'https://knottedrootsonthelake.com',
                'referral_emails' => ['krlakeevents@gmail.com'],
                'referral_contact_name' => 'Tara Hardin',
            ],
        ];

        foreach ($venues as $data) {
            Venue::updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }
    }
}

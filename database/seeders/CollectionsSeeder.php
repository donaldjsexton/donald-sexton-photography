<?php

namespace Database\Seeders;

use App\Models\Collection;
use Illuminate\Database\Seeder;

class CollectionsSeeder extends Seeder
{
    public function run(): void
    {
        $collections = [
            [
                'slug' => 'essential',
                'name' => 'Essential',
                'headline' => 'Full day, core moments.',
                'summary' => 'Getting ready, ceremony, family photos, portraits, and reception coverage. Everything you need to document the day.',
                'starting_price' => 3800,
                'price_label' => 'Starting at',
                'coverage_hours_min' => 6,
                'coverage_hours_max' => 6,
                'display_order' => 1,
                'status' => 'published',
            ],
            [
                'slug' => 'complete',
                'name' => 'Complete',
                'headline' => 'All day, all moments.',
                'summary' => 'Everything in Essential, plus rehearsal coverage, pre-ceremony prep, and first looks. Capture the full narrative from beginning to end.',
                'starting_price' => 5200,
                'price_label' => 'Starting at',
                'coverage_hours_min' => 8,
                'coverage_hours_max' => 8,
                'display_order' => 2,
                'status' => 'published',
            ],
            [
                'slug' => 'extended',
                'name' => 'Extended',
                'headline' => 'Full coverage, no limits.',
                'summary' => 'Complete coverage plus welcome events, post-ceremony portraits, details, and extended reception. For weddings that demand nothing be missed.',
                'starting_price' => 6800,
                'price_label' => 'Starting at',
                'coverage_hours_min' => 10,
                'coverage_hours_max' => null,
                'display_order' => 3,
                'status' => 'published',
            ],
        ];

        foreach ($collections as $collection) {
            Collection::updateOrCreate(
                ['slug' => $collection['slug']],
                $collection,
            );
        }
    }
}

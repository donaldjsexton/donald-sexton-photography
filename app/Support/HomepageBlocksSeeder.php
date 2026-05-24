<?php

namespace App\Support;

use App\Models\HomepageSetting;

class HomepageBlocksSeeder
{
    /**
     * Default homepage section order. Each maps to a home_* block type whose
     * curated content resolves through HomeContent and whose copy falls back
     * to the existing homepage settings, so a freshly seeded homepage renders
     * identically to the classic layout.
     *
     * @var list<string>
     */
    public const DEFAULT_TYPES = [
        'home_hero',
        'home_statement',
        'home_discover',
        'home_portfolio',
        'home_journal',
        'home_reviews',
        'home_inquiry',
    ];

    /**
     * Create the default home blocks if none exist yet. Idempotent.
     *
     * @param  array<string, array{heading?: ?string, subheading?: ?string, body?: ?string}>  $copy
     *                                                                                               Per-block-type copy overrides keyed by type.
     * @return int Number of blocks created.
     */
    public static function seed(array $copy = []): int
    {
        $settings = HomepageSetting::query()->firstOrCreate([]);

        if ($settings->allBlocks()->exists()) {
            return 0;
        }

        foreach (self::DEFAULT_TYPES as $sortOrder => $type) {
            $settings->allBlocks()->create([
                'type' => $type,
                'heading' => $copy[$type]['heading'] ?? null,
                'subheading' => $copy[$type]['subheading'] ?? null,
                'body' => $copy[$type]['body'] ?? null,
                'is_visible' => true,
                'sort_order' => $sortOrder,
            ]);
        }

        return count(self::DEFAULT_TYPES);
    }
}

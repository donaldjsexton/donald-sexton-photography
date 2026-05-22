<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Block Type Registry
    |--------------------------------------------------------------------------
    |
    | The single source of truth for the composable page builder. Each entry
    | declares an editable block type: its admin label, which shared text
    | fields it exposes, how many attached media items it expects, and any
    | type-specific "data" fields stored in the block's JSON column.
    |
    | The public renderer resolves a Blade component at
    | "components/block-types/{kebab-type}.blade.php" for every type below,
    | so adding a block is: add an entry here + add the matching component.
    |
    | "media" accepts: 0 (none), an integer (fixed count), or 'many'.
    |
    */

    'types' => [

        'rich_text' => [
            'label' => 'Rich Text',
            'fields' => ['heading', 'body'],
            'media' => 0,
        ],

        'hero' => [
            'label' => 'Hero',
            'fields' => ['heading', 'subheading', 'body'],
            'media' => 1,
        ],

        'quote' => [
            'label' => 'Pull Quote',
            'fields' => ['heading', 'body'],
            'media' => 0,
        ],

        'gallery' => [
            'label' => 'Gallery',
            'fields' => ['heading', 'body'],
            'media' => 'many',
        ],

        'full_bleed' => [
            'label' => 'Full Bleed Image',
            'fields' => ['heading', 'body'],
            'media' => 1,
        ],

        'image_pair' => [
            'label' => 'Image Pair',
            'fields' => ['heading', 'body'],
            'media' => 2,
        ],

        'cta' => [
            'label' => 'Call To Action',
            'fields' => ['heading', 'body'],
            'media' => 0,
            'data' => [
                'primary_url' => ['label' => 'Primary button URL'],
                'primary_label' => ['label' => 'Primary button label'],
                'secondary_url' => ['label' => 'Secondary button URL'],
                'secondary_label' => ['label' => 'Secondary button label'],
            ],
        ],

        'spacer' => [
            'label' => 'Spacer',
            'fields' => [],
            'media' => 0,
        ],

        /*
        |----------------------------------------------------------------------
        | Homepage section blocks
        |----------------------------------------------------------------------
        |
        | Art-directed sections that compose the homepage. They are dynamic:
        | their curated content (stories, testimonials, journal posts,
        | collections) resolves through App\Support\HomeContent, while their
        | editable copy lives on the block. Scoped to 'homepage' so they stay
        | out of the generic page palette.
        |
        */

        'home_hero' => [
            'label' => 'Home · Hero',
            'context' => 'homepage',
            'fields' => ['heading', 'body'],
            'media' => 0,
        ],

        'home_statement' => [
            'label' => 'Home · Statement',
            'context' => 'homepage',
            'fields' => [],
            'media' => 0,
        ],

        'home_discover' => [
            'label' => 'Home · Discover',
            'context' => 'homepage',
            'fields' => ['body'],
            'media' => 0,
        ],

        'home_portfolio' => [
            'label' => 'Home · Portfolio',
            'context' => 'homepage',
            'fields' => [],
            'media' => 0,
        ],

        'home_journal' => [
            'label' => 'Home · Journal',
            'context' => 'homepage',
            'fields' => [],
            'media' => 0,
        ],

        'home_reviews' => [
            'label' => 'Home · Reviews',
            'context' => 'homepage',
            'fields' => [],
            'media' => 0,
        ],

        'home_inquiry' => [
            'label' => 'Home · Inquiry',
            'context' => 'homepage',
            'fields' => ['heading', 'body'],
            'media' => 0,
        ],

    ],

];

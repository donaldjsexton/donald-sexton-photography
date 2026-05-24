<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vendor Types
    |--------------------------------------------------------------------------
    |
    | The wedding-industry vendor presets a tenant can be created as. Each
    | drives onboarding: the starter About page copy and the homepage block
    | copy. "labels" hold vendor-facing term overrides reused around the app.
    | Leaving copy null falls back to the generic photographer defaults baked
    | into the home section components.
    |
    */

    'default' => 'photographer',

    'types' => [

        'photographer' => [
            'name' => 'Photographer',
            'labels' => [
                'showcase_plural' => 'Wedding Stories',
                'showcase_singular' => 'Wedding Story',
            ],
            'onboarding' => [
                'about' => [
                    'title' => 'About',
                    'body' => '<p>Introduce yourself and the way you photograph. Tell couples what it feels like to work with you.</p>',
                ],
                'home' => [
                    // Photographer is the baseline; null copy uses the defaults.
                ],
            ],
        ],

        'dj' => [
            'name' => 'DJ',
            'labels' => [
                'showcase_plural' => 'Events',
                'showcase_singular' => 'Event',
            ],
            'onboarding' => [
                'about' => [
                    'title' => 'About',
                    'body' => '<p>Introduce yourself and how you run a reception. Tell couples how you read a room and keep the floor full.</p>',
                ],
                'home' => [
                    'home_hero' => [
                        'heading' => 'Wedding DJ',
                        'body' => 'Music that keeps your celebration moving from first dance to last call.',
                    ],
                    'home_discover' => [
                        'body' => 'Sets built around your crowd, your must-plays, and your hard nos.',
                    ],
                    'home_portfolio' => [
                        'subheading' => 'Recent Events',
                        'heading' => 'Nights worth replaying.',
                    ],
                    'home_inquiry' => [
                        'heading' => 'Check your date in 30 seconds.',
                        'body' => 'Send your date and venue and I will tell you if I am open.',
                    ],
                ],
            ],
        ],

        'officiant' => [
            'name' => 'Officiant',
            'labels' => [
                'showcase_plural' => 'Ceremonies',
                'showcase_singular' => 'Ceremony',
            ],
            'onboarding' => [
                'about' => [
                    'title' => 'About',
                    'body' => '<p>Introduce yourself and your approach to ceremonies. Tell couples how you help their words sound like them.</p>',
                ],
                'home' => [
                    'home_hero' => [
                        'heading' => 'Wedding Officiant',
                        'body' => 'Ceremonies that sound like you — warm, personal, and never off the shelf.',
                    ],
                    'home_discover' => [
                        'body' => 'We write a ceremony around your story, your vows, and the people in the room.',
                    ],
                    'home_portfolio' => [
                        'subheading' => 'Recent Ceremonies',
                        'heading' => 'Moments couples remember.',
                    ],
                    'home_inquiry' => [
                        'heading' => 'Check your date in 30 seconds.',
                        'body' => 'Send your date and venue and I will tell you if I am available.',
                    ],
                ],
            ],
        ],

    ],

];

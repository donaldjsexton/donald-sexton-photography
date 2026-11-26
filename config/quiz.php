<?php

/*
 * Wedding coverage quiz content. Draft copy: edit here, no code changes needed.
 * Sample times assume a 4:30 PM ceremony.
 */
return [
    'tiers' => [
        'essential' => [
            'label' => 'Essential',
            'hours' => 6,
            'price' => '$2,100',
            'intro' => "You're keeping it tight, which is a perfectly good plan. Six hours covers the day without a lot of padding.",
            'timeline' => [
                ['2:30', 'Arrive. Details, dress, a few quiet minutes with whoever you\'re getting ready with.'],
                ['3:30', 'First look or couple portraits, while the light still behaves.'],
                ['4:30', 'Ceremony.'],
                ['5:00', 'Family formals. Keep the list short; the list always grows.'],
                ['5:30', 'Sunset portraits, 15 minutes. Maybe 20 if the sky cooperates.'],
                ['6:00', 'Reception entrance, first dance, toasts.'],
                ['8:30', 'Cake, a couple of dances, and I head out.'],
            ],
            'gap' => 'Gap to know about: no send-off and no cocktail hour candids.',
        ],
        'complete' => [
            'label' => 'Complete',
            'hours' => 8,
            'price' => '$2,800',
            'intro' => 'The one most couples land on. Enough room to cover both getting-ready sides and still not rush the portraits.',
            'timeline' => [
                ['12:30', 'Arrive. Getting ready (one side, then the other), details, letters, the awkward-but-sweet moments.'],
                ['2:30', 'First look, then wedding party portraits.'],
                ['3:30', 'Break. Eat something. Really.'],
                ['4:30', 'Ceremony.'],
                ['5:00', 'Family formals and cocktail hour candids.'],
                ['6:00', 'Sunset portraits, with enough time to do them properly.'],
                ['6:30', 'Reception entrance, dinner, toasts, first dances.'],
                ['8:30', 'Last light of the evening on the dance floor. I head out.'],
            ],
            'gap' => 'Gap to know about: a send-off or late-night dancing may fall just past the end.',
        ],
        'extended' => [
            'label' => 'Extended',
            'hours' => 10,
            'price' => '$3,500',
            'intro' => 'For a day with a welcome party, two locations, or a big guest list. Everything gets covered and nothing is squeezed.',
            'timeline' => [
                ['12:30', 'Arrive. Getting ready on both sides, details, family arriving.'],
                ['2:00', 'First look and wedding party portraits.'],
                ['3:30', 'Travel between locations and ceremony prep.'],
                ['4:30', 'Ceremony.'],
                ['5:00', 'Family formals and cocktail hour.'],
                ['6:00', 'Sunset portraits, unhurried.'],
                ['6:45', 'Reception entrance, dinner, toasts, first dances, cake.'],
                ['8:30', 'Open dance floor, bouquet toss, whatever your crowd actually does.'],
                ['10:30', 'Send-off, then I go home.'],
            ],
            'gap' => null,
        ],
    ],
    'second_photographer' => 'A second photographer (+$1,200) helps with two getting-ready locations or 150+ guests.',
    'elopement' => [
        'label' => 'Elopement',
        'intro' => "That's a different kind of day. Coverage for small ceremonies is quoted by the hour.",
        'url' => '/clearwater-beach-elopement-photographer',
    ],
];

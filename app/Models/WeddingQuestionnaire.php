<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WeddingQuestionnaire extends Model
{
    protected $fillable = [
        'inquiry_id',
        'token',
        'responses',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'responses' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $questionnaire): void {
            if (! $questionnaire->token) {
                $questionnaire->token = Str::random(40);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function publicUrl(): string
    {
        return route('questionnaire.show', $this);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function response(string $key, mixed $default = null): mixed
    {
        return data_get($this->responses, $key, $default);
    }

    /**
     * Form schema: sections of fields. Each field: key, label, type, and optional options.
     *
     * @return array<int, array{title: string, fields: array<int, array<string, mixed>>}>
     */
    public static function schema(): array
    {
        $style = [['value' => '1'], ['value' => '2'], ['value' => '3'], ['value' => '4'], ['value' => '5']];

        return [
            [
                'title' => 'Couple & Contact',
                'fields' => [
                    ['key' => 'wedding_date', 'label' => 'Wedding Date', 'type' => 'date'],
                    ['key' => 'location', 'label' => 'Location', 'type' => 'text'],
                    ['key' => 'bride_name', 'label' => 'Bride Name', 'type' => 'text'],
                    ['key' => 'bride_phone', 'label' => 'Cell Phone Number', 'type' => 'tel'],
                    ['key' => 'bride_can_text', 'label' => 'Can I text you?', 'type' => 'radio', 'options' => ['Yes', 'No']],
                    ['key' => 'bride_email', 'label' => "Bride's Email Address", 'type' => 'email'],
                    ['key' => 'bride_address', 'label' => 'Home Address', 'type' => 'textarea'],
                    ['key' => 'groom_name', 'label' => "Groom's Name", 'type' => 'text'],
                    ['key' => 'groom_phone', 'label' => "Groom's Cell Phone Number", 'type' => 'tel'],
                    ['key' => 'groom_email', 'label' => "Groom's Email Address", 'type' => 'email'],
                ],
            ],
            [
                'title' => 'Day Logistics',
                'fields' => [
                    ['key' => 'bride_prep_address', 'label' => 'Address where bride will prepare', 'type' => 'textarea'],
                    ['key' => 'photographer_report_address', 'label' => 'Photographer Report To Location', 'type' => 'textarea'],
                    ['key' => 'photographer_start_time', 'label' => 'Photographer Start Time', 'type' => 'text'],
                    ['key' => 'ceremony_address', 'label' => 'Address of Ceremony', 'type' => 'textarea'],
                    ['key' => 'ceremony_start_time', 'label' => 'Ceremony Start Time', 'type' => 'text'],
                    ['key' => 'reception_address', 'label' => 'Reception Address', 'type' => 'textarea'],
                    ['key' => 'cocktail_hour_time', 'label' => 'Time of Cocktail Hour', 'type' => 'text'],
                    ['key' => 'formal_photos_location', 'label' => 'Location of Formal Photographs', 'type' => 'text'],
                    ['key' => 'reception_time', 'label' => 'Time of Reception', 'type' => 'text'],
                ],
            ],
            [
                'title' => 'Wedding Party',
                'fields' => [
                    ['key' => 'bridal_party_total', 'label' => 'Total Number in Bridal Party', 'type' => 'number'],
                    ['key' => 'maid_of_honor', 'label' => 'Maid of Honor', 'type' => 'text'],
                    ['key' => 'best_man', 'label' => 'Best Man', 'type' => 'text'],
                    ['key' => 'bridesmaids_count', 'label' => "Number of Bride's Maids", 'type' => 'number'],
                    ['key' => 'groomsmen_count', 'label' => 'Number of Groomsmen', 'type' => 'number'],
                    ['key' => 'kids_in_party', 'label' => 'Ring Bearers, Flower Girls, Other', 'type' => 'checkboxes', 'options' => ['Ring Bearer Only', 'Flower Girls Only', 'Both', 'Flower Men', 'Flower Women', 'Hobbits', 'None']],
                    ['key' => 'guest_count', 'label' => 'Number of invited guests', 'type' => 'number'],
                    ['key' => 'children_present', 'label' => 'Do you and your spouse have children that will be present, names?', 'type' => 'textarea'],
                ],
            ],
            [
                'title' => 'Capture Preferences',
                'fields' => [
                    ['key' => 'photographer_attire', 'label' => "Photographer's desired attire", 'type' => 'text'],
                    ['key' => 'traditions_to_capture', 'label' => 'Any customs or traditions you want me to capture', 'type' => 'textarea'],
                    ['key' => 'family_dynamics', 'label' => 'Any family dynamics you’d like me to be aware of (divorced parents, etc.)', 'type' => 'textarea'],
                    ['key' => 'avoid_capturing', 'label' => 'Anything you would like me NOT to capture', 'type' => 'textarea'],
                    ['key' => 'special_shots', 'label' => 'Any SPECIAL candids or posed portraits that you’d like me to capture', 'type' => 'textarea'],
                    ['key' => 'first_look', 'label' => 'Will you be seeing each other before the wedding ceremony? If yes, will you be interested in doing a "first look"?', 'type' => 'radio', 'options' => ['Yes', 'No']],
                    ['key' => 'first_look_details', 'label' => 'First look details', 'type' => 'textarea'],
                    ['key' => 'ceremony_restrictions', 'label' => 'Are there restrictions', 'type' => 'radio', 'options' => ['Yes', 'No']],
                    ['key' => 'ceremony_restrictions_details', 'label' => 'If "yes", what are they', 'type' => 'textarea'],
                    ['key' => 'vip_list', 'label' => 'VIP List — people we should be sure to photograph (name and relation)', 'type' => 'textarea'],
                    ['key' => 'first_dance_song', 'label' => 'First Dance Song', 'type' => 'text'],
                    ['key' => 'engagement_story', 'label' => 'Engagement Story or Favorite Story from your relationship', 'type' => 'textarea'],
                ],
            ],
            [
                'title' => 'Your Style (1–5)',
                'fields' => [
                    ['key' => 'style_candid', 'label' => 'Candid / Photojournalistic', 'type' => 'radio', 'options' => ['1', '2', '3', '4', '5']],
                    ['key' => 'style_formal', 'label' => 'Formal Posed Photography', 'type' => 'radio', 'options' => ['1', '2', '3', '4', '5']],
                    ['key' => 'style_details', 'label' => 'Wedding Details', 'type' => 'radio', 'options' => ['1', '2', '3', '4', '5']],
                    ['key' => 'style_natural', 'label' => 'Natural Portraiture', 'type' => 'radio', 'options' => ['1', '2', '3', '4', '5']],
                    ['key' => 'meal_preference', 'label' => 'Reception Meal preference', 'type' => 'textarea'],
                ],
            ],
            [
                'title' => 'Shot List — Getting Ready',
                'fields' => [
                    ['key' => 'shots_getting_ready', 'label' => 'Select all that apply', 'type' => 'checkboxes', 'options' => [
                        'The dress, veil, shoes',
                        'Bride in the dressing room, putting on finishing touches',
                        'Portraits of bride',
                        "Mother adjusting bride's veil",
                        'Portrait of mother & bride',
                        "Bride pinning on mother's corsage",
                        'Bridesmaid getting ready',
                        'Attendants helping bride with final preparations',
                        "Bride pinning on father's boutonniere",
                        'Groom getting ready',
                        "Groom pinning on mother's corsage",
                        "Groom pinning on father's boutonniere",
                        'Groom putting on tie',
                        'Groom with best man',
                        'Groom with ring bearer',
                        'Ring Bearer pillow, invitation, Bouquet',
                        'Bride & Groom first look',
                    ]],
                ],
            ],
            [
                'title' => 'Shot List — Ceremony',
                'fields' => [
                    ['key' => 'shots_ceremony', 'label' => 'Select all that apply', 'type' => 'checkboxes', 'options' => [
                        'Inside & Outside of Venue',
                        "Bride's car arriving",
                        'Bride & father waiting to enter',
                        'Guests arriving/waiting at ceremony location',
                        'Groom, best man & groomsmen',
                        'Parents & Grandparents being seated',
                        'Groom entering',
                        'Groom, groomsmen, and officiant standing at the alter',
                        'Wedding party coming down the aisle',
                        'Ring bearer/flower girl',
                        'Father giving away bride',
                        'Unity candle, readings, vows, rings',
                        'Lifting the veil and the kiss',
                        'Bride & groom coming down the aisle',
                        'Receiving line at ceremony site',
                    ]],
                ],
            ],
            [
                'title' => 'Shot List — Posed Portraits',
                'fields' => [
                    ['key' => 'shots_portraits', 'label' => 'Select all that apply', 'type' => 'checkboxes', 'options' => [
                        'Bride with bridesmaids & bride with groomsmen',
                        'Groom with bridesmaids & with groomsmen',
                        'Bride with maid of honor',
                        'Groom with best man',
                        'Bride & Groom with entire wedding party',
                        'Bride with parents/grandparents',
                        'Groom with parents/grandparents',
                        'Bride & Groom with parents',
                        'Bride with siblings',
                        'Groom with siblings',
                        'Bride & Groom with her/his immediate family',
                        'Solo portraits of Bride & Groom',
                        'Bride & Groom together',
                        'Bride & Groom with officiant',
                        'Other portraits as time allows',
                    ]],
                ],
            ],
            [
                'title' => 'Shot List — Reception',
                'fields' => [
                    ['key' => 'shots_reception', 'label' => 'Select all that apply', 'type' => 'checkboxes', 'options' => [
                        'Table settings, place cards, cake, food',
                        'Gift table, guest book, rings',
                        'Entrance to reception',
                        'Blessings, toasts, speakers before the meal',
                        'First dance of bride and groom',
                        'Bride dance with father',
                        'Groom dance with mother',
                        'Best man dancing with maid of honor',
                        'Band or DJ',
                        'Couple cutting the cake',
                        'Throwing & catching bouquet',
                        'Tossing & catching the garter',
                    ]],
                    ['key' => 'custom_shots', 'label' => 'Custom Shots', 'type' => 'textarea'],
                ],
            ],
        ];
    }

    /**
     * Flat [key => label] map for admin display.
     *
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        $labels = [];

        foreach (self::schema() as $section) {
            foreach ($section['fields'] as $field) {
                $labels[$field['key']] = $field['label'];
            }
        }

        return $labels;
    }
}

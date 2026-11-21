<?php

namespace App\Services\Cms;

use App\Models\Venue;
use App\Tenancy\CurrentSite;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VenueManager
{
    /**
     * Fields a remote CMS client may change. Billing and portal fields stay
     * admin-only.
     *
     * @var list<string>
     */
    public const PUBLIC_FIELDS = [
        'name', 'slug', 'city', 'state', 'region', 'headline', 'summary', 'body',
        'hero_media_id', 'website_url', 'google_places_id', 'is_featured',
        'seo_title', 'seo_description', 'faqs_text',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(?Venue $venue = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('venues', 'slug')->where('site_id', app(CurrentSite::class)->id())->ignore($venue?->id)],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'google_places_id' => ['nullable', 'string', 'max:255'],
            'referral_emails' => ['nullable', 'string'],
            'referral_contact_name' => ['nullable', 'string', 'max:255'],
            'referral_requires_approval' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'faqs_text' => ['nullable', 'string', 'max:8000'],

            'business_name' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_contact_name' => ['nullable', 'string', 'max:255'],
            'billing_address_line_1' => ['nullable', 'string', 'max:255'],
            'billing_address_line_2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'billing_country' => ['nullable', 'string', 'size:2'],
            'net_payment_terms' => ['nullable', 'string', 'max:50'],
            'portal_password' => ['nullable', 'string', 'min:8'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(Venue $venue, array $validated, bool $partial = false): void
    {
        $portalPassword = $validated['portal_password'] ?? null;
        $faqsText = $validated['faqs_text'] ?? null;
        unset($validated['portal_password'], $validated['faqs_text']);

        $venue->fill($validated);

        if (! $partial || array_key_exists('slug', $validated)) {
            $venue->slug = ! empty($validated['slug']) ? $validated['slug'] : Str::slug($validated['name'] ?? $venue->name);
        }

        if (! $partial || array_key_exists('is_featured', $validated)) {
            $venue->is_featured = (bool) ($validated['is_featured'] ?? false);
        }

        if (! $partial || array_key_exists('referral_requires_approval', $validated)) {
            $venue->referral_requires_approval = (bool) ($validated['referral_requires_approval'] ?? false);
        }

        if (! $partial || array_key_exists('referral_emails', $validated)) {
            $venue->referral_emails = $this->parseReferralEmails($validated['referral_emails'] ?? null);
        }

        if (! $partial || $faqsText !== null) {
            $venue->faqs = FaqText::parse($faqsText);
        }

        if (filled($portalPassword)) {
            $venue->password = $portalPassword;
        }

        $venue->save();
    }

    /**
     * @return array<int, string>|null
     */
    private function parseReferralEmails(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $emails = collect(preg_split('/[\s,;]+/', $raw))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        return $emails === [] ? null : $emails;
    }
}

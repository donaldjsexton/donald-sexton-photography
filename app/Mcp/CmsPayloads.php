<?php

namespace App\Mcp;

use App\Models\Block;
use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Contract;
use App\Models\HomepageSetting;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLineItem;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Venue;

/**
 * Response shapes for the management tools. Every shape is an allowlist, so a
 * new column on a model is not exposed until it is added here.
 */
class CmsPayloads
{
    /**
     * @return array<string, mixed>
     */
    public function page(Page $page, bool $detail = false): array
    {
        $payload = [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'template' => $page->template,
            'status' => $page->status,
            'published_at' => $page->published_at?->toIso8601String(),
            'sort_order' => $page->sort_order,
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
        ];

        if (! $detail) {
            return $payload;
        }

        $page->load(['allBlocks', 'media']);

        return $payload + [
            'excerpt' => $page->excerpt,
            'body' => $page->body,
            'hero_media_id' => $page->hero_media_id,
            'canonical_url' => $page->canonical_url,
            'faqs' => $page->faqs,
            'blocks' => $page->allBlocks->map(fn (Block $block): array => $this->block($block))->values()->all(),
            'media' => $page->media->map(fn (Media $media): array => $this->media($media))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function block(Block $block): array
    {
        return [
            'id' => $block->id,
            'type' => $block->type,
            'heading' => $block->heading,
            'subheading' => $block->subheading,
            'body' => $block->body,
            'data' => $block->data,
            'is_visible' => (bool) $block->is_visible,
            'sort_order' => $block->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function journalPost(JournalPost $post, bool $detail = false): array
    {
        $payload = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'post_type' => $post->post_type,
            'published_at' => $post->published_at?->toIso8601String(),
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
        ];

        if (! $detail) {
            return $payload;
        }

        $post->load(['categories', 'tags', 'venues']);

        return $payload + [
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'hero_media_id' => $post->hero_media_id,
            'gallery_id' => $post->gallery_id,
            'author_name' => $post->author_name,
            'canonical_url' => $post->canonical_url,
            'category_ids' => $post->categories->pluck('id')->all(),
            'tag_ids' => $post->tags->pluck('id')->all(),
            'venue_ids' => $post->venues->pluck('id')->all(),
        ];
    }

    /**
     * Public venue fields only. Billing, referral, and portal fields are not
     * exposed.
     *
     * @return array<string, mixed>
     */
    public function venue(Venue $venue, bool $detail = false): array
    {
        $payload = [
            'id' => $venue->id,
            'name' => $venue->name,
            'slug' => $venue->slug,
            'city' => $venue->city,
            'state' => $venue->state,
            'region' => $venue->region,
            'is_featured' => (bool) $venue->is_featured,
        ];

        if (! $detail) {
            return $payload;
        }

        return $payload + [
            'headline' => $venue->headline,
            'summary' => $venue->summary,
            'body' => $venue->body,
            'hero_media_id' => $venue->hero_media_id,
            'website_url' => $venue->website_url,
            'seo_title' => $venue->seo_title,
            'seo_description' => $venue->seo_description,
            'faqs' => $venue->faqs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function media(Media $media): array
    {
        return [
            'id' => $media->id,
            'filename' => $media->filename,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'url' => $media->publicUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function redirect(Redirect $redirect): array
    {
        return [
            'id' => $redirect->id,
            'from_path' => $redirect->from_path,
            'to_path' => $redirect->to_path,
            'status_code' => $redirect->status_code,
            'source' => $redirect->source,
        ];
    }

    /**
     * Contact fields are limited to names, email, status, and dates. Phone,
     * message text, consent records, tracking, and mail ids are not exposed.
     *
     * @return array<string, mixed>
     */
    public function inquiry(Inquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'status' => $inquiry->status,
            'primary_name' => $inquiry->primary_name,
            'partner_name' => $inquiry->partner_name,
            'email' => $inquiry->email,
            'event_type' => $inquiry->event_type,
            'event_date' => $inquiry->event_date?->toDateString(),
            'venue_name' => $inquiry->venue_name,
            'location_city' => $inquiry->location_city,
            'guest_count_range' => $inquiry->guest_count_range,
            'budget_range' => $inquiry->budget_range,
            'client_id' => $inquiry->client_id,
            'first_responded_at' => $inquiry->first_responded_at?->toIso8601String(),
            'created_at' => $inquiry->created_at?->toIso8601String(),
        ];
    }

    /**
     * Name, email, and counts only. No phone, address, notes, or portal data.
     *
     * @return array<string, mixed>
     */
    public function client(Client $client): array
    {
        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'partner_first_name' => $client->partner_first_name,
            'partner_last_name' => $client->partner_last_name,
            'email' => $client->email,
            'inquiries_count' => $client->inquiries_count ?? null,
            'invoices_count' => $client->invoices_count ?? null,
            'created_at' => $client->created_at?->toIso8601String(),
        ];
    }

    /**
     * No coordinator notes, calendar event ids, or raw calendar descriptions.
     *
     * @return array<string, mixed>
     */
    public function bookedJob(BookedJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status,
            'summary' => $job->summary,
            'couple_names' => $job->couple_names,
            'event_type' => $job->event_type,
            'event_date' => $job->event_date?->toDateString(),
            'previous_event_date' => $job->previous_event_date?->toDateString(),
            'event_time' => $job->event_time,
            'location' => $job->location,
            'inquiry_id' => $job->inquiry_id,
        ];
    }

    /**
     * Amounts and schedule only. No public portal id, payment records,
     * payment tokens, or internal notes.
     *
     * @return array<string, mixed>
     */
    public function invoice(Invoice $invoice, bool $detail = false): array
    {
        $payload = [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'client_name' => $invoice->billableName(),
            'booked_job_id' => $invoice->booked_job_id,
            'subtotal_cents' => $invoice->subtotal_cents,
            'discount_cents' => $invoice->discount_cents,
            'tax_cents' => $invoice->tax_cents,
            'total_cents' => $invoice->total_cents,
            'amount_paid_cents' => $invoice->amount_paid_cents,
            'amount_due_cents' => $invoice->amountDueCents(),
            'sent_at' => $invoice->sent_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ];

        if (! $detail) {
            return $payload;
        }

        $invoice->load(['lineItems', 'installments']);

        return $payload + [
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'line_items' => $invoice->lineItems->map(fn (InvoiceLineItem $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'total_cents' => $item->total_cents,
            ])->values()->all(),
            'installments' => $invoice->installments->map(fn (InvoiceInstallment $installment): array => [
                'sequence' => $installment->sequence,
                'label' => $installment->label,
                'due_date' => $installment->due_date?->toDateString(),
                'amount_cents' => $installment->amount_cents,
                'amount_paid_cents' => $installment->amount_paid_cents,
                'status' => $installment->status,
            ])->values()->all(),
        ];
    }

    /**
     * No signer IP or user agent, public portal id, or internal notes.
     *
     * @return array<string, mixed>
     */
    public function contract(Contract $contract, bool $detail = false): array
    {
        $payload = [
            'id' => $contract->id,
            'number' => $contract->number,
            'status' => $contract->status,
            'title' => $contract->title,
            'client_name' => $contract->billableName(),
            'invoice_id' => $contract->invoice_id,
            'booked_job_id' => $contract->booked_job_id,
            'issue_date' => $contract->issue_date?->toDateString(),
            'expires_at' => $contract->expires_at?->toDateString(),
            'sent_at' => $contract->sent_at?->toIso8601String(),
            'viewed_at' => $contract->viewed_at?->toIso8601String(),
            'signed_at' => $contract->signed_at?->toIso8601String(),
            'declined_at' => $contract->declined_at?->toIso8601String(),
        ];

        return $detail ? $payload + ['body' => $contract->body] : $payload;
    }

    /**
     * A proposal is a contract with its invoice, as built by the admin
     * proposal screen.
     *
     * @return array<string, mixed>
     */
    public function proposal(Contract $contract, bool $detail = false): array
    {
        return [
            'contract' => $this->contract($contract, $detail),
            'invoice' => $contract->invoice instanceof Invoice ? $this->invoice($contract->invoice, $detail) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function homepage(HomepageSetting $settings): array
    {
        $settings->loadMissing('allBlocks');

        return [
            'hero_heading' => $settings->hero_heading,
            'hero_subheading' => $settings->hero_subheading,
            'hero_media_id' => $settings->hero_media_id,
            'featured_story_ids_json' => $settings->featured_story_ids_json,
            'featured_testimonial_ids_json' => $settings->featured_testimonial_ids_json,
            'featured_journal_post_ids_json' => $settings->featured_journal_post_ids_json,
            'investment_teaser' => $settings->investment_teaser,
            'final_cta_heading' => $settings->final_cta_heading,
            'final_cta_body' => $settings->final_cta_body,
            'blocks' => $settings->exists
                ? $settings->allBlocks->map(fn (Block $block): array => $this->block($block))->values()->all()
                : [],
        ];
    }
}

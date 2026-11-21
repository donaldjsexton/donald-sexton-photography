<?php

namespace App\Mcp;

use App\Models\Block;
use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Inquiry;
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
}

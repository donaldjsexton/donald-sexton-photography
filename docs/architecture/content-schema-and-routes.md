# Content Schema And Routes

## Goals

- Keep the business wedding-first.
- Present the frontend with an editorial-luxury rhythm: large imagery, sparse navigation, strong storytelling.
- Preserve the journal/blog as a long-tail SEO asset.
- Make inquiry the primary conversion path without turning the site into a gimmicky chat app.
- Support a staged WordPress migration with re-runnable imports, slug preservation, and 301 redirects.

## Content Model

### 1. `pages`

Use for small marketing pages that are not journal posts or portfolio stories.

Fields:

- `id`
- `title`
- `slug`
- `template`
  Values: `home`, `about`, `collections`, `faq`, `location`, `custom`
- `status`
  Values: `draft`, `published`, `archived`
- `excerpt`
- `body`
- `hero_media_id`
- `seo_title`
- `seo_description`
- `canonical_url`
- `published_at`
- `sort_order`
- `created_at`
- `updated_at`

Notes:

- `home` will likely be route-driven instead of a fully freeform CMS page, but keeping the record allows editorial control over SEO and selected modules.
- `location` pages can target `clearwater-wedding-photographer`, `tampa-wedding-photographer`, etc.

### 2. `collections`

Represents investment/package content.

Fields:

- `id`
- `name`
- `slug`
- `headline`
- `summary`
- `description`
- `starting_price`
- `price_label`
  Examples: `Collections begin at`, `Most couples invest`
- `coverage_hours_min`
- `coverage_hours_max`
- `display_order`
- `is_featured`
- `status`
- `seo_title`
- `seo_description`
- `created_at`
- `updated_at`

Why this exists separately:

- Investment content needs to be manageable and reusable in the homepage, collections page, and inquiry qualification logic.

### 3. `wedding_stories`

Primary portfolio/storytelling type. This should carry full wedding features, not generic galleries.

Fields:

- `id`
- `title`
- `slug`
- `status`
  Values: `draft`, `published`, `archived`
- `story_type`
  Values: `wedding`, `elopement`, `engagement`, `editorial`
- `headline`
- `excerpt`
- `body`
- `hero_media_id`
- `event_date`
- `location_name`
- `city`
- `state`
- `venue_id`
- `client_names`
- `is_featured`
- `display_order`
- `seo_title`
- `seo_description`
- `canonical_url`
- `published_at`
- `created_at`
- `updated_at`

Relationships:

- belongs to `venue` optionally
- has many `media`
- has many `story_blocks`
- belongs to many `tags`

### 4. `story_blocks`

Structured editorial sections inside a wedding story.

Fields:

- `id`
- `wedding_story_id`
- `block_type`
  Values: `text`, `image_pair`, `full_bleed_image`, `carousel`, `quote`, `spacer`
- `heading`
- `body`
- `settings_json`
- `sort_order`

Why this exists:

- Prevents the portfolio from collapsing into one big rich-text blob or generic card gallery.
- Supports more art-directed long-form presentation.

### 5. `journal_posts`

SEO and content marketing engine. This is where imported WordPress posts land unless promoted into `wedding_stories` or `pages`.

Fields:

- `id`
- `title`
- `slug`
- `status`
- `post_type`
  Values: `advice`, `venue`, `real_wedding`, `engagement`, `brand`, `announcement`
- `excerpt`
- `body`
- `hero_media_id`
- `author_name`
- `published_at`
- `original_wp_post_id`
- `original_wp_url`
- `seo_title`
- `seo_description`
- `canonical_url`
- `created_at`
- `updated_at`

Relationships:

- belongs to many `categories`
- belongs to many `tags`
- belongs to many `venues`
- has many `media`

### 6. `venues`

Dedicated venue/location entity for both editorial and SEO.

Fields:

- `id`
- `name`
- `slug`
- `city`
- `state`
- `region`
- `headline`
- `summary`
- `body`
- `hero_media_id`
- `website_url`
- `is_featured`
- `seo_title`
- `seo_description`
- `created_at`
- `updated_at`

Relationships:

- has many `wedding_stories`
- belongs to many `journal_posts`

Why this matters:

- Lets you build venue landing pages that aggregate real weddings and blog content around a location.

### 7. `testimonials`

Fields:

- `id`
- `quote`
- `author_name`
- `author_context`
  Examples: `Bride`, `Bride & Groom`, `Planner`
- `event_date`
- `is_featured`
- `source`
  Values: `manual`, `google`, `facebook`, `imported`
- `sort_order`
- `created_at`
- `updated_at`

### 8. `inquiries`

Primary conversion record.

Fields:

- `id`
- `primary_name`
- `partner_name`
- `email`
- `phone`
- `instagram_handle`
- `event_type`
  Default `wedding`
- `event_date`
- `venue_name`
- `venue_id`
- `location_city`
- `guest_count_range`
- `budget_range`
- `coverage_interest`
- `heard_about`
- `message`
- `status`
  Values: `new`, `contacted`, `qualified`, `booked`, `archived`, `spam`
- `source`
  Values: `site_form`, `manual`, `imported`
- `utm_source`
- `utm_medium`
- `utm_campaign`
- `created_at`
- `updated_at`

Notes:

- This should remain focused enough to qualify leads but not feel like an application form.
- A lightweight SMS or email automation layer can be attached later without changing the content model.

### 9. `media`

Centralized imported and uploaded media store.

Fields:

- `id`
- `disk`
- `path`
- `filename`
- `mime_type`
- `width`
- `height`
- `alt_text`
- `caption`
- `credit`
- `focal_point_x`
- `focal_point_y`
- `original_wp_attachment_id`
- `created_at`
- `updated_at`

Polymorphic relationships:

- attachable to `pages`
- attachable to `wedding_stories`
- attachable to `journal_posts`
- attachable to `venues`

### 10. `categories`

For journal taxonomy.

Fields:

- `id`
- `name`
- `slug`
- `description`
- `seo_title`
- `seo_description`

Expected categories:

- `Wedding Advice`
- `Real Weddings`
- `Venues`
- `Engagements`

### 11. `tags`

Use sparingly. Prefer controlled tags over WordPress tag sprawl.

Fields:

- `id`
- `name`
- `slug`

### 12. `redirects`

Required for WordPress cutover.

Fields:

- `id`
- `from_path`
- `to_path`
- `status_code`
  Default `301`
- `source`
  Values: `wp_import`, `manual`
- `created_at`
- `updated_at`

### 13. `import_runs`

Tracks repeatable WordPress import executions.

Fields:

- `id`
- `source_type`
  Value initially `wordpress`
- `status`
  Values: `pending`, `running`, `completed`, `failed`
- `started_at`
- `finished_at`
- `summary_json`
- `error_log`
- `created_at`
- `updated_at`

### 14. `import_mappings`

Maps old WordPress records to new Laravel records.

Fields:

- `id`
- `import_run_id`
- `source_table`
- `source_id`
- `source_url`
- `target_type`
- `target_id`
- `created_at`
- `updated_at`

## Core Relationships

- `pages` belongs to `media` as hero.
- `collections` can be embedded into `pages` or rendered directly on the collections route.
- `wedding_stories` belongs to one `venue` optionally.
- `wedding_stories` has many ordered `story_blocks`.
- `wedding_stories` belongs to many `tags`.
- `journal_posts` belongs to many `categories`.
- `journal_posts` belongs to many `tags`.
- `journal_posts` belongs to many `venues`.
- `venues` has many `wedding_stories`.
- `media` is reused across stories, journal posts, and pages.

## Public Route Map

Prefer short, clean routes. Preserve legacy slugs through `redirects`.

### Core Marketing Routes

- `GET /`
  Home page.
- `GET /about`
  Brand, philosophy, and trust page.
- `GET /collections`
  Investment page with starting pricing, collection framing, FAQs, and CTA.
- `GET /inquire`
  Primary inquiry page.
- `POST /inquire`
  Form submission endpoint.
- `GET /thank-you`
  Post-submission confirmation page.

### Portfolio Routes

- `GET /weddings`
  Portfolio landing page for featured wedding stories.
- `GET /weddings/{slug}`
  Individual wedding story.

Optional future expansion:

- `GET /engagements`
- `GET /engagements/{slug}`

I would not expose these until the wedding-first positioning needs to broaden.

### Journal Routes

- `GET /journal`
  Journal index.
- `GET /journal/category/{slug}`
  Category archive.
- `GET /journal/tag/{slug}`
  Tag archive if retained.
- `GET /journal/{slug}`
  Individual journal post.

### Venue And Location SEO Routes

- `GET /venues`
  Optional index only if enough venue content exists.
- `GET /venues/{slug}`
  Venue landing page with related stories and journal posts.
- `GET /locations/{slug}`
  City or region landing page.

Examples:

- `/venues/knotted-roots-on-the-lake`
- `/locations/clearwater-wedding-photographer`
- `/locations/tampa-wedding-photographer`

### Utility Routes

- `GET /sitemap.xml`
- `GET /feed.xml`
  Optional if journal syndication matters.
- `GET /robots.txt`

### Redirect And Legacy Resolution

- Unmatched requests should check `redirects` before returning 404.
- Imported WordPress paths should be preserved where reasonable.
- If an old post slug moves from `/old-post/` to `/journal/old-post`, add a 301.

## Admin Route Map

Assuming Filament for admin:

- `GET /admin`
- `GET /admin/pages`
- `GET /admin/collections`
- `GET /admin/wedding-stories`
- `GET /admin/journal-posts`
- `GET /admin/venues`
- `GET /admin/testimonials`
- `GET /admin/inquiries`
- `GET /admin/media`
- `GET /admin/redirects`
- `GET /admin/import-runs`

Custom admin actions:

- `POST /admin/imports/wordpress/run`
- `POST /admin/imports/wordpress/dry-run`
- `POST /admin/imports/wordpress/rebuild-redirects`

## Route Resolution Rules

To avoid collisions:

1. Reserve top-level slugs:
   - `about`
   - `collections`
   - `inquire`
   - `thank-you`
   - `weddings`
   - `journal`
   - `venues`
   - `locations`
   - `admin`

2. Keep public story and journal slugs unique within their own namespaces, not globally.

3. Do not put journal posts at the top level.

Reason:

- Namespaced content avoids WordPress-style route ambiguity and makes redirects easier to manage.

## Homepage Composition

The homepage should be route-driven with configurable selections, not a generic page-builder canvas.

Recommended sections:

- Hero
- Featured wedding stories
- Brand statement
- Process or experience section
- Investment teaser
- Testimonials
- Journal or venue spotlight block
- Inquiry CTA

Suggested support table:

### `homepage_settings`

Fields:

- `id`
- `hero_heading`
- `hero_subheading`
- `hero_media_id`
- `featured_story_ids_json`
- `featured_testimonial_ids_json`
- `featured_journal_post_ids_json`
- `investment_teaser`
- `final_cta_heading`
- `final_cta_body`
- `updated_at`

This keeps the homepage curated without forcing every section into a page-builder abstraction.

## WordPress Migration Rules

Default migration targets:

- WordPress blog posts -> `journal_posts`
- WordPress wedding features/galleries -> `wedding_stories` when clearly portfolio-worthy
- WordPress pages worth preserving -> `pages`
- WordPress media library -> `media`
- WordPress categories/tags -> `categories` and `tags`
- Old URLs -> `redirects`

Rules:

- Preserve publish dates.
- Preserve slugs where possible.
- Capture original WordPress IDs and URLs for repeatable imports.
- Strip or transform shortcodes during import.
- Normalize oversized tag sets instead of importing all tag noise blindly.

## Initial Build Recommendation

Build these first:

1. `pages`
2. `collections`
3. `wedding_stories`
4. `story_blocks`
5. `journal_posts`
6. `venues`
7. `testimonials`
8. `inquiries`
9. `media`
10. `redirects`
11. `import_runs`
12. `import_mappings`

That gives enough structure to ship the core site and migrate WordPress content without redesigning the database midstream.

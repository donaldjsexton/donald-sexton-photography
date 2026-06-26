# Project Unification Plan

> Consolidating `java-photo-gallery` (Spring Boot gallery engine) into
> `donald-sexton-photography` (Laravel business platform).

## Decision

The Laravel app is the host. The Java Photo Gallery is reimplemented as a
native **Galleries** domain inside this Laravel application. The Java repo is
retained as the **authoritative functional spec** for the ingestion and
delivery behaviors, not as a running service.

This was chosen over a polyglot microservice split because:

- The Laravel app is the production business platform (CRM, billing, portal,
  CMS, multi-tenant) and is already deployed at `/srv/dsp`.
- The whole value of unification is wiring client galleries into the CRM
  (client → gallery → booked job → invoice → contract). That linkage is
  trivial in one stack and painful across two.
- A second language + second database + cross-service auth + tenant-sync glue
  doubles ongoing ops cost on a single production box, for a solo studio, with
  no offsetting benefit — every Java strength (dedup, EXIF, variants, signed
  URLs, share tokens) is reproducible in Laravel.

## What we're actually gaining

Today the Laravel site **outsources client-gallery delivery to Pic-Time**
(see `app/Models/Concerns/InteractsWithPicTime.php`). Its own "galleries" are
editorial image sets embedded in journal posts and wedding stories. There is
no native proofing, sharing, signed access, or downloads.

The Java engine is exactly that missing capability. Unifying brings client
galleries *inside* the branded site and the CRM.

## Domain mapping (Java → Laravel)

| Java | Laravel target | Notes |
|------|----------------|-------|
| `Tenant`, `TenantResolutionFilter`, `TenantContext` | existing `Site` / `SiteDomain` + domain resolution | No new tenancy. Map `Tenant.slug` ↔ `Site.subdomain`. |
| `User`, `Membership`, `MembershipRole` | existing `User` (admin) + `Client` (portal) | Gallery access for clients flows through the existing portal auth, not a new membership table. |
| `Gallery` | new `Gallery` model | Top-level client gallery, e.g. "Smith Wedding". Site-scoped. |
| `Album`, `AlbumVisibility`, `AlbumCoverService` | new `Album` model | Sub-collection within a gallery; `visibility` (public/private); `cover_photo_id`. |
| `Category` | existing taxonomy or `Album` grouping | Defer; likely not needed at MVP. |
| `Photo`, `GalleryPhoto` | new `Photo` model + `album_photo` pivot | Distinct from editorial `Media`. Stores `sha256`, dimensions, EXIF, disk/path, variant paths. |
| `ShareToken`, `ShareTokenService`, `ShareController` | new `ShareToken` model + public share routes | Public client links to a gallery/album. |
| `ExifService` | PHP EXIF (Intervention Image v3 / native `exif_read_data`) | Non-blocking, tolerant of missing data — mirror Java. |
| `LocalPhotoStorageService` / `R2PhotoStorageService` / `PhotoStorageService` | existing Laravel `s3` disk (R2 endpoint already configured) | One disk abstraction already exists. Key layout `galleries/{site}/{gallery}/{uuid}.{ext}`. |
| `SignedUrlService`, `GallerySignedUrlController` | `URL::temporarySignedRoute` + R2 `Storage::temporaryUrl` | Native Laravel signed/presigned URLs. |
| `DownloadService`, `DownloadController` | streamed ZIP route (ZipStream) | Whole-gallery / selection download. |
| `PhotoSearchService` | Eloquent scopes | — |
| Spring Security / local auth | existing Laravel auth + portal | Already done on the Laravel side. |

## Ingestion behaviors to preserve (from the Java spec)

These are the reasons the Java app exists; carry them over verbatim in intent:

- **Hash-first dedup** — compute SHA-256 before storage expansion; unique per
  site. Re-upload of an identical file is a no-op pointing at the existing row.
- **Idempotent / restart-safe** uploads — safe to retry; partial failures leave
  a recoverable state, never a half-written gallery.
- **Non-blocking metadata** — EXIF extraction failure must not block storage.
- **Fail-fast on corrupt files** — validate before persistence.
- **Auditable outcomes** — record success / duplicate / failure per upload.

## Implementation status

Phases 1–6 are **implemented, tested, and merged** on
`claude/project-unification-plan-qiexk2`. Phase 7 is documentation-only and is
covered below. One deviation from the original plan: the ingestion pipeline was
built on PHP's native **GD + ext-exif** (mirroring the existing `MediaOptimizer`)
rather than Intervention Image, so **no new dependency was added**.

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | Gallery data model (Gallery/Album/Photo/ShareToken, site-scoped) | ✅ Done |
| 2 | Ingestion pipeline (hash dedup, EXIF, WebP variants) | ✅ Done |
| 3 | Delivery (share tokens, signed streaming, ZIP, public views) | ✅ Done |
| 4 | Admin CMS (galleries, albums, uploads, covers, share links) | ✅ Done |
| 5 | CRM integration (client/job links, portal, opt-in payment gate) | ✅ Done |
| 6 | Editorial native galleries (wedding stories / journal posts) | ✅ Done |
| 7 | Decommission Java repo as spec-of-record (no data import) | ✅ Done |

All migrations remain **unmigrated in production** — they run on deploy only
after `--pretend` review and owner confirmation, per the safety notes below.

## Phased roadmap

### Phase 0 — Scaffolding & decisions
- Confirm R2 bucket + credentials for the `s3` disk (`.env`, **needs owner
  confirmation** per production rules).
- Add Intervention Image (image variants/EXIF) — **dependency change, needs
  approval**.
- Decide `Photo` as a new model vs. extending `Media`. Recommendation: **new
  `Photo` model** — `Media` is editorial/CMS-shaped; client photos need hash,
  variants, and album membership without polluting CMS media.

### Phase 1 — Gallery data model
- Migrations: `galleries`, `albums`, `photos`, `album_photo`, `share_tokens`,
  all `site_id`-scoped via the existing `BelongsToSite` concern.
- Models + factories + the `BelongsToSite` trait, following sibling conventions.
- Tests: site-scoping, relationships, slug/publicId generation.

### Phase 2 — Ingestion pipeline
- Upload service implementing the preserved behaviors above (hash dedup, EXIF,
  variant generation, audit record).
- Storage keyed `galleries/{site}/{gallery}/{uuid}.{ext}` on the `s3`/R2 disk.
- Tests for happy path, duplicate, corrupt file, missing-EXIF, retry.

### Phase 3 — Delivery (the client-facing half)
- `ShareToken` model + public share routes (gallery + album), token-guarded.
- Signed / temporary URLs for protected originals (Laravel signed routes / R2
  presign).
- ZIP download route (full gallery + selection).
- Public gallery/album Blade views (responsive per UI/UX rules — grids collapse
  4→2→1, 44px touch targets).

### Phase 4 — Admin CMS for galleries
- Admin controllers under `app/Http/Controllers/Admin/` (create gallery, upload,
  organize albums, set cover, manage/revoke share links), matching existing
  admin patterns.
- Mobile table/grid strategy decided up front per CLAUDE.md UI rules.

### Phase 5 — CRM integration (the prize)
- `galleries.client_id` (+ optional `booked_job_id`) linking a gallery to a
  CRM client / job.
- Surface galleries in the client portal (`app/Http/Controllers/Portal/`):
  logged-in clients see their own galleries.
- **Optional billing gate** (default off): a per-gallery flag
  (e.g. `requires_payment`) that, when set, gates full-resolution download /
  final-gallery access on a paid invoice. Galleries are **ungated by default** —
  the gate is opt-in per gallery, never the standing behavior.

### Phase 6 — Reduce Pic-Time dependence (gradual)
- Let journal posts / wedding stories reference a native `Gallery` instead of
  an external Pic-Time URL, alongside `InteractsWithPicTime` during transition.
- No big-bang cutover; both coexist until native galleries cover the workflow.

### Phase 7 — Decommission Java service
- Archive `java-photo-gallery` as the spec-of-record.
- **No data import.** Treated as greenfield by owner decision — production
  galleries currently live in Pic-Time, so there is nothing to migrate out of
  the Java instance. No importer is built.

## Production safety notes

Per this repo's `CLAUDE.md` (live infrastructure at `/srv/dsp`):

- Migrations run only after `--pretend` review and **owner confirmation**.
- `.env` / R2 credential changes require **confirmation**.
- New Caddy-served gallery directories must be ≥ `0755` dirs / `0644` files.
- Adding Intervention Image is a dependency change requiring **approval**.

## Resolved decisions (owner)

1. **Greenfield** — no migration from the Java instance. Phase 7 builds no
   importer.
2. **Reuse the existing R2 bucket** — client galleries share the configured
   `s3` (R2) disk/bucket, segmented by key prefix
   (`galleries/{site}/{gallery}/...`), not a separate bucket.
3. **Gating available, default off** — per-gallery opt-in payment gate on
   full-res download; galleries are ungated unless explicitly flagged.

# Donald Sexton Photography

Laravel build for an editorial wedding photography site with:

- homepage, portfolio, journal, venues, collections, and inquiry routes
- structured content models for stories, journal posts, pages, venues, testimonials, and collections
- Vite-powered frontend assets
- tests covering public route resolution, inquiry persistence, SEO metadata, and redirect behavior

## Stack

- PHP / Laravel
- Blade templates
- Vite
- Tailwind v4 as the asset pipeline base
- SQLite for local development by default

## Local Setup

1. Install PHP and Node dependencies.

```bash
composer install
npm install
```

2. Create the environment file and app key.

```bash
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
```

3. Run the database migrations and expose the public media path.

```bash
php artisan migrate
php artisan storage:link
```

4. Start the app.

```bash
php artisan serve
```

For frontend asset development:

```bash
npm run dev
```

For a production asset build:

```bash
npm run build
```

## Admin CMS

Create an admin-capable user:

```bash
php artisan admin:user admin@example.com --name="Site Admin"
```

Then sign in at `/admin/login`.

The current light CMS covers:

- homepage curation
- media uploads and metadata
- page editing
- wedding story editing
- journal post editing
- WordPress XML import for journal posts, categories, tags, mappings, redirects, and real wedding promotion
- temporary Pic-Time import for blog pages that need to be parsed into local content and media

The WordPress importer still does not download the full WordPress media library, but it does now promote imported real weddings into `wedding_stories`.

For large WordPress exports that are too big for browser upload limits, import directly from disk:

```bash
php artisan wordpress:import /absolute/path/to/wordpress-export.xml
```

For Pic-Time blog pages, import one or more remote URLs, WordPress XML exports that contain Pic-Time entries, saved HTML files, or directories of saved HTML:

```bash
php artisan pictime:import https://gallery.example.com/post-one /absolute/path/to/export.xml /absolute/path/to/saved-pages --target=auto
```

`--target=auto` classifies gallery-heavy posts as wedding stories. You can force `--target=weddings` or `--target=journal` when needed.

## Testing

Run the application test suite:

```bash
php artisan test
```

Run the deploy smoke checks:

```bash
php artisan launch:check
```

## Deployment Checklist

Before deploying, confirm:

- `.env` is configured for the target environment
- `APP_ENV`, `APP_URL`, and database credentials are correct
- `FILESYSTEM_DISK` matches the media strategy for the environment
- migrations have been run
- `php artisan storage:link` has been run anywhere the site uses the `public` disk
- `npm run build` has produced current frontend assets
- writable directories are available for `storage/` and `bootstrap/cache/`
- public media storage is configured correctly if uploaded content is in use
- canonical URLs and SEO content are set for key public pages
- `php artisan launch:check` passes against the target environment

Typical production commands:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

If uploaded media will stay on the local/public disk in production, the deploy target needs a persistent `storage/` directory and a valid `public/storage` symlink. If media will move to S3, make sure each media record is written with `disk = s3` and the corresponding AWS variables are set in the environment.

## Current Priorities

- finish the operational launch checklist around media, env defaults, and deployment assumptions
- replace remaining generic or fallback copy with final brand-approved language
- keep public route behavior stable under partial content states
- extend the CMS into venues, collections, testimonials, and inquiry management

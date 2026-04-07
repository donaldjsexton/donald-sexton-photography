# Launch Readiness

## Environment

- Set `APP_NAME`, `APP_ENV`, `APP_DEBUG`, and `APP_URL` for the target environment.
- Use PostgreSQL in production. The local SQLite default is only for development.
- Use a real SMTP provider in production. The selected provider for this project is Postmark over SMTP on port `587`.
- Set `FILESYSTEM_DISK=public` when uploaded media should resolve through `public/storage`.
- Set the AWS variables and use `disk = s3` on media records if production media will live in S3.

## Production Choices

- Database: PostgreSQL 16 on the application server is the default choice for the first deploy. It is the better growth path than MariaDB here, and the app has no MySQL-specific dependency that would justify choosing MariaDB first.
- SMTP: Postmark over SMTP is the mail provider choice for check availability and other transactional mail. On Hetzner Cloud, ports `25` and `465` are blocked by default, while port `587` stays open, so Postmark on `587` avoids the local mail-server path.
- Web stack: keep PHP-FPM behind your web server setup and treat mail as an external service, not something the CX11 should deliver itself.

## Production Env Notes

- Set `DB_CONNECTION=pgsql` with the PostgreSQL host, port, database name, username, and password for production.
- Set `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.postmarkapp.com`, `MAIL_PORT=587`, and use the Postmark server token for both `MAIL_USERNAME` and `MAIL_PASSWORD`.
- Set `INQUIRY_NOTIFICATION_TO` so the inquiry form sends a studio notification email after each successful submission.
- Keep the current app behavior where the inquiry is saved first and mail failures do not drop the lead.

## Media And Storage

- Run `php artisan storage:link` on any environment that serves media from the `public` disk.
- Keep `storage/` persistent across deploys if uploaded assets are stored locally.
- Confirm that uploaded media records have the correct `disk` value. The frontend resolves URLs from that field first.
- Verify that `APP_URL` matches the public domain before generating sitemap and canonical URLs.

## Application

- Create at least one admin user with `php artisan admin:user you@example.com`.
- Run `php artisan migrate --force` during deploys.
- Cache config, routes, and views after the environment is in place.
- Build frontend assets with `npm run build`.
- Run `php artisan launch:check` after the environment and assets are in place.
- Confirm the inquiry form writes successfully and redirects to `/thank-you`.

## Public Route Checks

- Home renders featured stories and journal entries, or backfills cleanly if curated IDs are stale.
- Weddings, journal, collections, and venues render intentional empty states instead of raw placeholders.
- Venue detail pages collapse empty related-content sections instead of exposing dead ends.
- `sitemap.xml` returns current public routes with `lastmod` values.

## Final Content Pass

- Replace any temporary collection, venue, or inquiry copy with final brand-approved language.
- Review SEO titles, descriptions, and canonicals on evergreen pages and featured stories.
- Confirm hero media and alt text exist for the routes intended to lead the site.

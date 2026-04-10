# Laravel Forge Deployment

This project is set up to use:

- GitHub Actions for CI
- a Forge deploy hook for production CD
- Forge zero-downtime deployments as the preferred deployment strategy

## Why This Shape

Laravel Forge supports both direct CI-driven deploys through the Forge CLI and simpler deploy hooks. For the first pass, this repository uses a deploy hook because it keeps CI/CD easy to wire up while still allowing GitHub Actions to stay in control of when production deploys happen.

Forge documents:

- zero-downtime deployments for new sites by default
- shared paths for persistent `storage/` and SQLite files when needed
- deploy hooks for CI-triggered deployments
- reserved query parameters for branch, commit, author, and message
- deployment health checks

Source references:

- [Forge deployments](https://forge.laravel.com/docs/sites/deployments)

## Repository Workflows

- [`.github/workflows/ci.yml`](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/.github/workflows/ci.yml)
  Runs on pull requests and pushes to `main`. It installs dependencies, migrates SQLite, runs the test suite, and builds Vite assets.

- [`.github/workflows/deploy-forge.yml`](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/.github/workflows/deploy-forge.yml)
  Triggers a production deploy in Forge after the `CI` workflow succeeds for a push to `main`. It can also be run manually from GitHub Actions on `main`.

## Forge Site Setup

1. Create the production site in Forge from the GitHub repository `donaldjsexton/donald-sexton-photography`.
2. Keep zero-downtime deployments enabled. Forge says new sites use zero-downtime deployments by default.
3. Set the deployment branch to `main`.
4. Disable Forge `Push to deploy`.
   Reason: GitHub Actions should be the gatekeeper so deploys only happen after CI passes.
5. Configure the production `.env` values in Forge.
6. Enable Node on the app server if it is not already available.
   Reason: this project builds Vite assets during deploys.
7. Add shared paths in Forge:
   - `storage`
   - `database/database.sqlite` only if production uses SQLite
8. Enable deployment health checks in Forge and set the health check URL to `/up`.
   This repo already exposes Laravel’s health route at `/up` in [`bootstrap/app.php`](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/bootstrap/app.php).

## GitHub Setup

Create a GitHub `production` environment and store this secret there:

- `FORGE_DEPLOY_HOOK_URL`

Use the full deploy hook URL copied from:

- Forge site
- `Settings`
- `Deployments`
- `Deploy hook`

Forge’s docs state that deploy hooks can be triggered with `GET` or `POST` and accept query parameters like:

- `forge_deploy_branch`
- `forge_deploy_commit`
- `forge_deploy_author`
- `forge_deploy_message`

The deploy workflow already sends these values so the Forge deploy history is easier to read.

## Recommended Forge Deploy Script

Use a zero-downtime deploy script in Forge that keeps Laravel caches current and builds frontend assets on the server:

```bash
$CREATE_RELEASE()
cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

npm ci
npm run build

php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

$ACTIVATE_RELEASE()
$RESTART_QUEUES()
```

Notes:

- Forge documents that `$CREATE_RELEASE()`, `$ACTIVATE_RELEASE()`, and `$RESTART_QUEUES()` are the zero-downtime macros that should remain in the deploy script.
- If this site ends up running Horizon or dedicated queue workers in Forge, the queue restart macro is the right place to pick up new code.
- `php artisan storage:link` is included because this app serves uploaded media from the `public` disk by default.

## Production Env Baseline

Use the production direction already documented in [launch-readiness.md](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/docs/deployment/launch-readiness.md):

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain`
- `DB_CONNECTION=pgsql`
- Postmark SMTP on port `587`
- `FILESYSTEM_DISK=public` unless media is moved to S3
- `INQUIRY_NOTIFICATION_TO` set to the real studio inbox

## Health And Post-Deploy Checks

Forge health checks should use:

- `/up`

Application-level smoke checks can still be run manually on the server with:

```bash
php artisan launch:check
```

Do not make `launch:check` part of every automated deploy until production content requirements are in place. The command currently checks for published evergreen pages like `about` and `collections`, which is useful for launch readiness but can be stricter than a pure infrastructure deploy.

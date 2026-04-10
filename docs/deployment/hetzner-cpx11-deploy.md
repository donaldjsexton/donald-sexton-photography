# Hetzner CPX11 Deployment

This repository uses:

- GitHub Actions for CI
- GitHub Actions over SSH for production deploys
- a server-side deploy script tuned for a small Hetzner `CPX11`

The target box is already provisioned. This document only covers the CI/CD side and the expected server-side deploy behavior.

## Repository Workflows

- [`.github/workflows/ci.yml`](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/.github/workflows/ci.yml)
  Runs tests and a production asset build on pull requests and pushes to `main`.

- [`.github/workflows/deploy-ssh.yml`](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/.github/workflows/deploy-ssh.yml)
  Triggers a production deploy after the `CI` workflow succeeds for a push to `main`. It can also be triggered manually from GitHub Actions on `main`.

## GitHub Production Environment

Create a GitHub environment named `production`.

Add these environment secrets:

- `PRODUCTION_SSH_PRIVATE_KEY`
  The private key GitHub Actions should use to SSH into the server.

- `PRODUCTION_SSH_KNOWN_HOSTS`
  Optional but recommended. The host key line from your local `known_hosts` for the production server.

Add these environment variables:

- `PRODUCTION_SSH_HOST`
- `PRODUCTION_SSH_PORT`
- `PRODUCTION_SSH_USER`
- `PRODUCTION_APP_DIR`

Optional environment variables:

- `PRODUCTION_PHP_BIN`
- `PRODUCTION_COMPOSER_BIN`
- `PRODUCTION_NPM_BIN`

Typical values:

- `PRODUCTION_SSH_PORT=22`
- `PRODUCTION_SSH_USER=deploy` or whatever user owns the app
- `PRODUCTION_APP_DIR=/var/www/donald-sexton-photography`
- `PRODUCTION_PHP_BIN=php`
- `PRODUCTION_COMPOSER_BIN=composer`
- `PRODUCTION_NPM_BIN=npm`

## Server Deploy Script

The remote deploy script lives at [scripts/deploy-cpx11.sh](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/scripts/deploy-cpx11.sh).

It is written specifically for a smaller `CPX11` box:

- single in-place deploy directory
- `flock` lock to prevent overlapping deploys
- fast-forward only Git updates
- production Composer install without dev dependencies
- `NODE_OPTIONS=--max-old-space-size=512` to reduce Vite build memory pressure
- Laravel cache rebuild and migrations

The workflow uploads the current version of that script to `/tmp` on the server and executes it there, so the deploy logic always matches the commit that triggered the deploy.

## Expected App Directory State

The app directory set in `PRODUCTION_APP_DIR` should already:

- be a git clone of this repository
- be able to `git fetch origin` as the deploy user
- have the production `.env` present on the server
- have write access for `storage/` and `bootstrap/cache/`
- have working `php`, `composer`, `node`, and `npm` on the server user’s path

## Deploy Flow

1. Push lands on `main`.
2. GitHub Actions runs [`.github/workflows/ci.yml`](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/.github/workflows/ci.yml).
3. If CI passes, GitHub Actions runs [`.github/workflows/deploy-ssh.yml`](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/.github/workflows/deploy-ssh.yml).
4. The deploy workflow uploads [scripts/deploy-cpx11.sh](/Users/ninjaexilemkiii/Documents/Dev/donald-sexton-photography/scripts/deploy-cpx11.sh) and runs it on the server against the tested commit SHA.

## Post-Deploy Checks

After a successful deploy, validate:

- `/up`
- the public homepage
- `/admin/login`
- `php artisan launch:check`

The `launch:check` command is intentionally not hardwired into the deploy workflow because it currently enforces some content-state assumptions in addition to infrastructure checks.

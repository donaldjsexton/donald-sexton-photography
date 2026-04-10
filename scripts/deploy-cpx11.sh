#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/donald-sexton-photography}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
DEPLOY_COMMIT="${DEPLOY_COMMIT:-}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
LOCK_FILE="${LOCK_FILE:-/tmp/donald-sexton-photography-deploy.lock}"
NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=512}"

log() {
    printf '[deploy] %s\n' "$1"
}

fail() {
    printf '[deploy] ERROR: %s\n' "$1" >&2
    exit 1
}

[ -d "$APP_DIR" ] || fail "App directory does not exist: $APP_DIR"

exec 9>"$LOCK_FILE"
flock -n 9 || fail "Another deploy is already running."

cd "$APP_DIR"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "App directory is not a git repository."

if ! git diff --quiet || ! git diff --cached --quiet; then
    fail "Deploy aborted because tracked files have local changes."
fi

if [[ ! -f .env ]]; then
    fail "Missing .env in $APP_DIR."
fi

cleanup() {
    if [[ -f artisan ]]; then
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
    fi
}

trap cleanup EXIT

log "Fetching origin/$DEPLOY_BRANCH"
git fetch --prune origin "$DEPLOY_BRANCH"

current_branch="$(git branch --show-current)"

if [[ "$current_branch" != "$DEPLOY_BRANCH" ]]; then
    if git show-ref --verify --quiet "refs/heads/$DEPLOY_BRANCH"; then
        git switch "$DEPLOY_BRANCH"
    else
        git switch --track -c "$DEPLOY_BRANCH" "origin/$DEPLOY_BRANCH"
    fi
fi

if [[ -n "$DEPLOY_COMMIT" ]]; then
    git cat-file -e "$DEPLOY_COMMIT^{commit}" >/dev/null 2>&1 || fail "Commit not available locally: $DEPLOY_COMMIT"
    log "Fast-forwarding to tested commit $DEPLOY_COMMIT"
    git merge --ff-only "$DEPLOY_COMMIT"
else
    log "Fast-forwarding branch $DEPLOY_BRANCH"
    git pull --ff-only origin "$DEPLOY_BRANCH"
fi

log "Putting the app in maintenance mode"
"$PHP_BIN" artisan down --retry=60 --refresh=15 || true

log "Installing PHP dependencies"
"$COMPOSER_BIN" install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

log "Installing Node dependencies"
"$NPM_BIN" ci --no-audit --no-fund

log "Building frontend assets with reduced Node memory usage"
NODE_OPTIONS="$NODE_OPTIONS" "$NPM_BIN" run build

log "Running database migrations"
"$PHP_BIN" artisan migrate --force

if [[ ! -L public/storage ]]; then
    log "Creating public storage symlink"
    "$PHP_BIN" artisan storage:link || true
fi

log "Refreshing Laravel caches"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

log "Bringing the app back up"
"$PHP_BIN" artisan up

log "Deploy complete"

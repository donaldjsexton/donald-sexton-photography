#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/donald-sexton-photography}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
DEPLOY_COMMIT="${DEPLOY_COMMIT:-}"
SOURCE_ARCHIVE="${SOURCE_ARCHIVE:-}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
LOCK_FILE="${LOCK_FILE:-/tmp/donald-sexton-photography-deploy.lock}"
NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=512}"
RELEASES_TO_KEEP="${RELEASES_TO_KEEP:-5}"

DEPLOY_MODE=""
DEPLOY_ROOT=""
RELEASE_DIR=""
DEPLOY_SUCCESS=0

log() {
    printf '[deploy] %s\n' "$1"
}

fail() {
    printf '[deploy] ERROR: %s\n' "$1" >&2
    exit 1
}

detect_deploy_mode() {
    if [[ -d "$APP_DIR/releases" && -d "$APP_DIR/shared" ]]; then
        DEPLOY_MODE="releases"
        DEPLOY_ROOT="$APP_DIR"
        return
    fi

    if [[ "$(basename "$APP_DIR")" == "current" && -d "$(dirname "$APP_DIR")/releases" && -d "$(dirname "$APP_DIR")/shared" ]]; then
        DEPLOY_MODE="releases"
        DEPLOY_ROOT="$(cd "$(dirname "$APP_DIR")" && pwd)"
        return
    fi

    if [[ -d "$APP_DIR" ]]; then
        DEPLOY_MODE="in-place"
        DEPLOY_ROOT="$APP_DIR"
        return
    fi

    fail "App directory does not exist: $APP_DIR"
}

cleanup() {
    if [[ -n "$SOURCE_ARCHIVE" && -f "$SOURCE_ARCHIVE" ]]; then
        rm -f "$SOURCE_ARCHIVE"
    fi

    if [[ "${DEPLOY_MODE:-}" == "releases" && "$DEPLOY_SUCCESS" -ne 1 && -n "$RELEASE_DIR" && -d "$RELEASE_DIR" ]]; then
        rm -rf "$RELEASE_DIR"
    fi

    if [[ "${DEPLOY_MODE:-}" == "releases" ]]; then
        if [[ -L "$DEPLOY_ROOT/current" && -f "$DEPLOY_ROOT/current/artisan" ]]; then
            "$PHP_BIN" "$DEPLOY_ROOT/current/artisan" up >/dev/null 2>&1 || true
        fi
        return
    fi

    if [[ -f artisan ]]; then
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
    fi
}

prepare_release_paths() {
    mkdir -p "$DEPLOY_ROOT/releases" "$DEPLOY_ROOT/shared"
    mkdir -p \
        "$DEPLOY_ROOT/shared/storage/app/public" \
        "$DEPLOY_ROOT/shared/storage/framework/cache/data" \
        "$DEPLOY_ROOT/shared/storage/framework/sessions" \
        "$DEPLOY_ROOT/shared/storage/framework/testing" \
        "$DEPLOY_ROOT/shared/storage/framework/views" \
        "$DEPLOY_ROOT/shared/storage/logs"
}

link_shared_paths() {
    [[ -f "$DEPLOY_ROOT/shared/.env" ]] || fail "Missing shared env file: $DEPLOY_ROOT/shared/.env"

    ln -sfn "$DEPLOY_ROOT/shared/.env" "$RELEASE_DIR/.env"

    rm -rf "$RELEASE_DIR/storage"
    ln -sfn "$DEPLOY_ROOT/shared/storage" "$RELEASE_DIR/storage"

    mkdir -p "$RELEASE_DIR/bootstrap/cache"
}

build_release() {
    local target_dir="$1"

    cd "$target_dir"

    log "Installing PHP dependencies"
    "$COMPOSER_BIN" install \
        --no-interaction \
        --no-dev \
        --prefer-dist \
        --optimize-autoloader

    if [[ -f public/build/manifest.json ]]; then
        log "Using prebuilt frontend assets from the release archive"
    else
        log "Installing Node dependencies"
        "$NPM_BIN" ci --no-audit --no-fund

        log "Building frontend assets with reduced Node memory usage"
        NODE_OPTIONS="$NODE_OPTIONS" "$NPM_BIN" run build
    fi

    log "Running database migrations"
    "$PHP_BIN" artisan migrate --force

    log "Creating public storage symlink"
    "$PHP_BIN" artisan storage:link || true

    log "Refreshing Laravel caches"
    "$PHP_BIN" artisan optimize:clear
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan view:cache
}

prune_old_releases() {
    mapfile -t old_releases < <(ls -1dt "$DEPLOY_ROOT"/releases/* 2>/dev/null | tail -n "+$((RELEASES_TO_KEEP + 1))")

    for release_path in "${old_releases[@]}"; do
        [[ "$release_path" == "$RELEASE_DIR" ]] && continue
        rm -rf "$release_path"
    done
}

exec 9>"$LOCK_FILE"
flock -n 9 || fail "Another deploy is already running."

detect_deploy_mode
trap cleanup EXIT

if [[ "$DEPLOY_MODE" == "releases" ]]; then
    [[ -f "$SOURCE_ARCHIVE" ]] || fail "Missing source archive: $SOURCE_ARCHIVE"

    prepare_release_paths

    release_name="$(date +%Y%m%d%H%M%S)"
    if [[ -n "$DEPLOY_COMMIT" ]]; then
        release_name="$release_name-${DEPLOY_COMMIT:0:7}"
    fi

    RELEASE_DIR="$DEPLOY_ROOT/releases/$release_name"
    mkdir -p "$RELEASE_DIR"

    log "Extracting release archive into $RELEASE_DIR"
    tar -xzf "$SOURCE_ARCHIVE" -C "$RELEASE_DIR"
    rm -f "$SOURCE_ARCHIVE"
    SOURCE_ARCHIVE=""

    link_shared_paths

    if [[ -L "$DEPLOY_ROOT/current" && -f "$DEPLOY_ROOT/current/artisan" ]]; then
        log "Putting the current release in maintenance mode"
        "$PHP_BIN" "$DEPLOY_ROOT/current/artisan" down --retry=60 --refresh=15 || true
    fi

    build_release "$RELEASE_DIR"

    log "Activating release"
    ln -sfn "$RELEASE_DIR" "$DEPLOY_ROOT/current"

    log "Bringing the app back up"
    "$PHP_BIN" "$RELEASE_DIR/artisan" up

    prune_old_releases
    DEPLOY_SUCCESS=1
    log "Deploy complete"
    exit 0
fi

cd "$DEPLOY_ROOT"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "App directory is not a git repository."

if ! git diff --quiet || ! git diff --cached --quiet; then
    fail "Deploy aborted because tracked files have local changes."
fi

if [[ ! -f .env ]]; then
    fail "Missing .env in $DEPLOY_ROOT."
fi

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

build_release "$DEPLOY_ROOT"

log "Bringing the app back up"
"$PHP_BIN" artisan up

DEPLOY_SUCCESS=1

log "Deploy complete"

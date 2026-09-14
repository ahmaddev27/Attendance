# shellcheck shell=bash
#
# Production deploy. Runs on the VPS through run-on-vps.sh after CI passes on
# main (.github/workflows/deploy.yml), prefixed by lib/vps-common.sh.
#
# Order matters:
#   1. sync code          4. build images            7. caches
#   2. composer.lock      5. migrate (new image,     8. health gate
#   3. remember running      old release still live)  9. prune
#      images for rollback 6. switch containers
#
# Migrations run before traffic moves, so a failing migration leaves the
# running release untouched. Anything that fails after the switch (caches,
# a container not running, /api/health not answering 200) rolls the app
# containers back to the images recorded in step 3. Migrations are additive
# by policy, so the previous release keeps working on the newer schema.
#
# This script NEVER overwrites .env, seeds or truncates the database, runs
# migrate:fresh, or deletes untracked files on the VPS.
#
# Inputs: APP_PATH (required), NO_CACHE ("true" to rebuild without cache).

enter_app_dir

# SSH user vs directory owner mismatch → git 'dubious ownership'
git config --global --add safe.directory "$APP_PATH" || true

# `.git/FETCH_HEAD: Permission denied` / `insufficient permission for adding
# an object` happen when an operator ran a git write op as a different user,
# leaving foreign-owned files under .git. Probe the usual failure surfaces and
# realign ownership before git fetch fails with a cryptic error.
realign_git_ownership() {
  local current needs_chown=0 foreign
  current="$(id -un):$(id -gn)"

  { touch .git/.taqat-write-probe 2>/dev/null && rm -f .git/.taqat-write-probe; } || needs_chown=1
  if [ -d .git/objects ]; then
    { touch .git/objects/.taqat-write-probe 2>/dev/null && rm -f .git/objects/.taqat-write-probe; } || needs_chown=1
  fi
  if [ "$needs_chown" -eq 0 ]; then
    foreign="$(find .git -not -user "$(id -un)" -print -quit 2>/dev/null || true)"
    [ -n "$foreign" ] && needs_chown=1
  fi
  [ "$needs_chown" -eq 0 ] && return 0

  warn ".git tree has foreign-owned files (running as $current) — realigning"
  if ! sudo chown -R "$current" .git 2>/dev/null && ! chown -R "$current" .git 2>/dev/null; then
    fail "chown failed. Fix on the VPS as root: sudo chown -R $current $APP_PATH/.git"
    exit 1
  fi
  if ! { touch .git/.taqat-write-probe 2>/dev/null && rm -f .git/.taqat-write-probe; }; then
    fail "still cannot write to .git after chown — aborting deploy"
    exit 1
  fi
  info ".git ownership realigned to $current"
}

remember_running_images() {
  PREVIOUS_IMAGES_RECORDED=1
  local pair container image image_id
  for pair in taqat_api:taqat-api taqat_web:taqat-web; do
    container="${pair%%:*}"
    image="${pair##*:}"
    if image_id="$(docker inspect --format '{{.Image}}' "$container" 2>/dev/null)" && [ -n "$image_id" ]; then
      docker tag "$image_id" "$image:previous"
      info "$image:previous → ${image_id#sha256:}"
    else
      warn "$container is not running — automatic rollback unavailable for this deploy"
      PREVIOUS_IMAGES_RECORDED=0
    fi
  done
}

# Recreating Redis with a new command would start it empty. Turning on the
# append-only file on the running instance first writes the current dataset
# (sessions, queued jobs) to the data volume, so the recreated container
# loads it back. No-op once persistence is already on.
ensure_redis_persistence() {
  if ! docker inspect taqat_redis > /dev/null 2>&1; then
    info "redis container not present yet — nothing to preserve"
    return 0
  fi

  local password
  password="$(docker inspect --format '{{range .Config.Cmd}}{{println .}}{{end}}' taqat_redis \
    | grep -A1 -x -- '--requirepass' | tail -n 1)"
  export REDISCLI_AUTH="$password"

  if docker exec -e REDISCLI_AUTH taqat_redis redis-cli CONFIG GET appendonly < /dev/null | tail -n 1 | grep -q '^yes'; then
    info "redis append-only file already enabled"
    unset REDISCLI_AUTH
    return 0
  fi

  info "enabling redis append-only file before the container is recreated"
  local reply
  reply="$(docker exec -e REDISCLI_AUTH taqat_redis redis-cli CONFIG SET appendonly yes < /dev/null | tr -d '\r')"
  if [ "$reply" != "OK" ]; then
    unset REDISCLI_AUTH
    return 1
  fi

  local attempt persistence
  for attempt in $(seq 1 120); do
    sleep 1
    persistence="$(docker exec -e REDISCLI_AUTH taqat_redis redis-cli INFO persistence < /dev/null | tr -d '\r')"
    if printf '%s\n' "$persistence" | grep -qx 'aof_enabled:1' \
      && printf '%s\n' "$persistence" | grep -qx 'aof_rewrite_in_progress:0' \
      && printf '%s\n' "$persistence" | grep -qx 'aof_rewrite_scheduled:0' \
      && printf '%s\n' "$persistence" | grep -qx 'aof_last_bgrewrite_status:ok'; then
      info "redis dataset written to the append-only file after ${attempt}s"
      unset REDISCLI_AUTH
      return 0
    fi
  done

  unset REDISCLI_AUTH
  return 1
}

# Points :local back at the running release. Used whenever a deploy stops
# before or after switching, so a later `docker compose up` can never start
# containers from an image that was built but never passed the gate.
restore_previous_image_tags() {
  [ "${PREVIOUS_IMAGES_RECORDED:-0}" = "1" ] || return 1
  docker tag taqat-api:previous taqat-api:local || return 1
  docker tag taqat-web:previous taqat-web:local || return 1
}

rebuild_laravel_caches() {
  dc exec -T api php artisan config:clear < /dev/null || return 1
  dc exec -T api php artisan config:cache < /dev/null || return 1
  dc exec -T api php artisan route:cache < /dev/null || return 1
  dc exec -T api php artisan view:cache < /dev/null || return 1
  dc exec -T api php artisan storage:link < /dev/null > /dev/null 2>&1 || true
}

verify_services_running() {
  local service state failures=0
  for service in db redis minio meilisearch api nginx web queue scheduler reverb; do
    state="$(dc ps --format '{{.State}}' "$service" 2>/dev/null || echo missing)"
    if [ "$state" != "running" ]; then
      fail "$service: ${state:-missing}"
      dc logs --tail=30 "$service" || true
      failures=$((failures + 1))
    else
      info "✓ $service: running"
    fi
  done
  [ "$failures" -eq 0 ]
}

switch_traffic() {
  section "6/9  Switching traffic to the new release"
  # --force-recreate picks up new .env values even when only env vars changed.
  dc up -d --no-deps --force-recreate api web queue scheduler reverb || return 1

  if ! ensure_redis_persistence; then
    warn "could not enable redis persistence before recreate — sessions and queued jobs in memory may be lost once"
  fi

  # Brings up backing services added to the compose file since the last
  # deploy and applies changed settings; unchanged services keep running and
  # every data volume stays attached.
  dc up -d db redis minio meilisearch || return 1

  # nginx resolves the api upstream per request (10s TTL); restarting it
  # picks up the fresh container IP immediately.
  dc restart nginx || return 1
}

# Everything after traffic moved to the new containers. Each step returns
# non-zero instead of exiting so the caller can roll back.
verify_new_release() {
  section "7/9  Rebuilding Laravel caches"
  wait_for_api || return 1
  rebuild_laravel_caches || { fail "cache rebuild failed"; return 1; }

  section "8/9  Health gate"
  verify_services_running || return 1
  wait_for_health || return 1
}

roll_back() {
  section "✗ Rolling back to $BEFORE"
  if [ "${PREVIOUS_IMAGES_RECORDED:-0}" != "1" ]; then
    fail "no previous images were recorded — manual rollback required (see docs/deployment/vps-deploy.md)"
    return 1
  fi

  git reset --hard "$BEFORE" || return 1
  restore_previous_image_tags || return 1
  dc up -d --no-deps --force-recreate api web queue scheduler reverb || return 1
  dc restart nginx || return 1
  wait_for_api || return 1
  rebuild_laravel_caches || warn "cache rebuild failed on the previous release"

  if wait_for_health; then
    info "previous release is serving again"
    return 0
  fi
  fail "previous release is not healthy either — needs manual attention"
  return 1
}

section "1/9  Checking out the verified commit from origin/main"
realign_git_ownership

# HARD SAFETY: .env is never touched by the deploy path. If someone committed
# it, abort loudly rather than deploy over their secrets.
if git ls-files --error-unmatch .env > /dev/null 2>&1; then
  fail "FATAL: .env is tracked in git — deploy refuses to run"
  fail "Remove it from the index: git rm --cached .env && commit"
  exit 1
fi
if [ -f .env ]; then
  cp .env ".env.pre-deploy.$(date +%Y%m%d-%H%M%S)"
  info "✓ .env backed up before pull"
fi

git fetch origin main

# The workflow forwards the commit whose CI passed (DEPLOY_SHA). Resetting to
# the branch tip instead would ship any later commit whose CI is still running
# or has already failed. Without a SHA (local rehearsal) the tip is deployed.
TARGET="origin/main"
if [ -n "${DEPLOY_SHA:-}" ]; then
  if ! printf '%s' "$DEPLOY_SHA" | grep -Eq '^[0-9a-f]{40}$'; then
    fail "DEPLOY_SHA is not a full commit hash — refusing to deploy"
    exit 1
  fi
  if ! git merge-base --is-ancestor "$DEPLOY_SHA" origin/main; then
    fail "commit $DEPLOY_SHA is not on origin/main — refusing to deploy it"
    exit 1
  fi
  # CI runs can finish out of order: never roll the server back to an older
  # commit after a newer one has gone out.
  if [ "$(git rev-parse HEAD)" != "$DEPLOY_SHA" ] && git merge-base --is-ancestor "$DEPLOY_SHA" HEAD; then
    info "a newer commit ($(git rev-parse --short HEAD)) is already deployed — nothing to do"
    exit 0
  fi
  TARGET="$DEPLOY_SHA"
fi

# git reset --hard refuses to clobber untracked files, so rescue any file
# that is now tracked upstream but untracked here by renaming it.
for tracked_upstream in docker-compose.simple.yml infra/docker/nginx/default.conf; do
  if git ls-tree -r "$TARGET" --name-only | grep -Fxq "$tracked_upstream" \
    && [ -e "$tracked_upstream" ] \
    && ! git ls-files --error-unmatch "$tracked_upstream" > /dev/null 2>&1; then
    rescued="${tracked_upstream}.pre-track.$(date +%Y%m%d-%H%M%S)"
    warn "'$tracked_upstream' is tracked upstream but untracked here — backing up to '$rescued'"
    mv "$tracked_upstream" "$rescued"
  fi
done

BEFORE="$(git rev-parse HEAD)"
git reset --hard "$TARGET"
AFTER="$(git rev-parse HEAD)"
info "$BEFORE → $AFTER"

CHANGED_FILES="$(git diff --name-only "$BEFORE" "$AFTER" 2>/dev/null || echo all)"
info "Changed files: $(printf '%s\n' "$CHANGED_FILES" | wc -l) file(s)"

section "2/9  Sync composer.lock if composer.json changed"
if printf '%s\n' "$CHANGED_FILES" | grep -qx 'apps/api/composer.json' \
  && ! printf '%s\n' "$CHANGED_FILES" | grep -qx 'apps/api/composer.lock'; then
  info "composer.json changed but lock did not — regenerating"
  docker run --rm -v "$(pwd)/apps/api:/app" -w /app \
    composer:2 config policy.advisories.block false > /dev/null 2>&1 || true
  docker run --rm -v "$(pwd)/apps/api:/app" -w /app \
    composer:2 update --no-scripts --no-interaction --ignore-platform-reqs --no-audit --lock
else
  info "composer.lock is in sync — skipping"
fi

section "3/9  Recording the running release for rollback"
remember_running_images

section "4/9  Rebuilding api + web images"
# no_cache=true nukes the buildx cache first: slow, but rescues a deploy stuck
# on cached layers that reference files never actually downloaded.
if [ "${NO_CACHE:-false}" = "true" ]; then
  warn "--no-cache requested — pruning buildx cache first"
  docker builder prune -af || true
  build_args=(--pull --no-cache api web)
else
  build_args=(--pull api web)
fi
if ! dc build "${build_args[@]}"; then
  fail "image build failed — the running release is untouched; restoring the previous checkout"
  git reset --hard "$BEFORE" || true
  restore_previous_image_tags || true
  exit 1
fi

section "5/9  Running migrations with the new image (old release still serving)"
# An explicit name keeps the one-off container clear of the service's
# container_name (taqat_api), which the running release still owns.
# shellcheck disable=SC2016  # runs inside the one-off container
if ! dc run --rm --no-deps -T --name "taqat_migrate_$(date +%s)_$$" api sh -c 'php artisan migrate:status | tail -n 40; php artisan migrate --force' < /dev/null; then
  fail "migrate --force failed — traffic was never switched; restoring the previous checkout"
  git reset --hard "$BEFORE" || true
  restore_previous_image_tags || true
  exit 1
fi

if ! switch_traffic || ! verify_new_release; then
  if ! roll_back; then
    fail "rollback did not complete — production needs manual attention"
  fi
  exit 1
fi

section "9/9  Pruning old images"
# Only dangling images are pruned; the :previous tags survive for rollback.
docker image prune -f --filter "until=60m" || true
docker builder prune -f --keep-storage 3g || true

dc ps

section "✓ Deploy complete: $AFTER"

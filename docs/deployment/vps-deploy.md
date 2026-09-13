# TAQAT VPS Deployment Guide

Everything you need to know about deploying TAQAT to the shared cPanel VPS
running Docker behind Apache.

## Architecture

```
Public Internet (443/80)
      ↓
Apache 2.4 (cPanel — SSL termination, reverse proxy)
      ↓
Docker containers (bound to 127.0.0.1 only)
  ├── nginx:8180 → php-fpm (Laravel API)
  ├── web:8181 → Next.js
  └── reverb:8182 → Reverb WebSocket
  └── (internal-only) db, redis, minio
```

## Environment files — what lives where

| File | Location | Purpose | Git-tracked? |
|------|----------|---------|--------------|
| `.env` | `/home/deploy/apps/taqat/.env` | Runtime secrets + config | ❌ (git-ignored) |
| `.env.example` | Repo root | Template with placeholders | ✅ |
| `apps/api/.env` | Optional Laravel-specific | Legacy — unused now | ❌ (git-ignored) |
| `docker-compose.simple.yml` | Repo root | Compose config | ✅ (as of Sept 2026) |

**The deploy workflow never touches `.env`** — it verifies `.env` is
git-ignored on every run and aborts if someone committed it accidentally.
Every deploy backs up `.env` to `.env.pre-deploy.YYYYMMDD-HHMMSS` before
git reset, so a bad commit can be recovered.

## Required environment variables

Only variables actually used at runtime are listed. See `.env.example` for
the full template.

### Application

- `APP_KEY` — generated once via `openssl rand -base64 32`, base64-prefix it
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://attendees.taqatgaza.com`

### Database (MySQL 8, container-internal)

- `DB_HOST=db`, `DB_PORT=3306`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`

### Redis (predis client, container-internal)

- `REDIS_HOST=redis`, `REDIS_PORT=6379`, `REDIS_PASSWORD`
- `REDIS_CLIENT=predis` (no phpredis extension in the image)

### Reverb (WebSocket — dual internal/external config)

Two hosts, two schemes, two ports — one for Laravel talking to Reverb over
the Docker network, one for the browser talking to Reverb via Apache:

```bash
# ---- INTERNAL (Laravel → Reverb via Docker network) ----
REVERB_HOST=reverb              # container name
REVERB_PORT=8182                # host binding (docker-compose maps 8182:8080)
REVERB_SCHEME=http              # internal — no TLS between containers
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080         # port INSIDE the container
REVERB_APP_ID=taqat
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>

# ---- EXTERNAL (browser → Reverb via Apache) ----
# These are baked into the Next.js bundle at build time.
NEXT_PUBLIC_REVERB_APP_KEY=<matches REVERB_APP_KEY>
NEXT_PUBLIC_REVERB_HOST=attendees.taqatgaza.com
NEXT_PUBLIC_REVERB_PORT=443     # Apache handles TLS termination
NEXT_PUBLIC_REVERB_SCHEME=https # → wss:// in the browser
```

### Broadcasting

- `BROADCAST_CONNECTION=reverb` (default `null` disables realtime)

### S3-compatible file storage (MinIO, container-internal)

- `FILESYSTEM_DISK=s3`
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET=taqat-media`
- `AWS_ENDPOINT=http://minio:9000`, `AWS_USE_PATH_STYLE_ENDPOINT=true`

## Apache reverse-proxy config

Two files with identical content:

```
/etc/apache2/conf.d/userdata/std/2_4/taqatgaza/attendees.taqatgaza.com/proxy.conf   ← HTTP (:80)
/etc/apache2/conf.d/userdata/ssl/2_4/taqatgaza/attendees.taqatgaza.com/proxy.conf   ← HTTPS (:443)
```

Both **must** exist. AutoSSL creates the SSL VirtualHost but doesn't copy
the HTTP proxy config — copy it manually after enabling AutoSSL:

```bash
mkdir -p /etc/apache2/conf.d/userdata/ssl/2_4/taqatgaza/attendees.taqatgaza.com/
cp /etc/apache2/conf.d/userdata/std/2_4/taqatgaza/attendees.taqatgaza.com/proxy.conf \
   /etc/apache2/conf.d/userdata/ssl/2_4/taqatgaza/attendees.taqatgaza.com/proxy.conf
/scripts/rebuildhttpdconf
systemctl restart httpd
```

The canonical `proxy.conf` content lives in `infra/apache/proxy.conf`.

## Manual first-time setup

Once per VPS. After this, all subsequent deploys are automatic via GitHub
Actions.

1. **Clone the repo** as the `deploy` user:
   ```bash
   sudo -u deploy git clone https://github.com/ahmaddev27/Attendance.git /home/deploy/apps/taqat
   ```

2. **Create `.env`** from `.env.example` and fill in real secrets:
   ```bash
   cd /home/deploy/apps/taqat
   cp .env.example .env
   nano .env
   ```

3. **Bring the stack up**:
   ```bash
   docker compose -f docker-compose.simple.yml build
   docker compose -f docker-compose.simple.yml up -d
   docker compose -f docker-compose.simple.yml exec -T api php artisan migrate --force
   docker compose -f docker-compose.simple.yml exec -T api php artisan db:seed --class=AdminUserSeeder --force
   ```

4. **Set up Apache proxy** (see previous section).

5. **Enable AutoSSL** in WHM (Let's Encrypt) and copy proxy.conf to `ssl/`.

6. **Configure GitHub Actions secrets** in the repo settings:
   - `VPS_HOST` — server IP or hostname
   - `VPS_USER` — `deploy` (must own the repo dir and be in the docker group)
   - `VPS_SSH_KEY` — the deploy user's private SSH key
   - `VPS_SSH_PORT` — usually `22`
   - `VPS_APP_PATH` — `/home/deploy/apps/taqat`

## Auto-deploy flow (per push to main)

1. **CI runs** (api-tests, web-build, infra-scripts). If it fails, deploy never fires.
2. **Deploy job triggers** via `workflow_run`, prepares the SSH key (`.github/actions/vps-ssh`) and runs `infra/scripts/vps-deploy.sh` on the VPS through `infra/scripts/run-on-vps.sh`:
   1. Verifies `.env` is git-ignored, backs it up to `.env.pre-deploy.TIMESTAMP`, then `git fetch && git reset --hard origin/main`.
   2. Regenerates `composer.lock` via the `composer:2` image if `composer.json` changed without it.
   3. Tags the images of the running `taqat_api` / `taqat_web` containers as `taqat-api:previous` / `taqat-web:previous`.
   4. `docker compose build --pull api web`. A build failure restores the previous checkout; nothing else changes.
   5. **Runs `php artisan migrate --force` in a one-off container from the new image while the old release keeps serving.** A migration failure stops here, restores the previous checkout, and leaves the running containers untouched.
   6. Recreates `api web queue scheduler reverb`, applies backing-service changes (`db redis minio meilisearch`), restarts nginx. Before Redis is recreated with a new command, its append-only file is switched on live so sessions and queued jobs survive.
   7. Rebuilds Laravel caches.
   8. **Health gate:** every service must be `running` and `GET /api/health` (through nginx) must answer 200.
   9. Prunes dangling images and trims the build cache.
3. **Anything that fails in steps 6–8 rolls back automatically**: the checkout returns to the previous commit, the `:previous` images are re-tagged as `:local`, the app containers are recreated and the health gate runs again. The workflow run is still marked failed, so the failure is visible.

The script is linted in CI (`bash -n` + shellcheck) because it runs on production with no second chance.

## What the deploy will NEVER do

- Touch `.env` or any file listed in `.gitignore`
- Run `migrate:fresh` or any destructive migration
- Seed the database (except AdminUserSeeder, which is only run manually on first setup)
- Delete or recreate the database container
- Delete Docker volumes (data preserved across every deploy)
- Push new commits back to the repo
- Modify Apache config (that's a one-time manual step)

## Rolling back

Automatic rollback (above) covers failures during a deploy. To go back to an older release after a successful deploy, revert the offending commit on `main` and push: CI and the normal deploy do the rest, with the same health gate.

Database rollbacks are intentionally not automated. Migrations are additive, so older code keeps working on a newer schema; if data itself must be restored, use the `Restore database` workflow described in [backup-restore.md](backup-restore.md).

## Common failure modes and fixes

### `.env` was accidentally committed
```
✗ FATAL: .env is tracked in git — deploy refuses to run
```
Fix: `git rm --cached .env`, commit and push.

### `composer.lock` is out of date
CI auto-recovers via `composer install || composer update`. Locally,
regenerate manually:
```bash
docker run --rm -v "$(pwd)/apps/api:/app" -w /app composer:2 \
  update --no-scripts --ignore-platform-reqs --no-audit --lock
git add apps/api/composer.lock && git commit -m "chore: sync composer.lock"
```

### `reverb` container in restart loop
Usually a `REDIS_PASSWORD` mismatch or Reverb config error. Check logs:
```bash
docker compose logs --tail=50 reverb
```

### Notifications land in the bell but no realtime toast
Broadcast pipeline is broken. Check in order:
1. `BROADCAST_CONNECTION=reverb` in `.env` (not `null`)
2. `REVERB_HOST=reverb` (Docker internal name)
3. `REVERB_SERVER_PORT=8080` (matches `broadcasting.php` default)
4. Frontend: `POST /api/broadcasting/auth` returns 200 (not 404)
5. Apache proxy config includes `/app` (singular) rewrite for WebSocket upgrade

## Related docs

- `docs/v2/01-architecture.md` — system architecture
- `.env.example` — full environment template
- `.github/workflows/deploy.yml` — the deploy workflow
- `docker-compose.simple.yml` — production stack

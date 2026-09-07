# Infrastructure

Dockerfiles + configuration for the TAQAT stack. See `docs/v2/01-architecture.md`
for the full service architecture.

## Layout

```
infra/docker/
├── api/Dockerfile        # Laravel 11, PHP 8.3-FPM Alpine (multi-stage: composer -> runtime)
├── web/Dockerfile        # Next.js 15, Node 20 Alpine (multi-stage: deps -> builder -> runner)
├── nginx/
│   ├── default.conf      # dev: nginx <-> api only (docker-compose.yml)
│   └── prod.conf         # prod: single public entrypoint for api + web + reverb (docker-compose.prod.yml)
└── mysql/init.sql        # creates the `taqat` DB + `taqat` user on first boot
```

## Development

```bash
cp apps/api/.env.example apps/api/.env      # once apps/api exists
cp apps/web/.env.example apps/web/.env.local

docker compose up -d
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed
```

- API: http://localhost:8000 (nginx -> api php-fpm)
- Web: http://localhost:3000 (Next.js, direct — not proxied by nginx in dev)
- MinIO console: http://localhost:9001
- Reverb: ws://localhost:8080

## Production (VPS)

```bash
cp .env.example .env    # fill in real secrets
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec -T api php artisan migrate --force
```

Production intentionally never bind-mounts source code. `api_app` is a named
Docker volume shared read-write by `api` and read-only by `nginx`; the api
container's entrypoint reseeds it from its own image on every start (see
`infra/docker/api/Dockerfile`), which is how nginx gets filesystem access to
`public/index.php` for the PHP-FPM passthrough without ever touching the git
checkout. If you change that mechanism, keep both sides in sync.

Only `nginx` (ports 80/443) is exposed publicly; every other service binds to
`127.0.0.1` for admin/debug access via an SSH tunnel.

### Enabling SSL (first deploy)

`infra/docker/nginx/prod.conf` ships HTTP-only so the stack comes up cleanly
before any certificate exists. Once DNS points at the VPS:

```bash
# 1. Bring the stack up on plain HTTP first (needed for the ACME challenge).
docker compose -f docker-compose.prod.yml up -d

# 2. Obtain a certificate via the webroot the running nginx already serves at
#    /.well-known/acme-challenge/ (see prod.conf).
docker run --rm \
  -v letsencrypt_certs:/etc/letsencrypt \
  -v certbot_webroot:/var/www/certbot \
  certbot/certbot certonly --webroot -w /var/www/certbot \
  -d taqat.your-domain.com --email you@example.com --agree-tos --no-eff-email

# 3. Uncomment the :443 server block in infra/docker/nginx/prod.conf, replace
#    the :80 block's `location /` with `return 301 https://$host$request_uri;`,
#    then reload:
docker compose -f docker-compose.prod.yml exec nginx nginx -s reload
```

Renewal: run the same `certbot renew` invocation (mount the same two volumes)
on a cron/systemd timer, then `docker compose exec nginx nginx -s reload`.

## What still needs attention before going live

- **`apps/web/next.config.ts` must set `output: 'standalone'`** — the web
  Dockerfile's runner stage copies `.next/standalone`, which only exists with
  that option. Not yet present as of this writing.
- **`NEXT_PUBLIC_*` build args**: these are baked into the Next.js bundle at
  image build time (see `infra/docker/web/Dockerfile`), not at container
  runtime. Set them as GitHub Actions repo **Variables** (not secrets — they
  ship in the public JS bundle) so `.github/workflows/deploy.yml` bakes the
  right values in.
- **Secret rotation**: `DB_PASSWORD`, `REDIS_PASSWORD`, `MINIO_ROOT_PASSWORD`,
  `REVERB_APP_SECRET` all live in the VPS's `.env` (chmod 600, not in git).
  Rotating any of them requires updating `.env` and recreating the affected
  containers (`docker compose -f docker-compose.prod.yml up -d`) — Redis/MySQL
  data volumes are unaffected.
- **`APP_KEY`**: generate once with
  `docker compose -f docker-compose.prod.yml run --rm api php artisan key:generate --show`
  and store it in `.env` — rotating it invalidates all encrypted data/sessions.
- **Backups**: `db_data`, `redis_data` and `minio_data` are named volumes with
  no backup job configured yet. Add a scheduled `mysqldump`/`mc mirror` job
  before relying on this in production.
- **CI test database assumptions**: `.github/workflows/ci.yml` spins up its
  own MySQL/Redis service containers with standard Laravel env var names
  (`DB_HOST`, `DB_DATABASE`, etc.). If `apps/api` ships its own
  `.env.testing`/`phpunit.xml` with different values, reconcile them.
- **API health checks**: the `api` service's Docker healthcheck only checks
  that php-fpm accepts TCP connections on 9000 (`nc -z`), not that the app
  actually boots. Once `apps/api` exposes a real `/api/health` endpoint (see
  `docs/v2/01-architecture.md#13`), consider a deeper check via `cgi-fcgi`.
- **Zero-downtime deploys**: `docker compose up -d` briefly drops requests to
  a service while its container is replaced (a few seconds). Fine for an
  internal HR tool; revisit (blue/green, Traefik) if that stops being true.

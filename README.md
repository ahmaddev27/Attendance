# TAQAT Digital Workplace

Employee management, attendance, tasks, projects, sprints, requests, and AI-powered insights for TAQAT.

## Structure

```
taqat/
├── apps/
│   ├── api/          Laravel 11 REST API (Sanctum + Spatie)
│   └── web/          Next.js 15 SPA (React + TypeScript + Tailwind + shadcn/ui)
├── infra/
│   └── docker/       Dockerfiles for api, web, nginx
├── docs/
│   └── v2/           Architecture, ERD, phase plans
├── docker-compose.yml       Local dev stack
└── docker-compose.prod.yml  Production stack (VPS)
```

## Quick start (dev)

```bash
docker compose up -d
docker compose exec api php artisan migrate --seed
```

- API: http://localhost:8000
- Web: http://localhost:3000
- Reverb WS: ws://localhost:8080
- MinIO: http://localhost:9001

## Docs

See [`docs/v2/`](docs/v2/) for the full planning package (overview, architecture, ERD, phase-1 plan).

## History

The v1 (single Laravel monolith with Livewire) is preserved in the `v1-final` git tag and in `_v1_artifacts/`.

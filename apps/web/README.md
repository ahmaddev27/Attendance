# TAQAT Web (Next.js 15)

Frontend SPA for the TAQAT Digital Workplace platform.

See `../../docs/v2/01-architecture.md` for architecture details.

## Getting started

```bash
npm install
cp .env.example .env.local
npm run dev
```

Open [http://localhost:3000](http://localhost:3000). The app redirects `/`
to `/login`.

## Build

```bash
npm run build
npm run start
```

`next.config.ts` sets `output: 'standalone'` so the production build can run
in a minimal Docker image without the full `node_modules` tree.

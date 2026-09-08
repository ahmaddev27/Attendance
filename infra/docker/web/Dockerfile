# syntax=docker/dockerfile:1
#
# TAQAT Web — Next.js 15 (Node 20)
# Build context: ./apps/web  (see docker-compose.yml / docker-compose.prod.yml)
#
# IMPORTANT: apps/web/next.config.ts must set `output: 'standalone'` for the
# runner stage below to find .next/standalone/server.js. Without it, `npm run
# build` still succeeds but this image will fail at the final COPY step.

############################################
# Stage 1 — deps: install node_modules
############################################
FROM node:20-alpine AS deps
WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

############################################
# Stage 2 — builder: compile the production build
############################################
FROM node:20-alpine AS builder
WORKDIR /app

COPY --from=deps /app/node_modules ./node_modules
COPY . .

# NEXT_PUBLIC_* values are inlined into the client (and server) bundle at
# build time — setting them as *runtime* environment variables later has no
# effect. Defaults below match local dev; production values are passed as
# --build-arg by .github/workflows/deploy.yml.
ARG NEXT_PUBLIC_API_URL=http://localhost:8000/api
ARG NEXT_PUBLIC_REVERB_HOST=localhost
ARG NEXT_PUBLIC_REVERB_PORT=8080
ARG NEXT_PUBLIC_REVERB_SCHEME=http
ARG NEXT_PUBLIC_REVERB_APP_KEY=taqat-app-key
ENV NEXT_PUBLIC_API_URL=${NEXT_PUBLIC_API_URL} \
    NEXT_PUBLIC_REVERB_HOST=${NEXT_PUBLIC_REVERB_HOST} \
    NEXT_PUBLIC_REVERB_PORT=${NEXT_PUBLIC_REVERB_PORT} \
    NEXT_PUBLIC_REVERB_SCHEME=${NEXT_PUBLIC_REVERB_SCHEME} \
    NEXT_PUBLIC_REVERB_APP_KEY=${NEXT_PUBLIC_REVERB_APP_KEY} \
    NEXT_TELEMETRY_DISABLED=1

RUN npm run build

############################################
# Stage 3 — runner: minimal production image
############################################
FROM node:20-alpine AS runner
WORKDIR /app

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0

RUN addgroup --system --gid 1001 nodejs \
    && adduser --system --uid 1001 nextjs

COPY --from=builder /app/public ./public
# .next/standalone contains a trimmed node_modules + server.js produced by
# `output: 'standalone'`; .next/static and public/ are not included in it and
# must be copied alongside.
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static

USER nextjs
EXPOSE 3000

CMD ["node", "server.js"]

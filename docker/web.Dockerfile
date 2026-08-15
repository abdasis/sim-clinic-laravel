# Web Dockerfile — TanStack Start (Node SSR) via bun
FROM oven/bun:1-alpine AS builder

WORKDIR /app

# Monorepo files for bun workspaces — root bun.lock is the source of truth,
# references apps/* and packages/*; copy ALL workspace package.json so the
# lockfile resolves without "lockfile had changes"
COPY package.json bun.lock ./
COPY apps/web/package.json ./apps/web/
COPY apps/api/package.json ./apps/api/
COPY apps/wa-gateway/package.json ./apps/wa-gateway/

# Install all workspace deps from root lockfile
RUN bun install --frozen-lockfile

# Copy web source
COPY apps/web/ ./apps/web/

WORKDIR /app/apps/web

# API URL for production — full URL since API is on a different subdomain
ENV VITE_API_URL=https://api.mebaclinic.com/api
RUN bun run build

# bun workspaces use SYMLINKS into a global store at /app/node_modules/.bun,
# and the symlinks are RELATIVE (../../../node_modules/.bun/...). To keep them
# resolving in the runtime image we must preserve the same structure: copy the
# whole /app/apps/web/node_modules (symlinks) AND /app/node_modules (the .bun
# store they point to) into the same relative paths, then run from apps/web.
# Production stage
FROM oven/bun:1-alpine

WORKDIR /app

COPY --from=builder /app/apps/web/dist ./apps/web/dist
COPY --from=builder /app/apps/web/node_modules ./apps/web/node_modules
COPY --from=builder /app/node_modules ./node_modules

WORKDIR /app/apps/web

ENV PORT=3000
ENV HOST=0.0.0.0
ENV NODE_ENV=production

EXPOSE 3000

# Copy the client build into /app/public (shared web-public volume, also mounted
# at /var/www/web-assets in nginx) so static /assets/* are served, then boot SSR.
CMD ["sh", "-c", "mkdir -p /app/public && cp -r /app/apps/web/dist/client/. /app/public/ && bun dist/server/server.js"]

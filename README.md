# sim-clinic-laravel

Monorepo sim-clinic dengan backend Laravel API + frontend TanStack Start.

## Struktur

```
sim-clinic-laravel/
├── apps/
│   ├── api/      # Laravel API (pgsql)
│   └── web/      # TanStack Start + shadcn/ui
├── packages/     # shared (opsional)
├── docker-compose.yml
└── package.json  # bun workspaces
```

## Prasyarat

- PHP 8.5+, Composer
- Bun
- Docker (untuk PostgreSQL)

## Setup

### 1. Database PostgreSQL

```bash
docker compose up -d db
```

Berjalan di port `5435` (db: `sim_clinic_laravel`, user/pass: `postgres/postgres`).

### 2. Backend Laravel (apps/api)

```bash
cd apps/api
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve  # port 8000
```

### 3. Frontend TanStack Start (apps/web)

```bash
cd apps/web
bun install
bun run dev  # port 3000
```

### 4. Jalankan semua (dari root)

```bash
bun install
bun run dev
```

## Ports

| Service | Port |
|---------|------|
| PostgreSQL | 5435 |
| Laravel API | 8000 |
| TanStack Start | 3000 |
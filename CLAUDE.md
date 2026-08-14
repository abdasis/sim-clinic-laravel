# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Monorepo for **sim-clinic**, a multi-tenant beauty-clinic SaaS. Bun workspaces.

- `apps/api` — Laravel 13 (PHP 8.3+) API-only, PostgreSQL, Sanctum token auth.
- `apps/web` — TanStack Start (React 19, SSR), shadcn/ui `radix-nova`, Tailwind v4, TanStack Router + Query.
- `packages/` — shared (reserved, currently empty).
- `specs/` — source of truth: `001-multi-tenant-single-db` and `002-beauty-clinic-mvp` each hold `spec.md`, `tasks.md`, `research.md`, `data-model.md`, `api-contracts.md`. Code comments cite these (`spec 001 FR-007`, `R2 matriks`). `docs/` has ERD + per-table normalization + feature-workflow.

## Commands

Run from repo root unless noted.

```bash
# DB (PostgreSQL on port 5435, db/user/pass: sim_clinic_laravel/postgres/postgres)
docker compose up -d db

# Backend (apps/api) — port 8000
cd apps/api && cp .env.example .env && php artisan key:generate && php artisan migrate
php artisan serve                # or from root: bun run dev:api

# Frontend (apps/web) — port 3001 (NOT 3000; root README is stale)
cd apps/web && bun install && bun run dev   # or from root: bun run dev:web

# Both via concurrently
bun install && bun run dev

# Tests
cd apps/api && php artisan test                          # sqlite :memory: per phpunit.xml
cd apps/api && php artisan test --filter=ExampleTest     # single test
cd apps/web && bun run test                              # vitest

# Lint / typecheck
cd apps/api && vendor/bin/pint
cd apps/web && npx tsc --noEmit --incremental
cd apps/web && bun run generate-routes                   # regen TanStack route tree (tsr)
```

Do NOT auto-run `bun run dev` / `bun run build` / `composer run dev` — tell the user to run them.

## Backend architecture (apps/api)

### Multi-tenant single-DB

Every tenant-scoped request carries `{tenant}` (slug) in the URL path. Two middleware, applied per route group in `routes/api.php`:

- `ResolveTenant` — looks up `Tenant` by slug, stores it in the container as `app('tenant')`. Unknown slug → 404.
- `EnsureTenantActive` — 403/423 if tenant status is `Inactive`.

Tenant isolation is automatic on models using the `App\Concerns\BelongsToTenant` trait:

- `App\Scopes\TenantScope` global scope filters `tenant_id` from `app('tenant')`.
- `creating` hook auto-fills `tenant_id` from the container.
- When no tenant is bound (CLI, seeding, central routes), the scope filters nothing.

Tenant-scoped models: `User`, `Service`, `Patient`, `Booking`, `Product`, `StockMovement`, `Transaction`, `TransactionItem`, `Payment`, `Invoice`, `MedicalRecord`, `TreatmentRecord`, `MedicalPhoto`. NOT scoped: `Tenant`, `AuditLog` (nullable `tenant_id` for central events), `Invitation`.

### Auth & permissions

Sanctum token SPA auth. Frontend stores token in `localStorage` (`clinic_token`); `Authorization: Bearer …` header.

Two role layers on `User`:
- `role` (`UserRole` enum): `platform_admin` / `tenant_admin` / `member` — platform-level.
- `clinic_role` (`ClinicRole` enum): `admin` / `doctor` / `therapist` / `cashier` — clinic-module access.

Permissions are a static matrix in `App\Services\ClinicPermission` (role → module → `'r'`|`'rw'`), exposed via a single Gate `clinic.access` registered in `ClinicServiceProvider`. Policies delegate to it: `$user->can('clinic.access', ['booking', 'w'])`. Add a module → extend the matrix + write a Policy. Frontend mirrors the same role→module map in `apps/web/src/routes/$tenant/clinic/route.tsx` for sidebar visibility.

Route groups in `routes/api.php`:
- Public: `/translations`, `/register`, `/central/login`, `/invitations/{token}`(+`/accept`).
- Central platform admin (`auth:sanctum`, prefix `/central`): tenant list + status toggle.
- Tenant auth (`/{tenant}`, `resolve.tenant`): login/logout.
- Tenant user mgmt (`/{tenant}/users`): invite/remove/role.
- Clinic (`/{tenant}/clinic`): staff, services, patients, bookings, products/inventory, POS transactions, medical records, reports.

### Layering — Controller → Service → Action

- **Controller** (`app/Http/Controllers`): `$this->authorize(...)`, resolves FormRequest, calls Service or Action, returns `*Resource` wrapped as `{ data, meta }`. Uses `InteractsWithDataTable` trait to parse server-side DataTable query params (`page, per_page, sort, direction, search, filter[column]`).
- **Service** (`app/Services`): orchestrates a use case, may call multiple Actions and cross-cutting infra (`InvoiceService`, `StockService`, `ReportService`, `TransactionService`, `BookingOverlapService`, `InvitationService`, `TenantRegistrationService`). `ClinicPermission` is the permission matrix (not a use-case service).
- **Action** (`app/Actions`): one concrete unit of work in `execute()`/`handle()`. May inject Repository/Model/FormRequest/event/log and call other Actions (one-directional, no cycles). **Never inject a Service into an Action.** Examples: `LogAuditAction`, `PayTransactionAction`, `CancelTransactionAction`, `UploadMedicalPhotoAction`, `ArchiveServiceAction`, `RemoveUserAction`.

### Audit log

Currently native: `App\Models\AuditLog` (plain model, `properties` cast `array`) + `LogAuditAction::handle(action, subject, causer, context, tenant)` which does `AuditLog::create([...])` manually. `spatie/laravel-activitylog` is **not** installed. Every data-changing action calls `LogAuditAction` — narrative action strings like `user.login`, `tenant.registered`, `staff.created`. Tenant/causer default from container/auth when omitted.

Target design (in `docs/erd/audit_logs.md`): migrate to `spatie/laravel-activitylog` with custom `App\Models\Activity extends Spatie\Activitylog\Models\Activity` (`$table = 'audit_logs'`), `activity()->causedBy()->performedOn()->withProperties()->log()` API. `LogAuditAction` becomes a wrapper keeping the same signature. `tenant_id` moves from an explicit column to `properties->tenant_id`. Add spatie when model-event auto-log / `activity()` helper is needed — until then native is enough.

### i18n

`lang/id/*.php` groups (Indonesian, `APP_LOCALE=id`, fallback `en`). `TranslationController::index` serializes all groups to `GET /api/translations`; the web app fetches them once via `useTrans()` hook → global store → synchronous `t(key)`. Identifier keys are English; user-facing values are Indonesian.

### API response shape

Always `{ "data": …, "meta": … }` (collection → Resource collection in `data`, pagination in `meta`). Errors: HTTP status + `{ message, errors }`. Validation via FormRequest classes in `app/Http/Requests`.

## Frontend architecture (apps/web)

- Path aliases `#/*` and `@/*` → `src/*` (tsconfig `bundler` mode, `verbatimModuleSyntax`, strict).
- **File-based routing** in `src/routes` (TanStack Router, `tsr.config.json` target `react`). Run `bun run generate-routes` after adding/renaming route files.
- `src/routes/__root.tsx` — root layout. Decides chrome by path prefix: `/$tenant/clinic/*` and `/central/*` (except `/central/login`) render with admin sidebar (no public Header/Footer); everything else gets the public Header/Footer.
- `src/routes/$tenant/clinic/route.tsx` — clinic shell: role-filtered sidebar nav derived from `clinic_role`, logout clears auth.
- `src/lib/api.ts` — `fetch` wrapper (`apiGet/apiPost/apiPut/apiPatch/apiDelete/apiUpload`). Builds nested query params as `key[sub]`, attaches Bearer token from `localStorage`. `apiUpload` for `FormData` (no JSON content-type).
- `src/lib/auth.ts` — `localStorage` auth state (`clinic_token` + `clinic_user`); `hasClinicRole(...roles)` for FE guards.
- `src/integrations/tanstack-query` — React Query setup; server state via hooks (`useTrans`, etc.).
- UI: shadcn `radix-nova` style, `hugeicons` icon library (per `components.json`); primitives in `src/components/ui`, domain components colocated in `src/routes/<feature>/components/`, shared feature components in `src/components/{datatable,forms,medical-photos,schedule}`.

## Authoring discipline — MANDATORY

### Frontend (apps/web)

Setiap komponen/halaman baru atau restyle harus memenuhi dua lapis:

1. **Struktur design** — layout, hierarchy, density, spacing rhythm, komposisi komponen. Bukan asal tumpuk elemen; tiap region punya fungsi jelas, ada anchor visual, kontras bobot (size/weight/color) membimbing mata. Gunakan token design system yang sudah ada (`radix-nova`, Tailwind v4 tokens), bukan nilai hardcode.
4. **Arah visual — gaya Linear** — bersih, presisi, minim ornamen:
   - **Shadow tipis**: elevasi halus (`shadow-sm`/ring `1px` low-opacity), tidak ada shadow tebal/blur lebar. Beda elevation via border `1px` subtle + shadow tipis, bukan shadow dramatis.
   - **Clean**: border subtle (`border-border/50`), radii konsisten kecil-sedang (`rounded-md`/`rounded-lg`), background `neutral`/`muted`, hindari gradient mencolok dan warna jenuh. Putuskan hierarchy lewat spacing + border + bobot tipografi, bukan warna berlebih.
   - **Density tinggi**: komponen rapat tapi napas, padding konsisten kecil, informasi padat tanpa berantakan. Label kecil, numeralign-right, tabel borderless dengan row separator tipis.
   - **Fokus & feedback**: state fokus pakai ring `2px` tipis (accent), state aktif terlihat dari latar `muted` + teks weight, bukan shadow/gradient.
2. **Finishing / anti-slop** — hasil tidak boleh terasa generic AI: datar, monoton, tanpa karakter. Tambah micro-interaction (transition, hover/focus state, feedback state), perhatikan typographic detail (line-height, tracking, numeral alignment), empty/loading/error state yang manusiawi, konsistensi radii/border/shadow, dan motion yang halus bukan berlebihan. Tiap elemen interaktif wajib punya state lengkap (default/hover/active/focus/disabled). Pola ini berlaku juga saat delegasi ke `sierly`.
3. **Tooltip + shortcut wajib** — setiap aksi (button ikon, menu item, kontrol non-eviden) harus punya `Tooltip` yang menjelaskan fungsinya dan, bila relevan, shortcut keyboard-nya. Shortcut:
   - Hindari bentrok dengan bawaan browser/OS: `Ctrl/Cmd+W/T/N/F/L`, `F5`, `Alt+panah`, `Tab/Shift+Tab`, `Enter/Space`, `Esc` (close), `Cmd+K` (command palette), `Cmd+S`, `Cmd+R`.
   - Gunakan kombinasi aman: `g`+huruf (vim-style go-to), `?` (buka shortcut help), angka `1-9` (navigasi tab/section), atau `Mod+Shift+...` untuk aksi non-eviden.
   - Tampilkan shortcut di tooltip dalam badge monospace (`<kbd>`).
   - Sediakan layer help (`?`) yang daftar semua shortcut aktif.

### Backend (apps/api)

Authoring kode BE (controller, service, action, model, migration, form request, policy) mengikuti konvensi framework + prinsip clean code:

- **Framework-conform**: gunakan fitur Laravel sesuai tujuan (FormRequest untuk validasi, Resource untuk response shape, Eloquent untuk single CRUD, Policy/Gate untuk otorisasi, `DB::transaction` untuk multi-write atomik). Hindari raw SQL untuk single CRUD (kecuali bulk/query kompleks — beri alasan `ponytail:`).
- **Clean code**: SOLID ( satu responsibility per class, dependency inversion via injection), DRY (extract duplikasi ke Action/trait/private method), KISS (no premature abstraction — no interface dengan satu implementasi, no factory untuk satu produk). Action = satu use case di `execute()`/`handle()`, tidak boleh inject Service. Controller → Service → Action, satu arah, tanpa siklus. Pola ini berlaku juga saat delegasi ke `ammar`.

## Conventions

- Names: English identifiers everywhere (entities, folders, tables, routes, i18n keys). Comments may be Indonesian.
- PHP: PascalCase class files, snake_case migrations/config. JS/TS/CSS: kebab-case files. Component max 300 lines, PHP method max 100 lines — extract beyond that.
- UI text: Indonesian, semi-formal friendly tone. Every inner page has a breadcrumb reflecting root→active hierarchy.
- `ponytail:` comments mark deliberate simplifications with the ceiling and upgrade path.

## Git

- Commit langsung ke branch saat ini. Jangan buat branch/PR baru hanya untuk commit, kecuali user meminta eksplisit.
- NO emoji di commit/PR/code. NO AI attribution: no `Co-Authored-By: Claude`/bot, `Generated with [Claude Code]`, `🤖`, atau marker AI lain. Commit murni atas nama user. Conventional Commits.
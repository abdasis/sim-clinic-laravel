# Implementation Plan: Multi-Tenant Single Database

**Branch**: `001-multi-tenant-single-db` | **Date**: 2026-07-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-multi-tenant-single-db/spec.md`

## Summary

Platform klinik multi-tenant pada satu shared database, identifikasi tenant via segmen path URL (`/{tenant-slug}/...`), tanpa subdomain. Isolasi data via global scope Eloquent + trait `BelongsToTenant` pada `tenant_id`. Tenant self-registration publik membuat tenant + admin pertama atomik. Multi-user per tenant via undangan email. Admin platform kelola status tenant. Audit log aksi kritis. Frontend React 19 (TanStack Router/Query/Table) pakai datatable yang sudah ada (`apps/web/src/components/datatable/`), icon wajib `@hugeicons/react`, form reusable baru di `apps/web/src/components/forms/` (react-hook-form + zod).

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13.8 (backend `apps/api`); TypeScript + React 19 (frontend `apps/web`).

**Primary Dependencies**:
- Backend (sudah terinstall): `laravel/framework ^13.8`, `laravel/sanctum ^4.0`. Backend baru (per user input): `spatie/laravel-activitylog` untuk audit log aksi kritis (FR-028).
- Frontend (sudah): `@tanstack/react-router`, `@tanstack/react-query`, `@tanstack/react-table ^8.21.3`, `@shadcn/react`, `react-hook-form ^7.81.0`, `@hookform/resolvers`, `zod ^4.4.3`, `sonner`, `tailwindcss ^4`.
- Frontend baru (per user input): `@hugeicons/react`, `@hugeicons/core-free-icons`.

**Storage**: Single shared database — SQLite (dev default), Postgres/MySQL (produksi). Tidak ada DB per tenant.

**Testing**: PHPUnit 12 (`php artisan test`) backend; Vitest 4 (`bun run test`) frontend.

**Target Platform**: Web (browser). Backend Laravel SPA-API + Frontend TanStack Start SSR.

**Project Type**: Web service (API) + SPA frontend (monorepo `apps/api`, `apps/web`).

**Performance Goals**: Identifikasi tenant via path tidak menambah penalti respons yang dirasakan (SC-003). Skala awal 100 tenant × 50 user (SC-008) tanpa degradasi.

**Constraints**: Class PHP ≤300 baris, method ≤100 baris (CLAUDE.md). File React ≤300 baris. i18n wajib `__()` / `t()` dari `lang/id/*.php`. Breadcrumb wajib setiap halaman. Form ≤5 field modal, >5 field halaman. Komentar Indonesia, hanya untuk logika rumit.

**Scale/Scope**: v1 = tenant register + admin pertama + identifikasi path + isolasi + multi-user + platform admin + audit log. Hapus permanen tenant, multi-tenant per user, permission granular di luar scope.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` masih placeholder (belum diratifikasi — semua field `[PRINCIPLE_X_*]` kosong). Tidak ada prinsip konkret untuk dievaluasi → tidak ada gate yang dilanggar. Re-check post-design: tetap tidak ada prinsip konkret → gate lolos.

**Catatan**: Constitution kosong berarti tidak ada constraint governance. Disarankan user ratifikasi constitution sebelum implementasi, tapi tidak memblokir plan.

## Project Structure

### Documentation (this feature)

```text
specs/001-multi-tenant-single-db/
├── plan.md              # file ini
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   └── api-contracts.md # Phase 1
└── tasks.md             # Phase 2 (/speckit-tasks, belum dibuat)
```

### Source Code (repository root)

```text
apps/
├── api/                          # Laravel 13 backend
│   ├── app/
│   │   ├── Models/
│   │   │   ├── Tenant.php
│   │   │   ├── User.php           # extend: tenant_id, role, status
│   │   │   ├── Invitation.php
│   │   │   └── Activity.php       # extend Spatie\Activitylog\Models\Activity, $table='audit_logs'
│   │   ├── Scopes/
│   │   │   └── TenantScope.php
│   │   ├── Concerns/
│   │   │   ├── BelongsToTenant.php # trait
│   │   │   └── InteractsWithDataTable.php # sudah ada
│   │   ├── Http/
│   │   │   ├── Middleware/
│   │   │   │   ├── ResolveTenant.php
│   │   │   │   └── EnsureTenantActive.php
│   │   │   ├── Controllers/
│   │   │   │   ├── TenantRegistrationController.php
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── UserController.php
│   │   │   │   └── PlatformTenantController.php
│   │   │   ├── Requests/
│   │   │   │   ├── RegisterTenantRequest.php
│   │   │   │   ├── InvitationRequest.php
│   │   │   │   └── UpdateTenantStatusRequest.php
│   │   │   └── Resources/
│   │   │       ├── TenantResource.php
│   │   │       └── UserResource.php
│   │   ├── Services/
│   │   │   ├── TenantRegistrationService.php
│   │   │   └── InvitationService.php
│   │   └── Actions/
│   │       ├── LogAuditAction.php
│   │       └── RemoveUserAction.php
│   ├── database/
│   │   ├── migrations/
│   │   │   ├── create_tenants_table.php
│   │   │   ├── add_tenant_role_status_to_users_table.php
│   │   │   ├── create_invitations_table.php
│   │   │   └── (audit_logs: publish dari spatie via vendor:publish --tag=activitylog-migrations, rename tabel via custom Activity model)
│   │   ├── factories/
│   │   └── seeders/
│   │       └── CentralTenantSeeder.php
│   ├── routes/
│   │   ├── api.php                 # tenant-scoped + central + publik
│   │   └── web.php                 # landing page
│   └── lang/id/
│       ├── auth.php
│       ├── tenant.php
│       └── general.php
└── web/                          # React 19 frontend (TanStack Start)
    └── src/
        ├── components/
        │   ├── datatable/         # sudah ada, reuse
        │   ├── forms/             # BARU: form reusable
        │   │   ├── form-field.tsx
        │   │   ├── form-input.tsx
        │   │   ├── form-select.tsx
        │   │   ├── form-submit.tsx
        │   │   └── use-form.ts    # wrapper react-hook-form + zod
        │   └── ui/                # shadcn (sudah ada)
        ├── routes/
        │   ├── index.tsx          # landing page publik (root /)
        │   ├── $tenant/           # tenant-scoped routes
        │   │   ├── login.tsx
        │   │   ├── users/         # datatable user list + invite form
        │   │   └── dashboard.tsx
        │   └── central/
        │       ├── login.tsx
        │       └── tenants/       # datatable tenant list (platform admin)
        ├── hooks/
        │   └── use-trans.ts       # t() helper dari usePage().props.translations
        └── utils/
            └── trans.ts
```

**Structure Decision**: Monorepo `apps/api` (Laravel) + `apps/web` (TanStack Start). Backend: model + global scope + trait + service/action (CLAUDE.md class ≤300, method ≤100). Frontend: reuse datatable yang ada, buat `components/forms/` reusable, route `$tenant` untuk path-based tenant, `central` untuk platform admin. i18n via `lang/id/*.php` + `usePage().props.translations` + `t()` helper (CLAUDE.md).

## Complexity Tracking

> Tidak ada violation constitution (constitution kosong). Tabel tidak diisi.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| - | - | - |
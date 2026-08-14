# Implementation Plan: Platform Infrastructure — Tenants & Audit Log

**Branch**: `main` (work on `main`; spec dir `003-platform-infra`) | **Date**: 2026-08-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/003-platform-infra/spec.md`

## Summary

Infra platform dua langkah. **Langkah 1 (tenants)** sebagian besar sudah tersedia di codebase: model `Tenant` + migration (slug unique, status enum), `BelongsToTenant` concern + `TenantScope`, `TenantRegistrationService` (registrasi atomik + slug derivasi `Str::slug` + reject duplikat), `CentralAuthController::login`, `PlatformTenantController` (list DataTable + status toggle), `ResolveTenant` + `EnsureTenantActive` middleware, `CentralTenantSeeder`, serta FE `central/login.tsx`, `register.tsx`, `central/tenants/index.tsx` + `status-toggle.tsx`, central shell `central/route.tsx`. Yang **net-new** di Langkah 1: halaman dashboard central (`/central/index.tsx`) + nav item Dashboard di central shell + platform-admin role guard FE (`hasPlatformRole`) — karena FE saat ini hanya punya `hasClinicRole`, tidak ada pengecekan role platform. **Langkah 2 (audit_logs)** net-new: migrasi audit log dari native (`App\Models\AuditLog` + `AuditLog::create`) ke `spatie/laravel-activitylog` dengan custom `App\Models\Activity` (table `audit_logs`), `LogAuditAction` diubah jadi wrapper `activity()->causedBy()->performedOn()->withProperties()->log()`, `tenant_id` pindah dari kolom eksplisit ke `properties->tenant_id`. Morph causer/subject tetap (tidak ada FK DB) — audit tidak putus saat aktor/target dihapus. Disesuaikan dengan `docs/erd/audit_logs.md` + `docs/normalization/README.md`.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13 `^13.8`); TypeScript / React 19 (TanStack Start, SSR).

**Primary Dependencies**:
- BE: `laravel/framework ^13.8`, `laravel/sanctum ^4.0` (token SPA auth). **Menambah**: `spatie/laravel-activitylog` (Langkah 2) — diverifikasi via Context7: custom Activity model + custom table via `protected $table`, config `activity_model`, API `activity()->causedBy()->performedOn()->withProperties()->log()`, query `Activity::where('properties->tenant_id', ...)`.
- FE: TanStack Router + Query, shadcn `radix-nova`, Tailwind v4, `hugeicons`. Form primitives (`src/components/forms/`) + server-side `DataTable` (`src/components/datatable/`) sudah ada.

**Storage**: PostgreSQL (single-db multi-tenant, port 5435). Tests: SQLite `:memory:` (phpunit.xml).

**Testing**: PHPUnit (`php artisan test`); FE vitest. Konstitusi mewajibkan feature test per endpoint + unit test untuk logic non-trivial (TDD Red-Green-Refactor).

**Target Platform**: Linux server (API port 8000, web port 3001).

**Project Type**: Web service (API-only Laravel) + SPA (TanStack Start). Monorepo Bun workspaces: `apps/api` + `apps/web`.

**Performance Goals**: Standar web app — login central <5s, registrasi tenant <10s (SC-001/SC-002). Audit log query per tenant tanpa index JSON path untuk MVP; `ponytail: JSON path index add saat lambat`.

**Constraints**: Isolasi tenant non-negotiable (Konstitusi III); class PHP ≤300 baris, method ≤100 baris; komponen React ≤300 baris; nama identifier English; teks UI via `t()` (Indonesia semi-formal). FK `tenant_id` child `cascadeOnDelete` (revisi; hapus tenant di luar scope v1). Audit log immutable, morph (bukan FK).

**Scale/Scope**: MVP multi-tenant; jumlah tenant kecil-menengah. Audit log tumbuh monoton (immutable).

**Unknowns → research.md**:
- D1: strategi migrasi native audit_logs → spatie tanpa kehilangan data eksisting (table sudah ada, schema berbeda: native punya `tenant_id` + `action` + `causer_id` FK; spatie punya `log_name` + `description` + morph `causer_id`/`causer_type` + `subject_type` + `subject_id`).
- D2: cara aman rename/adopt table `audit_logs` sebagai table spatie (custom model `$table = 'audit_logs'`), publish migration spatie lalu adaptasi agar tidak bentrok dengan migration native existing.
- D3: slug URL-safe — `Str::slug` sudah dipakai; konfirmasi reject karakter non-URL-safe + reserved `central` (saat ini `TenantRegistrationService` cek duplikat tapi tidak eksplisit reject slug `central`).
- D4: FE platform-admin guard — tambah `hasPlatformRole()` di `src/lib/auth.ts` + guard ringan di `central/route.tsx`/dashboard (client-side, konsisten dengan pola auth localStorage saat ini; `ponytail:` route guard server bila butuh).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Konstitusi: `.specify/memory/constitution.md` v1.0.0. Prinsip aktif:

| Prinsip | Status | Catatan |
|---|---|---|
| I. Clean Code | PASS | Nama deskriptif, SRP per Service/Action, DRY via `LogAuditAction` tunggal, no dead code. `LogAuditAction` wrapper menjaga signature konsisten. |
| II. TDD | PASS (rencana) | tasks.md menulis test lebih dulu (Red) sebelum implementasi audit migrasi + dashboard. Feature test per endpoint (registrasi, central login, status toggle) + unit test `LogAuditAction` post-migrasi + query `properties->tenant_id`. |
| III. Multi-Tenant Isolation | PASS | `BelongsToTenant` + `TenantScope` + `ResolveTenant`/`EnsureTenantActive` sudah ada. Audit log TIDAK di-scope (benar — aksi platform_admin lintas tenant). `tenant_id` di `properties->tenant_id`, queryable per tenant. Central routes enforce `assertPlatformAdmin()` per-controller. FE: tambah `hasPlatformRole` guard agar dashboard central tidak diakses non-platform-admin. |
| IV. Simplicity (YAGNI) | PASS | Reuse kode existing (Langkah 1 BE hampir semua sudah ada). Tidak tambah interface/factory. spatie dipakai karena menyederhanakan audit (mengganti manual `AuditLog::create` + menyediakan `activity()` helper + model-event auto-log jikalau nanti butuh). Tidak tambah route guard middleware baru (enforce per-controller sudah cukup, sesuai pola existing). Dashboard minimal (ringkasan tenant + link), tidak over-build. |
| V. Bounded Size | PASS | `LogAuditAction` kecil (<50 baris); controller/service existing <300 baris; komponen FE dashboard <300 baris, ekstrak ke `components/` bila melebihi. |

**Gate verdict**: PASS — semua prinsip terpenuhi, tidak ada pelanggaran yang perlu justifikasi. Lanjut Phase 0.

### Post-design re-check (Phase 1)

Setelah `research.md` + `data-model.md` + `contracts/` selesai, re-evaluasi:

| Prinsip | Status | Catatan |
|---|---|---|
| I. Clean Code | PASS | `LogAuditAction` wrapper menjaga SRP + DRY (single point audit); migration adaptasi satu file; tidak ada dead code (native `AuditLog` dihapus post-migrasi). |
| II. TDD | PASS (rencana) | tasks.md (fase berikut) menulis test Red lebih dulu untuk: migrasi audit, `LogAuditAction` post-migrasi, query `properties->tenant_id`, dashboard FE guard. |
| III. Multi-Tenant Isolation | PASS | `tenant_id` di `properties->tenant_id`, queryable per tenant; audit tidak di-scope global (benar untuk aksi platform). FE `hasPlatformRole()` gate dashboard. |
| IV. Simplicity | PASS (justified dep) | `spatie/laravel-activitylog` = dependency baru. **Justifikasi**: eksplisit dimandatkan input user + target desain `docs/erd/audit_logs.md`; mengganti manual `AuditLog::create` → `activity()` helper (mengurangi kode jangka panjang + menyediakan auto-log model event jikalau butuh). Tidak ada interface/factory satu-impl. Reuse semua kode existing Langkah 1. **Tidak melanggar** — spatie menyelesaikan masalah yang beberapa baris tidak bisa (morph + helper + migration standar). |
| V. Bounded Size | PASS | `App\Models\Activity` <30 baris; `LogAuditAction::handle` <40 baris; migration adaptasi <80 baris; dashboard FE `central/index.tsx` <300 baris (ekstrak stat card ke `components/` bila melebihi). |

**Post-design verdict**: PASS. Satu dependency baru (spatie) terjustifikasi di Complexity Tracking. Lanjut ke `/speckit-tasks`.

## Project Structure

### Documentation (this feature)

```text
specs/003-platform-infra/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── api-contracts.md # Phase 1 output
├── checklists/
│   └── requirements.md  # /speckit-specify output
└── tasks.md             # /speckit-tasks output (next phase)
```

### Source Code (repository root)

```text
apps/api/
├── app/
│   ├── Actions/
│   │   └── LogAuditAction.php          # MODIFIED: wrapper activity() (Langkah 2)
│   ├── Models/
│   │   ├── Activity.php                # NEW: extends Spatie\Activitylog\Models\Activity, $table='audit_logs' (Langkah 2)
│   │   ├── AuditLog.php                # DEPRECATED/REMOVED post-migrasi (Langkah 2)
│   │   ├── Tenant.php                  # exists (no change)
│   │   └── User.php                    # exists (no change)
│   ├── Services/
│   │   └── TenantRegistrationService.php  # exists; confirm slug `central` reject (Langkah 1 hardening)
│   ├── Http/Controllers/
│   │   ├── CentralAuthController.php   # exists (no change)
│   │   ├── PlatformTenantController.php # exists (no change)
│   │   └── TenantRegistrationController.php # exists (no change)
│   ├── Http/Middleware/
│   │   ├── ResolveTenant.php           # exists (no change)
│   │   └── EnsureTenantActive.php      # exists (no change)
│   └── Enums/ {UserRole, ClinicRole, TenantStatus}.php  # exist (no change)
├── config/
│   └── activitylog.php                 # NEW: publish + set activity_model=App\Models\Activity (Langkah 2)
├── database/
│   └── migrations/
│       ├── 2026_07_06_000003_create_audit_logs_table.php  # exists (native schema) — Langkah 2: adapt to spatie schema
│       └── <tenant/users/... migrations>                   # exist (no change)
└── routes/api.php                      # exists (no change — routes already wired)

apps/web/src/
├── components/
│   ├── forms/                          # exists — reuse FormInput/FormSubmit/useForm (no new primitive needed for login/register/dashboard)
│   └── datatable/                      # exists — reuse for tenant list (central/tenants already uses it)
├── lib/
│   └── auth.ts                         # MODIFIED: add hasPlatformRole() (Langkah 1 FE)
└── routes/
    └── central/
        ├── route.tsx                   # MODIFIED: add Dashboard nav item + platform-admin guard (Langkah 1 FE)
        ├── login.tsx                   # exists (no change)
        ├── index.tsx                   # NEW: central dashboard page (Langkah 1 FE)
        └── tenants/ {index.tsx, components/status-toggle.tsx}  # exist (no change)
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` (existing). Langkah 1 BE nyaris tanpa perubahan kode (reuse existing); net-new FE dashboard + guard. Langkah 2 BE migrasi audit ke spatie (1 model baru, 1 config, 1 action diubah, adaptasi 1 migration). Tidak ada struktur baru — semua perubahan di slot existing. Dashboard FE mengikuti bentuk halaman `central/tenants/index.tsx` (breadcrumb `ClinicBreadcrumb` + heading + content), komposisi pakai `DataTable` existing bila perlu ringkasan tenant di dashboard, dan form primitives existing untuk setiap form.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Item | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Dependency baru `spatie/laravel-activitylog` | Dimandatkan input user + target desain `docs/erd/audit_logs.md`; menyediakan morph causer/subject, `activity()` helper, dan jalan migrasi ke auto-log model event. Mengganti native `AuditLog::create` manual. | Native `AuditLog::create` dipertahankan → ditolak: tidak menyediakan `activity()` helper, morph terbatas (`causer_id` FK→users, bukan morph penuh), dan tidak selaras dengan sumber kebenaran ERD yang menetapkan spatie sebagai target. Kode manual yang meniru spatie (morph penuh + helper + migration standar) = lebih banyak kode untuk masalah yang sudah diselesaikan package. |

Tidak ada pelanggaran ukuran/clean-code/isolasi — tabel ini hanya mencatat keputusan dependency tunggal yang terjustifikasi.
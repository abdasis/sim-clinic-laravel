# Implementation Plan: Users & Invitations

**Branch**: `004-users-invitations` | **Date**: 2026-08-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-users-invitations/spec.md`

## Summary

Revisi fitur users + invitations pada tenant: (1) soft-delete user (`deleted_at`) + index `(tenant_id, deleted_at)`; FK `bookings.assignee_id`/`medical_records.author_id`/`transactions.cashier_id` → `restrictOnDelete` (bukan cascade). (2) `RemoveUserAction` ganti hard-delete → soft-delete + `status=inactive`, naratif audit "Menonaktifkan staf {name} — peran {role}". (3) Migrasi otorisasi dari matriks statis `ClinicPermission` + Gate `clinic.access` ke **`spatie/laravel-permission`** dengan fitur **teams** (`team_id = tenant_id`) untuk scope role/permission per tenant — keputusan eksplisit user, mengesampingkan default Constitution IV. (4) Penonaktifan admin terakhir ditolak (FR-005/025). (5) FE: halaman login tenant (sudah ada), manajemen staf + undangan (sudah ada) — revisi untuk soft-delete awareness + breadcrumb + reuse `components/datatable` & `components/forms`; form reusable baru (`form-password` + konfirmasi dialog nonaktifkan) disimpan di `components/forms/`.

## Technical Context

**Language/Version**: PHP 8.3+ (apps/api), TypeScript 5.x strict (apps/web), React 19.

**Primary Dependencies**:
- BE: Laravel 13.8, laravel/sanctum 4 (token SPA auth), **`spatie/laravel-permission` v7** (BARU — ditambah via composer), `spatie/laravel-activitylog` (sudah ada dari spec 003 — audit log).
- FE: TanStack Start (SSR), TanStack Router + Query, react-hook-form + zod, shadcn/ui `radix-nova`, Tailwind v4, sonner (toast).
- Reusable FE: `components/datatable/*` (DataTable, Toolbar, Pagination, ColumnHeader, ViewOptions, FacetedFilter), `components/forms/*` (useForm+zod resolver, FormInput, FormSelect, FormTextarea, FormDatePicker, FormSubmit).

**Storage**: PostgreSQL (single-db multi-tenant). Tabel baru dari spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (semua dengan `team_id` karena `teams=true`).

**Testing**: PHPUnit (apps/api, sqlite :memory: per phpunit.xml); Vitest (apps/web).

**Target Platform**: Linux server (API port 8000, web port 3001).

**Project Type**: Web service (API-only Laravel) + SSR frontend, monorepo Bun workspaces.

**Performance Goals**: Login tenant < 5 detik (SC-001); daftar staf aktif query efisien via index `(tenant_id, deleted_at)`.

**Constraints**: Class PHP <= 300 baris, method <= 100 baris; komponen React <= 300 baris; teks UI via i18n (`__()`/`t()`); tidak ada string hardcode; breadcrumb wajib tiap halaman dalam.

**Scale/Scope**: Multi-tenant — role/permission di-scope per tenant via `team_id=tenant_id`. Dua lapis peran: `role` platform-level (enum) + `clinic_role` (sekarang via spatie role per team).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|--------|
| I. Clean Code | PASS | Authoring BE/FE ikut konvensi; delegasi ke `ammar` (BE) + `sierly` (FE) dengan brief skill. |
| II. TDD | PASS | Test task ditulis lebih dulu (tasks.md); Feature test per endpoint + unit test Action/Service. |
| III. Multi-Tenant Isolation | PASS | spatie teams (`team_id=tenant_id`) scope role/permission per tenant; `setPermissionsTeamId()` di middleware per request tenant. |
| IV. Simplicity (YAGNI) | **VIOLATION (justified)** | Penambahan `spatie/laravel-permission` + 5 tabel RBAC padahal matriks statis sudah memadai untuk MVP. **Justifikasi**: keputusan eksplisit user "ganti, harus menggunakan spatie permission". Tidak ada default wajar yang ditolak — ini arahan langsung. Lihat Complexity Tracking. |
| V. Bounded Size | PASS | Semua class/file dalam batas; extract bila melebihi. |

## Project Structure

### Documentation (this feature)

```text
specs/004-users-invitations/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── api-contracts.md # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit-tasks - NOT created here)
```

### Source Code (repository root)

```text
apps/api/
├── app/
│   ├── Enums/                  # UserRole, ClinicRole, UserStatus, InvitationStatus (existing, revision minimal)
│   ├── Models/
│   │   ├── User.php            # +HasRoles trait, +SoftDeletes, guard_name
│   │   ├── Invitation.php      # existing (revision: clinic_role on accept)
│   │   └── Role.php            # BARU — Spatie\Permission\Models\Role subclass bila perlu (default cukup)
│   ├── Http/
│   │   ├── Middleware/
│   │   │   └── SetPermissionTeamId.php  # BARU — setPermissionsTeamId(app('tenant')->id)
│   │   ├── Controllers/
│   │   │   ├── AuthController.php        # existing (login tenant) — pasang team id
│   │   │   ├── UserController.php        # existing — revisi: spatie role assign, soft-delete
│   │   │   ├── StaffController.php       # existing — revisi: spatie hasPermissionTo, soft-delete
│   │   │   └── InvitationController.php  # existing — accept assign spatie role
│   │   └── Requests/          # InvitationRequest, StoreStaffRequest, UpdateStaffRoleRequest (revisi)
│   ├── Actions/
│   │   ├── RemoveUserAction.php  # existing — revisi: soft-delete + status=inactive + naratif
│   │   ├── DeactivateStaffAction.php  # BARU — ekstrak logika nonaktifkan staf dari StaffController
│   │   └── LogAuditAction.php    # existing (dipakai apa adanya)
│   ├── Services/
│   │   ├── InvitationService.php  # existing — revisi: assign spatie role saat accept
│   │   ├── TenantRegistrationService.php  # existing — revisi: assign role admin pertama via spatie
│   │   └── ClinicPermission.php   # DEPRECATE — matriks digantikan spatie; hapus setelah migrasi Policy
│   ├── Policies/                # UserPolicy, dll — revisi: delegate ke spatie hasPermissionTo
│   └── Providers/
│       └── ClinicServiceProvider.php  # existing — revisi: Gate clinic.access → spatie, atau remove
├── database/
│   ├── migrations/
│   │   ├── *_add_soft_delete_to_users_table.php       # BARU — deleted_at + index(tenant_id, deleted_at)
│   │   ├── *_restrict_fk_to_users.php                 # BARU — drop+recreate FK assignee/author/cashier restrictOnDelete
│   │   └── *_create_permission_tables.php             # BARU — dari vendor:permission publish
│   ├── seeders/
│   │   ├── RolesAndPermissionsSeeder.php  # BARU — seed role (per clinic_role) + permission matrix per module
│   │   ├── CentralTenantSeeder.php        # existing — revisi: assign platform_admin role via spatie (team null/global)
│   │   └── TenantAdminSeeder.php          # existing — revisi: assign tenant_admin + clinic admin role
│   └── factories/UserFactory.php          # existing — revisi: withRole() helper bila perlu
└── config/permission.php  # BARU — teams=true, team_foreign_key=tenant_id

apps/web/src/
├── components/
│   ├── datatable/               # existing — REUSE (DataTable, Toolbar, Pagination, dst.)
│   └── forms/                   # existing — REUSE + 1 baru:
│       └── form-password.tsx    # BARU — reusable password+confirm field (dipakai login?/accept)
├── routes/$tenant/
│   ├── login.tsx                # existing — REUSE (sudah pakai forms/), minor breadcrumb tweak
│   ├── users/                   # existing — revisi: soft-delete awareness, undangan, breadcrumb
│   │   ├── index.tsx
│   │   └── components/invite-modal.tsx
│   ├── clinic/staff/            # existing — revisi: nonaktifkan (soft-delete), role select, breadcrumb
│   │   ├── index.tsx
│   │   └── components/
│   │       ├── staff-form-modal.tsx
│   │       ├── staff-actions-cell.tsx
│   │       └── deactivate-staff-dialog.tsx  # BARU — konfirmasi nonaktifkan (reuse confirm pattern)
│   └── clinic/route.tsx         # existing — revisi: sidebar mirror dari spatie permission/role
└── routes/invitations/$token.tsx  # existing — revisi: accept flow (set password), redirect login
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` (sudah ada). Fitur ini mayoritas revisi file existing + sedikit file baru. Tidak ada struktur baru — ikut pola Controller→Service→Action (BE) dan file-based routes + colocated components (FE). Form reusable baru disimpan di `components/forms/` per instruksi user.

## Complexity Tracking

> Constitution Check punya 1 violation yang harus dijustifikasi.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| `spatie/laravel-permission` + 5 tabel RBAC padahal `ClinicPermission` matriks statis sudah memadai untuk MVP (Constitution IV) | Keputusan eksplisit user: "ganti, harus menggunakan spatie permission" untuk manajemen role/permission antar tenant. Membuka jalur role/permission CRUD runtime + audit siapa-punya-apa per tenant di masa depan. | Matriks statis + Gate `clinic.access` (existing) — ditolak oleh instruksi langsung user; bukan default wajar yang ditolak, melainkan arahan eksplisit yang mengesampingkan Constitution IV. Migrasi ini sekali jalan; blast radius = rewrite Policy + seeder + FE sidebar mirror. |
# Data Model — Users & Invitations

**Feature**: [spec.md](./spec.md) | **Sumber kebenaran**: [docs/normalization/README.md](../../docs/normalization/README.md) + [docs/erd/users.md](../../docs/erd/users.md) + [docs/erd/invitations.md](../../docs/erd/invitations.md)

> Data model sumber kebenaran adalah ERD + normalisasi. Dokumen ini mencatat **revisi** pada `users` + tabel RBAC baru dari spatie.

## Entity: `users` (REVISI)

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | existing |
| tenant_id | bigint unsigned | FK → tenants.id, cascadeOnDelete | BelongsToTenant; central tenant untuk platform admin |
| name | string(255) | not null | |
| email | string(255) | unique (global), not null | FR-015 |
| password | string(255) | not null (hashed) | min 8, campur huruf+angka |
| role | enum(platform_admin, tenant_admin, member) | not null, default member | FR-024 — dipertahankan sebagai enum platform-level (info cepat, redundant terhadap spatie role global; jaga-konsistensi: assign bersamaan) |
| clinic_role | enum(admin, doctor, therapist, cashier) | nullable | FR-001 — dipertahankan sebagai enum (mirror cepat spatie role per-team) |
| status | enum(pending, active, inactive) | default active | FR-020/023 |
| email_verified_at | timestamp | nullable | v1 opsional |
| **deleted_at** | timestamp | nullable | **BARU (revisi)** — soft delete; `SoftDeletes` trait |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Index (REVISI)**:
- `email` UNIQUE (global) — existing.
- `tenant_id` INDEX — existing.
- **`(tenant_id, deleted_at)` INDEX** — BARU: list staf aktif per tenant (whereNull deleted_at).
- `clinic_role` INDEX — existing.

**Delete Rule (REVISI)**: Soft-delete (`deleted_at`). `RemoveUserAction`/`DeactivateStaffAction` → `status=inactive` + `delete()` (soft). Hard-delete dilarang.

**FK ke `users` (REVISI → restrictOnDelete)**:
- `bookings.assignee_id` → `users.id` : **restrictOnDelete** (drop cascade/restrict existing, recreate restrictOnDelete).
- `medical_records.author_id` → `users.id` : **restrictOnDelete**.
- `transactions.cashier_id` → `users.id` : **restrictOnDelete**.

> `ponytail: role enum + clinic_role enum redundant vs spatie tables — dipertahankan sebagai mirror cepat untuk query/sidebar tanpa join RBAC; jaga-konsistensi: assign role spatie + set enum bersamaan dalam DB transaction. Hapus enum saat semua konsumer baca dari spatie (upgrade path: saaat audit membuktihkan drift).`

## Entity: `invitations` (tidak berubah)

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK → tenants.id, cascadeOnDelete | |
| email | string(255) | not null | |
| role | enum(tenant_admin, member) | default member | FR-024 — keanggotaan tenant-level; clinic_role ditentukan saat/after accept |
| clinic_role | enum(admin, doctor, therapist, cashier) | nullable | **BARU (revisi minor)** — peran klinik yang akan di-assign saat accept (spec 002); nullable untuk non-klinik |
| token | string(64) | unique, not null | accept URL |
| expires_at | timestamp | not null | default +7 hari |
| status | enum(pending, accepted, cancelled, expired) | default pending | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Index**: `(tenant_id, email)` — existing.

**State transitions**: `pending` → (`accepted` | `cancelled` | `expired`), satu arah, no reverse.

**Business rules**: tolak email sudah user aktif (`status=active`, `deleted_at IS NULL`) di tenant sama (FR-022). Accept → buat User + assign spatie role (platform `member`/`tenant_admin` + clinic `clinic_role`) + undangan `accepted`.

## Entity: RBAC tables (BARU — spatie/laravel-permission, teams=true)

Dari `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"` (migration `create_permission_tables`), dengan `team_foreign_key = tenant_id`:

| Tabel | Kolom kunci | Catatan |
|-------|-------------|---------|
| `roles` | id, name, guard_name, **team_id** (nullable) | team_id=tenant_id untuk role per-team (clinic); null untuk global (platform) |
| `permissions` | id, name, guard_name | mis. `staff.r`, `staff.rw`, `booking.r`, `booking.rw`, dst. per modul |
| `model_has_roles` | role_id, model_type, model_id, **team_id** | pivot user↔role, scoped team |
| `model_has_permissions` | permission_id, model_type, model_id, **team_id** | permission langsung ke user (jarang dipakai; role cukup) |
| `role_has_permissions` | permission_id, role_id | permission yg dimiliki role |

**Guard**: `sanctum` (tunggal) — `User::$guard_name = 'sanctum'`.

**Role design**:
- Global (`team_id=null`): `platform_admin`, `tenant_admin`, `member`.
- Per-team (`team_id=tenant_id`): `admin`, `doctor`, `therapist`, `cashier` (clinic_role).

**Permission matrix (seed `RolesAndPermissionsSeeder`)** — mirror `ClinicPermission::MATRIX` existing ke spatie `role_has_permissions`:

| Role (clinic) | Permission |
|---------------|------------|
| admin | staff.rw, service.rw, patient.rw, booking.rw, medical_record.rw, product.rw, inventory.rw, transaction.rw, invoice.rw, report.rw |
| doctor | patient.rw, booking.rw, medical_record.rw, service.r |
| therapist | patient.r, booking.rw, medical_record.rw, service.r |
| cashier | patient.rw, transaction.rw, invoice.rw |

**Tenant isolation**: `setPermissionsTeamId($tenant->id)` di `SetPermissionTeamId` middleware pada route group `{tenant}`. spatie cache di-scope per team_id. Central route → team_id null (platform roles).

## Validation rules (dari requirements)

- email: `required|email|unique:users,email` (global) saat create staff; undangan cek per-tenant.
- password: `required|min:8|regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/` (FR-016).
- clinic_role: `required|in:admin,doctor,therapist,cashier` (StoreStaffRequest, UpdateStaffRoleRequest).
- role (platform, UserController::role): `required|in:member,tenant_admin`.
- invitation role: `required|in:tenant_admin,member` + `clinic_role: nullable|in:admin,doctor,therapist,cashier`.
- token: 64-char unique, generated server-side.

## State transitions

**User status**: registrasi→`active` (admin pertama) | undangan→`pending`→(set password)→`active` | nonaktif→`inactive`+`deleted_at` (soft). No hard-delete.

**Invitation status**: `pending`→(`accepted`|`cancelled`|`expired`), satu arah.
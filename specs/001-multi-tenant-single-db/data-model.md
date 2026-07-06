# Data Model: Multi-Tenant Single Database

**Spec**: [spec.md](./spec.md) | **Research**: [research.md](./research.md)

Semua tabel dalam satu shared database. Model tenant-scopeable ditandai `BelongsToTenant` trait + `TenantScope` global scope.

## Entities

### Tenant

Entitas klinik/organisasi. Acuan isolasi seluruh data.

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK, auto-increment | |
| name | string(255) | not null | Nama perusahaan |
| slug | string(255) | unique, not null, URL-safe | Diturunkan dari name; validasi reject non-URL-safe (FR-004, FR-005) |
| phone | string(50) | not null | Format lokal/internasional divalidasi |
| status | enum(active, inactive) | default `active` | FR-011: nonaktif tidak hapus data |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Unique**: `slug` (DB constraint). FR-004, FR-005.

**State transitions**:
- `active` ↔ `inactive` (FR-011, FR-006). Nonaktif → akhiri sesi aktif (FR-009).
- Hapus permanen di luar scope v1.

**Relationships**: hasMany `User`, hasMany `{TenantScopeableData}`. Audit log via spatie Activity (tidak relasi Eloquent langsung — query lewat `properties->tenant_id`).

---

### Central Tenant

Bukan tabel terpisah — tenant khusus dengan slug `central` (seeded). Titik masuk autentikasi platform (FR-027). Admin platform = user pada central tenant dengan peran platform-admin. FR-026: root `/` → landing page publik.

---

### User

Akun pengguna. Terikat tepat satu tenant (v1). Admin pertama dibuat saat registrasi tenant (FR-014).

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK → tenants.id, not null | BelongsToTenant; central tenant untuk admin platform |
| name | string(255) | not null | |
| email | string(255) | unique (global), not null | FR-015: unik lintas tenant |
| password | string(255) | not null (hashed) | FR-016: min 8, campur huruf+angka |
| role | enum(platform_admin, tenant_admin, member) | not null | FR-024; platform_admin hanya di central tenant |
| status | enum(pending, active, inactive) | default `active` | FR-020: undangan → pending; FR-023 |
| email_verified_at | timestamp | nullable | v1 opsional |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Unique**: `email` global (DB constraint, FR-015).

**Validation rules**:
- Registrasi: email unique global, format email; password min 8 + regex `/^(?=.*[A-Za-z])(?=.*\d).{8,}$/` (FR-016); phone format valid; name required.
- Slug auto-derived dari name; reject jika non-URL-safe atau bentrok (FR-004, FR-005).

**State transitions** (status):
- Registrasi tenant → `active` (admin pertama, FR-017).
- Undangan admin tenant → `pending` → set password → `active` (FR-020).
- Nonaktifkan/hapus keanggotaan → `inactive` / delete (FR-023).
- Tolak nonaktifkan/hapus admin terakhir tenant (FR-025).

**Business rules**:
- Satu email satu tenant di v1 (FR-015, assumption).
- Hapus user → data buatan user tetap milik tenant (assumption edge case).

**Relationships**: belongsTo `Tenant`. Audit log sebagai causer via spatie Activity (morph relation `causer_id`/`causer_type`).

---

### Invitation (undangan anggota)

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK → tenants.id, not null | |
| email | string(255) | not null | Email diundang |
| role | enum(tenant_admin, member) | default `member` | FR-024 |
| token | string(64) | unique, not null | Untuk accept URL |
| expires_at | timestamp | not null | Masa kedaluwarsa (edge case) |
| status | enum(pending, accepted, cancelled, expired) | default `pending` | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Business rules**:
- Tolak undangan email yang sudah user aktif di tenant sama (FR-022).
- Admin tenant bisa batalkan (edge case).
- Saat accept → buat `User` status `active` (atau `pending` set password) + invitation `accepted`.

**Relationships**: belongsTo `Tenant`.

---

### AuditLog (via spatie/laravel-activitylog)

Catatan aksi kritis (FR-028). Implementasi pakai `spatie/laravel-activitylog` (user input eksplisit) dengan custom Activity model + custom table name `audit_logs`.

Custom table name via model `App\Models\Activity extends Spatie\Activitylog\Models\Activity` (`protected $table = 'audit_logs'`) + register di `config/activitylog.php` → `activity_model`. Migration dari spatie (`vendor:publish --tag=activitylog-migrations`) dipakai apa adanya — ponytail: tidak tambah kolom `tenant_id`/`actor_id`, simpan di field native spatie.

Mapping field spatie → kebutuhan audit:

| Field spatie | Isi | Catatan |
|-------------|-----|---------|
| id | PK auto | Default spatie |
| log_name | string, nullable | Namespace aksi, mis. `tenant`, `auth`, `user` |
| description | string | Action code: `tenant.registered`, `user.login`, `user.invited`, `user.removed`, `user.role_changed`, `tenant.status_changed` |
| subject_id / subject_type | morph, nullable | Model target (mis. Tenant, User) — `performedOn($model)` |
| causer_id / causer_type | morph, nullable | Actor (User) — `causedBy($user)`. Nullable: aksi anonim (registrasi publik) |
| properties | json | Context aksi: `tenant_id`, slug, email target, status lama/baru, ip_address |
| created_at | timestamp | Default spatie |
| updated_at | timestamp | Default spatie |

`tenant_id` disimpan di `properties->tenant_id` (bukan kolom terpisah — ponytail: native spatie field cukup). Query audit per tenant: `Activity::where('properties->tenant_id', $tenantId)`.

**Logged actions** (FR-028): registrasi tenant, login, manajemen user (undang/hapus/ubah peran), ubah status tenant. Aksi dicatat via wrapper `LogAuditAction` yang bungkus `activity()->performedOn()->causedBy()->withProperties()->log()`.

---

### Tenant-Scopeable Data (contoh: Patient)

Template untuk data bisnis lain. Semua punya pola sama: `tenant_id` + `BelongsToTenant` + `TenantScope`.

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK → tenants.id, not null | BelongsToTenant |
| name | string(255) | not null | Contoh field bisnis |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Isolasi**: `TenantScope` filter `WHERE tenant_id = app('tenant')->id`. Akses ID lintas tenant → dianggap tidak ada (FR-012).

---

### Global Data (FR-008)

Data master bersama (mis. daftar spesialis bawaan) TIDAK punya `tenant_id`, TIDAK pakai `BelongsToTenant`. Bisa diakses dari semua konteks tenant. Tandai dengan trait `IsGlobal` atau model tanpa `TenantScope`.

## Validation Rules Summary (registrasi tenant — FR-013/016)

```
name:    required|string|max:255
phone:   required|string|max:50|phone_format   // lokal/internasional
email:   required|email|max:255|unique:users,email
password:required|string|min:8|regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/
slug:    auto-derived dari name; reject non-URL-safe; unique:tenants,slug
```

## Index Strategi (ponytail: DB constraint over app code)

- `tenants.slug` UNIQUE INDEX.
- `users.email` UNIQUE INDEX (global).
- `users.tenant_id` INDEX (filter per tenant).
- `audit_logs` (tabel spatie default `activity_log` di-rename via custom model): index `properties->tenant_id` (JSON path index bila DB dukung; ponytail: query `where properties->tenant_id` cukup di dev, add index saat skala SC-008 terbukti lambat).
- `invitations.(tenant_id, email)` INDEX.
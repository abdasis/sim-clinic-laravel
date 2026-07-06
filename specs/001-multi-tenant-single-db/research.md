# Research: Multi-Tenant Single Database

**Branch**: `001-multi-tenant-single-db` | **Date**: 2026-07-06 | **Spec**: [spec.md](./spec.md)

## Unknowns dari Technical Context

### 1. Pola identifikasi tenant via path URL

**Decision**: Route group dengan prefix `{tenant}` + middleware `ResolveTenant` yang inject tenant ke `app('tenant')`. Root `/` di-route terpisah ke landing page.

**Rationale**: Laravel 13 dukung `Route::prefix('{tenant}')->group(...)` dengan route parameter binding. Middleware resolve tenant sekali per request, simpan di container, query global scope baca dari sana. Cocok dengan FR-002 (path-based, no subdomain) dan FR-010 (konteks konsisten per request).

**Alternatives**:
- Subdomain identification — ditolak user eksplisit (spec).
- Tenancy/tenancy package (stancl/tenancy) — overkill; single-db + path-based cukup dengaun native. Ponytail: jangan add dep baru untuk yang native Laravel jamin.

**Sumber**: Laravel 13 docs — Route Groups > Route Prefixes; Route Parameters.

---

### 2. Pola isolasi data antar tenant

**Decision**: Global scope Eloquent + trait `BelongsToTenant`. Setiap model tenant-scopeable punya kolom `tenant_id`. Global scope otomatis filter `WHERE tenant_id = app('tenant')->id`. Pakai `#[ScopedBy([TenantScope::class])]` attribute (Laravel 13).

**Rationale**: Global scope otomatis berlaku di semua query (FR-003, FR-012). `BelongsToTenant` trait isi `tenant_id` saat create dari konteks aktif. Mencegah lupa filter. Resource lintas tenant via ID langsung → dianggap tidak ada (FR-012) karena scope filter.

**Alternatives**:
- Query manual `where('tenant_id', ...)` di setiap controller — rawan lupa, violates FR-003 otomatis.
- Database per tenant — ditolak (spec: single shared DB).

**Sumber**: Laravel 13 docs — Eloquent > Global Scopes (`addGlobalScope`, `ScopedBy` attribute, `booted`).

---

### 3. Auth multi-tenant (Sanctum vs session)

**Decision**: Sanctum SPA auth (sudah di composer: `laravel/sanctum ^4.0`). Token/user terikat ke tenant via `users.tenant_id`. Middleware `ResolveTenant` + `auth:sanctum` berjalan bersama. Login central tenant (FR-027) → resolve tenant dari user, redirect ke `/{slug}/...`.

**Rationale**: Sanctum sudah terinstall, cocok untuk SPA React. Session-based alternatif tapi Sanctum lebih bersih untuk API + web. Email unik lintas tenant (FR-015) → satu user satu tenant di v1.

**Alternatives**:
- Session-only web auth — viable, tapi Sanctum lebih fleksibel untuk API token nanti.
- JWT — tambahan dep, tidak perlu.

---

### 4. Stack database

**Decision**: Default config `sqlite` tapi spec minta shared single DB produksi. Gunakan PostgreSQL (atau MySQL) di produksi; SQLite cukup untuk test/dev. Migrasi semua tabel tenant-scopeable punya `tenant_id` FK ke `tenants`. Unique constraint `tenants.slug` dan `users.email` global.

**Rationale**: Spec asumsi "shared single database". SQLite default Laravel 13 — cukup dev. Produksi Postgres/MySQL dipilih saat deploy, di luar scope plan. Constraint unik di DB level (ponytail: DB constraint over app code) jadi pertahanan terakhir isolasi.

---

### 5. Frontend: datatable, hugeicons, reusable form

**Decision**:
- **Datatable**: pakai `apps/web/src/components/datatable/` yang sudah ada (6 file: `datatable.tsx`, `datatable-column-header.tsx`, `datatable-toolbar.tsx`, `datatable-pagination.tsx`, `datatable-faceted-filter.tsx`, `datatable-view-options.tsx`). Berbasis `@tanstack/react-table ^8.21.3` sudah terinstall. Halaman tenant list, user list pakai ini.
- **Icons**: install `@hugeicons/react` + `@hugeicons/core-free-icons`. Pakai `HugeiconsIcon icon={XxxIcon} size={22} color="currentColor"`. Hapus `lucide-react` usage bertahap.
- **Reusable form**: buat `apps/web/src/components/forms/` dengan komponen base (field, input, select, submit) di atas `react-hook-form ^7.81.0` + `zod ^4.4.3` + `@hookform/resolvers`. Form registrasi tenant, form undang user, form manajemen tenant pakai base ini.

**Rationale**: User input eksplisit minta ketiganya. Datatable sudah ada → reuse (ponytail rung 2: sudah di codebase). Hugeicons wajib install. Form reusable mengikuti CLAUDE.md (modal ≤5 field, page >5 field) dan i18n wajib `t()` dari `lang/id/*.php` via HandleInertiaRequests share. react-hook-form + zod sudah terinstall.

**Alternatives**:
- Pakai `lucide-react` (sudah ada) — ditolak user eksplisit.
- Form library lain (formik) — tidak perlu, RHF sudah ada.

**Sumber**: context7 `/hugeicons/hugeicons-react` — `bun add @hugeicons/react @hugeicons/core-free-icons`, import `HugeiconsIcon` dari `@hugeicons/react` + icon dari `@hugeicons/core-free-icons`.

---

### 6. Audit log

**Decision**: Pakai `spatie/laravel-activitylog` (user input eksplisit). Custom Activity model `App\Models\Activity extends Spatie\Activitylog\Models\Activity` dengan `protected $table = 'audit_logs'` + register di `config/activitylog.php`. Migration dari spatie (`vendor:publish --tag=activitylog-migrations`) dipakai apa adanya — ponytail: tidak tambah kolom custom, `tenant_id` disimpan di `properties` JSON. Wrapper `LogAuditAction` bungkus `activity()->performedOn()->causedBy()->withProperties(['tenant_id'=>...])->log($action)`.

**Rationale**: User minta spatie eksplisit. Spatie sediakan tabel `activity_log` default + helper `activity()` + morph subject/causer + JSON properties — cocok semua kebutuhan FR-028 (action code, actor, tenant, context). Custom table name `audit_logs` lewat extended model (spatie docs: custom table via `$table` + config). `tenant_id` di `properties` bukan kolom — ponytail: native spatie field cukup, query `where('properties->tenant_id', $id)`.

**Alternatives**:
- Tabel `audit_logs` custom murni — lebih banyak kode, tidak pakai spatie (ditolak user eksplisit).
- Tambah kolom `tenant_id`/`actor_id` ke migration spatie — overkill; `properties` JSON + morph `causer` sudah cukup.

**Sumber**: context7 `/spatie/laravel-activitylog` — install, custom table name via extended Activity model, `activity()->causedBy()->withProperties()->log()`.

---

### 7. Central tenant & landing page

**Decision**: Root `/` → landing page publik. Tombol login header → route central tenant (`/central/login` atau tenant khusus `slug=central`). Central tenant adalah konteks autentikasi platform. Admin platform (FR-006) login via central, lihat daftar semua tenant.

**Rationale**: FR-026, FR-027. Central tenant = titik masuk autentikasi. Bukan tenant klinik biasa.

---

## Ringkasan Dependency Baru

Backend:
- `spatie/laravel-activitylog` (audit log, user input eksplisit)

Frontend:
- `@hugeicons/react`
- `@hugeicons/core-free-icons`

Selain itu native Laravel 13: Sanctum, Eloquent, route group, global scope.

## Catatan Ponytail

- Tidak add package tenancy/stancl — native Laravel global scope cukup.
- Tidak buat interface/repository abstraction untuk tenant — Service class cukup saat logika kompleks.
- DB constraint unik (slug, email) sebagai pertahanan isolasi, bukan app-level check saja.
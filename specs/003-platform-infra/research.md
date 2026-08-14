# Research — Platform Infrastructure: Tenants & Audit Log

**Spec**: [spec.md](./spec.md) | **Date**: 2026-08-14

Resolusi unknowns (D1–D4) dari Technical Context + best practice integrasi. Sumber: Context7 (`spatie/laravel-activitylog`), `docs/erd/{tenants,audit_logs}.md`, `docs/normalization/README.md`, eksplorasi codebase existing.

---

## D1 — Strategi migrasi native audit_logs → spatie (tanpa kehilangan data)

**Decision**: Migrate-in-place. Table `audit_logs` sudah ada (migration `2026_07_06_000003`). Spatie menyediakan migration defaultnya; karena kita pakai custom model `App\Models\Activity` dengan `$table = 'audit_logs'`, kita **adaptasi** schema existing ke field spatie daripada buat table baru. Drop kolom native yang tidak dipakai spatie, tambah kolom yang spatie butuhkan, pertahankan baris eksisting bila ada.

**Rationale**: Data audit eksisting sedikit (dev/MVP). Spatie schema standar: `id, log_name (nullable), description, subject_id (nullable), subject_type (nullable), causer_id (nullable), causer_type (nullable), properties (json nullable), created_at, updated_at`. Native saat ini: `id, tenant_id, action, subject_type, subject_id, causer_id, properties, timestamps`. Mapping: `action` → `description` (rename), `causer_id` → tetap + tambah `causer_type` (morph), `tenant_id` → pindah ke `properties->tenant_id` (drop kolom). `LogAuditAction` jadi penghubung — semua caller tidak berubah signature-nya.

**Alternatives considered**:
- *Drop & recreate table*: dibuang — kehilangan audit eksisting + migration history jadi kotor.
- *Dual-table (native + spatie paralel)*: dibuang — melanggar DRY + YAGNI; tujuan `docs/erd/audit_logs.md` eksplisit menyatukan ke spatie.
- *Custom table name `audit_logs` lewat config*: dipakai — spatie mendukung override table via custom model `$table` (Context7 konfirmasi), tidak perlu mengubah default package.

---

## D2 — Adaptasi migration `audit_logs` agar kompatibel spatie

**Decision**: Buat **migration baru** (`2026_08_14_*_migrate_audit_logs_to_spatie.php`) yang mengubah schema table `audit_logs` existing menjadi schema spatie, **bukan** publish migration spatie default (yang akan mencoba create table `activity_log` dan bentrok). Langkah migration:
1. `renameColumn('action', 'description')` — spatie pakai `description` untuk action code.
2. `addColumn('log_name', string, nullable)->after('description')` — namespace modul (`tenant`, `auth`, `user`, `tenant`).
3. `addColumn('causer_type', string, nullable)->after('causer_id')` — morph causer (native `causer_id` FK→users; spatie morph `causer_id`+`causer_type`).
4. Backfill `causer_type = 'App\Models\User'` untuk baris eksisting yang `causer_id` tidak null.
5. `dropForeign` pada `causer_id` (native FK→users `nullOnDelete`) + pastikan `causer_id` nullable — spatie morph tidak pakai FK (audit immutable; user dihapus tidak boleh memutus audit).
6. `dropColumn('tenant_id')` — pindah ke `properties->tenant_id`. Backfill dulu: set `properties = json_set(properties, '$.tenant_id', tenant_id)` untuk baris eksisting (PostgreSQL `jsonb_set` / raw), baru drop kolom + composite index `[tenant_id, action]`.
7. `dropColumn` composite index `[tenant_id, action]` (tidak relevan lagi).

Custom model `App\Models\Activity extends Spatie\Activitylog\Models\Activity` dengan `protected $table = 'audit_logs'`. Config `config/activitylog.php`: `'activity_model' => App\Models\Activity::class`. Tidak perlu `--tag="activitylog-migrations"` (kita adaptasi existing, bukan pakai migration default).

**Rationale**: Migration default spatie create `activity_log` table — kita sudah punya `audit_logs` dan mau reuse nama itu. Adaptasi via migration tunggal lebih bersih, menjaga riwayat migration, dan sesuai target `docs/erd/audit_logs.md` (custom table name `audit_logs`). `ponytail: index JSON path properties->tenant_id add saat lambat`.

**Alternatives considered**:
- *Publish spatie migration default + rename table*: dibuang — dua migration bertabrakan + nama table lepas dari konvensi.
- *Pertahankan kolom `tenant_id` eksplisit*: dibuang — `docs/erd/audit_logs.md` eksplisit `tenant_id` di `properties->tenant_id`, bukan kolom.

---

## D3 — Slug URL-safe + reserved `central`

**Decision**: Pertahankan `Str::slug(company_name)` di `TenantRegistrationService` (sudah ada). Tambah **hardening**: reject eksplisit bila `Str::slug` menghasilkan string kosong (sudah ada: abort 422) **dan** reject slug reserved `central` (saat ini hanya cek duplikat via `Tenant::where('slug', ...)`; karena central seed memakai `central`, duplikat-check akan menolak `central` secara tidak langsung — tapi jadikan eksplisit dengan konstanta reserved list agar jelas dan tahan bila seed central belum jalan). Tidak perlu validasi regex tambahan — `Str::slug` sudah menjamin URL-safe (lowercase, hapus karakter non-alfanumerik, ganti spasi `-`).

**Rationale**: `Str::slug` = native Laravel, memenuhi URL-safe tanpa kode custom (YAGNI rung 3). Reserved-list eksplisit lebih jelas daripada andalkan duplikat-check tak kasat mata. Validasi utama tetap di `RegisterTenantRequest` + service.

**Alternatives considered**:
- *Regex custom untuk URL-safe*: dibuang — `Str::slug` sudah cukup, regex = duplikasi.
- *Slug input manual oleh user*: dibuang — spec FR-003 menurunkan slug dari nama otomatis.

---

## D4 — FE platform-admin guard

**Decision**: Tambah `hasPlatformRole()` di `src/lib/auth.ts` (cek `user.role === 'platform_admin'`). Guard ringan di `central/route.tsx` atau `central/index.tsx`: bila `!hasPlatformRole()` → redirect ke `/central/login` (atau tampilkan empty state). Konsisten dengan pola auth client-side localStorage saat ini (tidak ada beforeLoad server guard). Tidak buat middleware BE baru — enforce `assertPlatformAdmin()` per-controller sudah ada dan cukup.

**Rationale**: FE saat ini hanya `hasClinicRole` (clinic nav). Dashboard central butuh gate agar non-platform-admin tidak melihatnya. Pola localStorage sudah jadi konvensi project (`ponytail:` di `__root.tsx` mencatat swap ke route flag bila admin berkembang). Tambah middleware BE = over-engineering untuk MVP (YAGNI).

**Alternatives considered**:
- *TanStack Router `beforeLoad` server-side guard*: dibuang untuk MVP — auth saat ini client-side; beralih sebagian menciptakan inkonsistensi. `ponytail: add saat SSR auth dipasang penuh`.
- *Middleware BE `platform_admin`*: dibuang — `assertPlatformAdmin()` per-controller sudah enforce; middleware = lapisan ganda yang tidak memberi nilai untuk MVP.

---

## Best practices: spatie/laravel-activitylog (Context7)

- Custom model: `class Activity extends BaseActivity { protected $table = 'audit_logs'; }` + config `'activity_model' => App\Models\Activity::class`.
- API: `activity()->causedBy($user)->performedOn($model)->withProperties(['tenant_id' => $id, 'ip_address' => $ip])->log('user.login')`.
- Query per tenant: `Activity::where('properties->tenant_id', $tenantId)->get()`.
- `causedBy(null)`/omit → causer nullable (aksi anonim seperti registrasi publik).
- `subject`/`causer` morphTo — resolve null bila target dihapus (audit tidak rusak).
- Tidak pakai model-event auto-log (`LogsActivity` trait) untuk MVP — `LogAuditAction` wrapper manual cukup (YAGNI). `ponytail: enable LogsActivity saat auto-log model event dibutuhkan`.

## Best practices: FE (radix-nova, Linear-style)

- Dashboard central: `ClinicBreadcrumb` (root→Dashboard), heading, ringkasan tenant via `DataTable` existing atau stat cards sederhana. Density tinggi, border subtle, shadow tipis.
- Semua interaktif pakai `Tooltip` + shortcut (aturan CLAUDE.md).
- Teks via `t()` (i18n), key English, nilai Indonesia semi-formal.
- Komponen ≤300 baris; ekstrak ke `components/` bila melebihi.
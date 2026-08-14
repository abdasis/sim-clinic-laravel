# Quickstart — Platform Infrastructure: Tenants & Audit Log

**Spec**: [spec.md](./spec.md) | **Date**: 2026-08-14
**Reference**: [data-model.md](./data-model.md) · [api-contracts.md](./contracts/api-contracts.md) · [docs/erd/tenants.md](../../docs/erd/tenants.md) · [docs/erd/audit_logs.md](../../docs/erd/audit_logs.md)

Skenario validasi end-to-end yang membuktikan fitur berjalan. Jalankan dari repo root.

## Prerequisites

```bash
docker compose up -d db                    # PostgreSQL port 5435
cd apps/api && cp .env.example .env && php artisan key:generate && php artisan migrate
cd apps/api && php artisan db:seed         # CentralTenantSeeder + TenantAdminSeeder + ClinicDemoSeeder
# Terminal 1
cd apps/api && php artisan serve           # port 8000
# Terminal 2
cd apps/web && bun install && bun run dev  # port 3001
```

Kredensial seed: platform admin `admin@platform.test` / `password123` (central tenant slug `central`).

## Validation 1 — Registrasi tenant baru (Langkah 1)

**Tujuan**: FR-002/003/004/005 — buat tenant + admin, slug URL-safe unik, audit tercatat.

```bash
curl -s -X POST http://localhost:8000/api/register \
  -H 'Content-Type: application/json' \
  -d '{"company_name":"Klinik Cantik","phone":"08120000","email":"admin@klinik-cantik.test","password":"Password123"}'
```

**Expected**: 201, `data.tenant.slug = "klinik-cantik"`, `data.user.role = "tenant_admin"`, `meta.redirect_to = "/klinik-cantik/login"`.

**Negative — slug duplikat/reserved**:
```bash
# company_name yang menghasilkan slug "central" → reject
curl -s -X POST http://localhost:8000/api/register -H 'Content-Type: application/json' \
  -d '{"company_name":"Central","phone":"08","email":"x@y.test","password":"Password123"}'
# Expected: 422 (slug reserved / duplikat)
```

**Audit tercatat**: di `apps/api`, `php artisan tinker`:
```php
\App\Models\Activity::where('description','tenant.registered')->latest()->first();
// properties->tenant_id = id tenant baru; causer = null (anonim)
```

## Validation 2 — Login central + dashboard (Langkah 1)

**Tujuan**: FR-001/008/010 — admin platform login, lihat dashboard central + breadcrumb.

1. Buka `http://localhost:3001/central/login`.
2. Login `admin@platform.test` / `password123`.
3. **Expected**: redirect ke `/central` (dashboard). Breadcrumb tampil: Central → Dashboard (item terakhir non-link). Nav sidebar ada item Dashboard + Tenants.
4. **Guard**: logout, login sebagai tenant_admin (via `/klinik-cantik/login`) lalu navigasi manual ke `/central` → **Expected**: redirect ke `/central/login` (bukan platform_admin).

**Audit**: `\App\Models\Activity::where('description','user.login')->latest()->first()->properties->tenant_id` = id central.

## Validation 3 — Toggle status tenant (Langkah 1)

**Tujuan**: FR-006/007/009 — nonaktifkan tenant, akses diblokir, audit tercatat.

```bash
# login central dapatkan token
TOKEN=$(curl -s -X POST http://localhost:8000/api/central/login -H 'Content-Type: application/json' \
  -d '{"email":"admin@platform.test","password":"password123"}' | jq -r .data.token)

# nonaktifkan tenant klinik-cantik (ganti {id})
curl -s -X PATCH http://localhost:8000/api/central/tenants/2/status \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"status":"inactive"}'
# Expected: 200, data.status = "inactive"

# akses tenant inactive → ditolak
curl -s -X POST http://localhost:8000/api/klinik-cantik/login \
  -H 'Content-Type: application/json' -d '{"email":"admin@klinik-cantik.test","password":"Password123"}'
# Expected: 423 (EnsureTenantActive)
```

**Audit**: `\App\Models\Activity::where('description','tenant.status_changed')->latest()->first()` → `properties.old_status = "active"`, `properties.new_status = "inactive"`.

## Validation 4 — Audit log immutable + morph (Langkah 2)

**Tujuan**: FR-015/016/017 — query per tenant via `properties->tenant_id`, audit tidak rusak saat subject dihapus, causer nullable.

```php
// php artisan tinker
use App\Models\Activity;

// query per tenant
Activity::where('properties->tenant_id', 2)->count();   // semua aksi tenant klinik-cantik

// aksi anonim (tenant.registered) → causer null
Activity::where('description','tenant.registered')->first()->causer; // null (tidak error)

// immutability + morph: hapus subject, audit tetap ada
$t = App\Models\Tenant::find(2);
$id = $t->id;
Activity::where('subject_type','App\Models\Tenant')->where('subject_id',$id)->count(); // N
// (penghapusan permanen tenant di luar scope v1 — verifikasi via hard-delete simulasi di test)
// setelah subject hilang: $activity->subject === null (resolve morph), record tetap ada
```

## Validation 5 — Audit log infra reusable (Langkah 2)

**Tujuan**: FR-013/018 — `LogAuditAction` sebagai single point, semua caller tetap jalan post-migrasi.

Jalankan suite test:
```bash
cd apps/api && php artisan test --filter=AuditLog
```
**Expected**: feature test yang memicu aksi (login, registrasi, status toggle) tetap lulus + verifikasi record `Activity` (bukan `AuditLog`) tercipta dengan `properties->tenant_id` terisi. Unit test `LogAuditAction` verifikasi: causer nullable tidak error, subject nullable tidak error, `log_name` diturunkan dari prefix action.

## Notes

- Tidak menjalankan `bun run build` / `composer run dev` (aturan CLAUDE.md) — user jalankan sendiri bila perlu.
- Index JSON path `properties->tenant_id` sengaja belum ditambah (`ponytail: add saat lambat`).
- Penghapusan permanen tenant di luar scope v1 — verifikasi immutability audit via test, bukan operasi manual.
# Quickstart Validation: Multi-Tenant Single Database

**Spec**: [spec.md](./spec.md) | **Contracts**: [contracts/](./contracts/) | **Data Model**: [data-model.md](./data-model.md)

Panduan validasi end-to-end. Tidak duplikasi detail implementasi — lihat kontrak & data model.

## Prerequisites

- PHP 8.3, Composer, Node/Bun
- Laravel 13 app di `apps/api` (`composer install`)
- React app di `apps/web` (`bun install`)
- DB: SQLite (dev default) atau Postgres/MySQL (produksi). Konfigurasi `apps/api/.env` `DB_CONNECTION`.

## Setup

```bash
# Backend
cd apps/api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --force
php artisan db:seed   # seed central tenant + platform admin

# Frontend
cd ../web
bun install
# install hugeicons (user input wajib)
bun add @hugeicons/react @hugeicons/core-free-icons
```

## Run

```bash
# API (apps/api)
php artisan serve   # http://127.0.0.1:8000

# Web (apps/web) — user jalankan sendiri (CLAUDE.md: jangan auto-run dev server)
bun dev
```

## Validation Scenarios

### V1: Registrasi tenant (FR-013/014/017, SC-001)

1. Buka `/` → landing page publik tampil.
2. Form registrasi: isi `company_name="Klinik Sehat"`, `phone="+62812..."`, `email="admin@klinik-sehat.test"`, `password="Pass1234"`.
3. Submit. Expect 201. Tenant `klinik-sehat` aktif, user `tenant_admin` terbuat.
4. Verify DB: `tenants` row + `users` row dengan `role=tenant_admin`.
5. Verify audit log: `audit_logs` action=`tenant.registered`.

Pass: tenant + user terbuat bersama, redirect ke `/{slug}/login`.

### V2: Identifikasi tenant via path (FR-002/007, SC-003/004)

1. Akses `http://127.0.0.1:8000/klinik-sehat/...` → konteks tenant aktif.
2. Akses `/tenant-tidak-ada/...` → 404 dengan pesan identifikasi jelas (FR-007), tidak ekspos internal.

Pass: data hanya tenant aktif; slug invalid ditolak jelas.

### V3: Isolasi data (FR-003/012, SC-002)

1. Daftar 2 tenant (A, B) via V1.
2. Login A, buat data pasien. Switch ke B (login B). Verify: pasien A tidak muncul di B.
3. Dari konteks B, GET `/{B-slug}/patients/{A-pasien-id}` → 404 (dianggap tidak ada, FR-012).

Pass: 0 kebocoran lintas tenant.

### V4: Multi-user per tenant (FR-018/019/020/022/025, SC-007)

1. Admin tenant A undang `member@...` via `POST /{A}/users/invite`. Verify invitation + audit `user.invited`.
2. Undang email yang sudah user aktif di A → 422 (FR-022).
3. Accept invitation via `/invitations/{token}` → set password → login.
4. Coba hapus admin terakhir A → 422 (FR-025).

Pass: undangan, accept, tolak duplikat, proteksi admin terakhir.

### V5: Manajemen tenant (FR-006/009/011, SC-005)

1. Login platform admin via `/central/login`.
2. `GET /central/tenants` → semua tenant tampil (datatable server-side).
3. Nonaktifkan tenant A. Akses `/{A-slug}/...` → ditolak (FR-009). Sesi aktif diakhiri.
4. Reaktifkan → akses normal kembali (FR-011).

Pass: status toggle, sesi diakhiri saat nonaktif, data tidak hilang.

### V6: Tenant dinonaktifkan saat sesi aktif (edge case, FR-009)

1. User A login. Admin platform nonaktifkan A.
2. User A request berikutnya → diakhiri dengan pesan jelas.

### V7: Global data (FR-008)

1. Akses `/{any-tenant}/global/specialties` → master data bersama tampil, sama untuk semua tenant.

## Test Commands

```bash
cd apps/api
php artisan test                    # PHPUnit feature+unit
php -l app/Models/Tenant.php        # syntax check (CLAUDE.md izin)
php artisan tinker                  # manual probe
```

Frontend:
```bash
cd apps/web
bun run test    # vitest
```

## Expected Outcomes (SC mapping)

- SC-001: registrasi < 2 menit (V1).
- SC-002: 0 kebocoran (V3).
- SC-004: slug invalid ditolak jelas (V2).
- SC-005: tenant nonaktif tidak akse dalam 1 request (V5).
- SC-007: undang+login < 3 menit (V4).
- SC-008: 100 tenant × 50 user — load test opsional, di luar MVP.
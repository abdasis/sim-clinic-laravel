# Quickstart — Master Layanan Klinik (005-service-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Contracts**: [services-api.md](contracts/services-api.md)

Panduan validasi end-to-end runnable. Tidak berisi kode implementasi penuh — detail implementasi di `tasks.md` (fase `/speckit-tasks`).

## Prasyarat

- Docker DB jalan: `docker compose up -d db` (PostgreSQL port 5435).
- Backend siap: `apps/api/.env` terkonfigurasi, `php artisan migrate` terbaru.
- Frontend siap: `apps/web` dependency terinstall.
- Seeded: minimal 1 tenant + 1 user admin klinik (clinic_role=admin). Untuk test permission: 1 user doctor + 1 cashier.

## Setup (setelah implementasi)

```bash
cd apps/api && php artisan migrate          # jalankan migration FK restrict
cd apps/api && php artisan test             # seluruh test service
```

Jangan jalankan `php artisan serve` / `bun run dev` otomatis — jalankan sendiri saat ingin validasi manual.

## Skenario validasi

### 1. CRUD + arsip (admin)

1. Login sebagai admin klinik → token.
2. `POST /{tenant}/clinic/services` body `{name:"Facial Glow", price:350000}` → 201, status `active`.
3. `GET /{tenant}/clinic/services` → data berisi "Facial Glow", arsip tidak muncul (default active).
4. `PUT /{tenant}/clinic/services/{id}` body `{price:400000}` → 200, harga berubah.
5. `DELETE /{tenant}/clinic/services/{id}` → 200, status `archived`, meta message "Layanan berhasil diarsipkan."
6. `GET /{tenant}/clinic/services?filter[status]=archived` → data berisi "Facial Glow" dengan `status_label` "Diarsipkan".
7. Cek `audit_logs`: ada 3 row (`service.created`, `service.updated`, `service.archived`), narasi mengandung "Facial Glow".

**Expected**: semua langkah sukses; activity log naratif tercatat.

### 2. Arsip tidak muncul di pilihan booking baru (FR-014)

1. Pastikan ada ≥1 layanan active + 1 layanan archived.
2. Buka FE halaman booking → modal "Tambah Booking".
3. Dropdown layanan hanya berisi layanan active.

**Expected**: layanan archived tidak muncul di dropdown (R3 — index default active).

### 3. Hard-delete direferensi diblokir restrict (FR-015)

1. Buat layanan A (active). Buat booking yang menunjuk layanan A.
2. Tinker/artisan: `Service::find($idA)->delete();` → melempar `QueryException` (FK restrict).
3. Arsipkan layanan A via `DELETE` endpoint → 200 (arsip, bukan hard-delete). Booking tetap ada, `service_id` valid.

**Expected**: hard-delete diblokir DB; arsip diizinkan dan tidak putus relasi.

### 4. Snapshot immutability (FR-016, R5)

1. Buat layanan B (name="Lama", price=100000).
2. Buat transaction item yang menunjuk B → snapshot `name="Lama"`, `unit_price=100000`.
3. Buat treatment record menunjuk B → `service_name="Lama"`.
4. Ubah B: `name="Baru"`, `price=200000`. Arsipkan B.
5. Baca transaction item & treatment record tadi.

**Expected**: `transaction_items.name="Lama"`, `unit_price=100000`, `treatment_records.service_name="Lama"` — tidak berubah walau master diubah/arsip.

### 5. Permission (R8)

1. Login doctor → `GET services` 200, `POST services` 403, `DELETE services/{id}` 403.
2. Login cashier → `GET services` 403, semua write 403.

**Expected**: matriks permission ditegakkan.

### 6. Validasi harga negatif (FR-011)

1. `POST services` body `{name:"X", price:-1}` → 422, error pada field `price`.

**Expected**: ditolak.

### 7. FE halaman master + breadcrumb + row actions

1. Buka `/{tenant}/clinic/services`.
2. Breadcrumb: "Beranda Klinik > Layanan" — "Layanan" item terakhir (bukan link), "Beranda Klinik" link ke `/$tenant/clinic`.
3. Tabel punya kolom aksi per-row: "Ubah" (buka modal edit prefill) + "Arsipkan" (alert confirm).
4. Faceted filter status di toolbar → pilih "Diarsipkan" → tampilkan arsip.

**Expected**: breadcrumb benar (tidak self-link), edit + archive berfungsi, filter status bekerja.

## Referensi

- Kontrak endpoint: [contracts/services-api.md](contracts/services-api.md)
- Struktur data: [data-model.md](data-model.md)
- Keputusan desain: [research.md](research.md)
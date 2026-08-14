# Quickstart — Master Pasien Klinik (006-patient-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Contracts**: [patients-api.md](contracts/patients-api.md)

Panduan validasi end-to-end runnable. Tidak berisi kode implementasi penuh — detail implementasi di `tasks.md` (fase `/speckit-tasks`).

## Prasyarat

- Docker DB jalan: `docker compose up -d db` (PostgreSQL port 5435).
- Backend siap: `apps/api/.env` terkonfigurasi, `php artisan migrate` terbaru (termasuk migration FK restrict + soft delete).
- Frontend siap: `apps/web` dependency terinstall.
- Seeded: minimal 1 tenant + 1 user admin klinik (clinic_role=admin). Untuk test permission: 1 user therapist (view only) + 1 cashier.

## Setup (setelah implementasi)

```bash
cd apps/api && php artisan migrate          # jalankan migration soft delete + FK restrict
cd apps/api && php artisan test             # seluruh test patient
cd apps/web && bun run generate-routes      # regen route tree bila ada file route baru
```

Jangan jalankan `php artisan serve` / `bun run dev` otomatis — jalankan sendiri saat ingin validasi manual.

## Skenario validasi

### 1. CRUD + nonaktifkan (admin)

1. Login sebagai admin klinik → token.
2. `POST /{tenant}/clinic/patients` body `{name:"Siti Aminah", phone:"08123456789", gender:"female", notes:"Alergi penisilin"}` → 201, `deleted_at:null`.
3. `GET /{tenant}/clinic/patients` → data berisi "Siti Aminah".
4. `PUT /{tenant}/clinic/patients/{id}` body `{address:"Jl. Mawar No. 1"}` → 200, alamat berubah.
5. `DELETE /{tenant}/clinic/patients/{id}` → 200, `deleted_at` terisi, meta message "Pasien berhasil dinonaktifkan."
6. `GET /{tenant}/clinic/patients` → "Siti Aminah" tidak muncul (FR-026, soft delete).
7. Cek `audit_logs`: ada 3 row (`patient.created`, `patient.updated`, `patient.deactivated`), narasi mengandung "Siti Aminah".

**Expected**: semua langkah sukses; activity log naratif tercatat.

### 2. Duplikat phone = peringatan, bukan block (FR-021/023)

1. Buat pasien A dengan phone "08123456789" → 201, `duplicate_warning:false`.
2. Buat pasien B dengan phone yang sama "08123456789" → 201 (tidak ditolak), `meta.duplicate_warning:true` + `meta.duplicate_patient_id={id A}`.
3. Update pasien B phone ke "08123450000" → 200, `duplicate_warning:false`.
4. Update pasien B phone kembali ke "08123456789" → 200, `duplicate_warning:true`.

**Expected**: duplikat tidak memblokir; peringatan dikembalikan di store + update.

### 3. Nonaktifkan = riwayat tetap utuh & dapat diakses (FR-022/025/028)

1. Buat pasien C (active). Buat booking yang menunjuk C + rekam medis (via booking done).
2. `DELETE /{tenant}/clinic/patients/{idC}` → 200, nonaktif.
3. `GET /{tenant}/clinic/patients/{idC}/history` → 200, riwayat booking/treatment tetap lengkap (R5 withTrashed).
4. `GET /{tenant}/clinic/patients/{idC}` → 200 (show resolve withTrashed), `deleted_at` terisi.
5. Verifikasi booking/rekam medis/transaksi pasien C di modul masing-masing tetap merujuk C, data utuh.

**Expected**: pasien nonaktif tidak muncul di list aktif, namun riwayat & detail tetap dapat diakses; relasi tidak putus.

### 4. Hard-delete direferensi diblokir restrict (FR-027)

1. Buat pasien D (active). Buat booking yang menunjuk D.
2. Tinker/artisan: `Patient::withTrashed()->find($idD)->forceDelete();` → melempar `QueryException` (FK restrict).
3. Nonaktifkan pasien D via `DELETE` endpoint → 200 (soft delete, bukan hard-delete). Booking tetap ada, `patient_id` valid.

**Expected**: hard-delete diblokir DB; soft delete diizinkan dan tidak putus relasi.

### 5. Permission (R8)

1. Login therapist → `GET patients` 200, `POST patients` 403, `DELETE patients/{id}` 403 (matriks: therapist `patient r`).
2. Login cashier → `GET patients` 200, `POST patients` 201, `DELETE patients/{id}` 200 (matriks: cashier `patient rw`).
3. Login doctor → `GET patients` 200, `POST patients` 201 (matriks: doctor `patient rw`).

**Expected**: matriks permission `ClinicPermission::MATRIX` ditegakkan via `PatientPolicy`.

### 6. Validasi (FR-020)

1. `POST patients` body `{phone:"08123456789"}` (name kosong) → 422, error field `name`.
2. `POST patients` body `{name:"X", phone:""}` → 422, error field `phone`.
3. `POST patients` body `{name:"X", phone:"08", birth_date:"2099-01-01"}` → 422, error field `birth_date` (masa depan).

**Expected**: ditolak dengan pesan validasi.

### 7. FE halaman master + breadcrumb + row actions

1. Buka `/{tenant}/clinic/patients`.
2. Breadcrumb: "Beranda Klinik > Pasien" — "Pasien" item terakhir (bukan link), "Beranda Klinik" link ke `/$tenant/clinic` (tidak self-link).
3. Tabel punya kolom aksi per-row: "Ubah" (navigasi ke halaman edit) + "Nonaktifkan" (alert confirm).
4. Form pasien (halaman new/edit) punya field Catatan (`notes`) — 7 field.
5. Buat pasien dengan phone duplikat via FE → AlertDialog peringatan duplikat muncul.
6. Buka riwayat pasien `/{tenant}/clinic/patients/{id}/history` → breadcrumb "Beranda Klinik > Pasien > {nama pasien} > Riwayat", nama pasien muncul di breadcrumb.
7. Nonaktifkan pasien → tidak muncul di list; halaman riwayatnya tetap dapat dibuka.

**Expected**: breadcrumb benar (tidak self-link), edit + nonaktifkan berfungsi, field notes ada, duplicate warning di create+edit, riwayat pasien nonaktif tetap dapat diakses.

## Referensi

- Kontrak endpoint: [contracts/patients-api.md](contracts/patients-api.md)
- Struktur data: [data-model.md](data-model.md)
- Keputusan desain: [research.md](research.md)
# Quickstart — Rekam Medis SOAP (009-medical-records)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Contracts**: [medical-records-api.md](contracts/medical-records-api.md)

Panduan validasi end-to-end runnable. Tidak berisi kode implementasi penuh — detail implementasi di `tasks.md` (fase `/speckit-tasks`).

## Prasyarat

- Docker DB jalan: `docker compose up -d db` (PostgreSQL port 5435).
- Backend siap: `apps/api/.env` terkonfigurasi, `php artisan migrate` terbaru.
- Frontend siap: `apps/web` dependency terinstall.
- Seeded: minimal 1 tenant + 1 user dokter klinik (clinic_role=doctor/admin) + 1 kasir (clinic_role=cashier) + 1 pasien + 1 layanan aktif + 1 booking berstatus `done` (assignee=dokter).
- DB test PostgreSQL: `docker compose exec db createdb -U postgres sim_clinic_laravel_test` (sekali).

## Setup (setelah implementasi)

```bash
cd apps/api && php artisan migrate          # migration spec 009 (softdelete, index, FK restrict parent+child)
cd apps/api && php artisan test             # seluruh test rekam medis (sqlite)
cd apps/api && php artisan test -c phpunit.pgsql.xml --filter=MedicalRecord   # constraint restrict FK (WAJIB sebelum rilis)
```

Jangan jalankan `php artisan serve` / `bun run dev` otomatis — jalankan sendiri saat ingin validasi manual.

## Skenario validasi

### 1. Isi rekam medis SOAP dari booking done (FR-040/033/088/044, US1)

1. Login sebagai dokter klinik → token.
2. `POST /{tenant}/clinic/medical-records` body `{booking_id:<doneBooking>, subjective:"...", objective:"...", assessment:"...", plan:"..."}` → 201, `patient_id` = pasien booking, `author_id` = dokter, `created_at` terisi.
3. `POST .../medical-records` body `{booking_id:<confirmedBooking>}` → 422 `medical_record.booking_not_done` (booking belum done).
4. `POST .../medical-records` body `{booking_id:<sudahPunyaRecord>}` → 422 `medical_record.already_exists` (R10 duplikat).
5. Login sebagai kasir → `POST .../medical-records` → 403 (FR-044, hanya dokter/terapis/admin).

**Expected**: rekam medis tersimpan hanya dari booking done, unik per booking, hanya role klinis.

### 2. Riwayat rekam medis per pasien (FR-022/096, US2, R3)

1. Buat 3 rekam medis untuk pasien A lewat 3 booking done berbeda.
2. `GET /{tenant}/clinic/patients/{patientA}/medical-records` → 3 record terurut kronologis (created_at), tanpa join ke bookings.
3. `GET .../patients/{patientB}/medical-records` → 0 record (pasien B berbeda).
4. Tenant B `GET /{tenantB}/clinic/patients/{idA}/medical-records` → 0/404 (TenantScope).

**Expected**: riwayat per pasien scoped tenant, kronologis, pakai index `(tenant_id, patient_id, created_at)`.

### 3. Edit SOAP + audit diff (FR-094, US4)

1. Buat rekam medis (subjective "lama").
2. `PATCH /{tenant}/clinic/medical-records/{id}` body `{subjective:"baru"}` → 200, `updated_at` berubah, `subjective` = "baru".
3. Cek `audit_logs`: event `medical_record.updated`, narasi "Memperbarui rekam medis pasien {patient}", properties old.subjective="lama"/new.subjective="baru".
4. `PATCH .../medical-records/{id}` body `{booking_id:99}` → di-accept/tolak booking_id (immutable — tidak mengubah patient_id/booking).

**Expected**: update SOAP tersimpan, audit diff old/new, field immutable tidak berubah.

### 4. Soft-delete + integritas child + restrict FK (FR-090/091/092/093, US3, R1/R2)

1. Buat rekam medis M dengan treatment record + foto.
2. `DELETE /{tenant}/clinic/medical-records/{M}` → 200, `deleted_at` terisi, meta "Rekam medis berhasil dihapus."
3. `GET .../medical-records` → M tidak muncul (soft-deleted exclude). `GET .../medical-records/{M}` → 404.
4. Tinker: cek DB → M masih ada (`deleted_at` not null), treatment record + foto tetap utuh (FR-091).
5. Tinker: hard-delete M (`MedicalRecord::withTrashed()->find($M)->forceDelete()`) → `QueryException` (FK restrict `medical_record_id` child — FR-092).
6. Tinker: hapus booking yang direferensi M → `QueryException` (FK restrict `booking_id` — FR-093).
7. Tinker: hapus pasien yang direferensi M → `QueryException` (FK restrict `patient_id` — FR-093).

**Expected**: soft-delete diizinkan, child tetap utuh; hard-delete + hapus parent direferensi diblokir restrict (pgsql test).

### 5. Audit naratif create (FR-094, R5, US4)

1. Buat rekam medis baru untuk pasien A.
2. Cek `audit_logs`: event `medical_record.created`, narasi "Mengisi rekam medis pasien {A}", properties full SOAP + booking_id + patient_id.
3. Soft-delete → event `medical_record.deleted`, narasi "Menghapus rekam medis pasien {A}".

**Expected**: narasi sesuai spec "Mengisi rekam medis pasien {patient}"; semua aksi ubah-data tercatat.

### 6. Immutability patient_id booking (R4, anomali #2)

1. Buat rekam medis untuk booking pasien A.
2. `PATCH /{tenant}/clinic/bookings/{bookingId}` body `{patient_id:<patientB>}` → 422 (booking immutable bila record ada — sudah ada di booking side).
3. Cek rekam medis → `patient_id` tetap pasien A (tidak drift).

**Expected**: booking patient_id immutable setelah record ada; rekam medis tidak drift (verifikasi, bukan implementasi ulang).

### 7. Tenant isolation (konstitusi III)

1. Tenant A buat rekam medis. Tenant B `GET /{tenantB}/clinic/medical-records` → tidak ada record A.
2. Tenant B `GET .../medical-records/{idA}` → 404 (TenantScope).

**Expected**: tidak ada bocor data lintas tenant.

### 8. FE form rekam medis + riwayat + breadcrumb (FR-097, US1/US2/US4)

1. Buka `/{tenant}/clinic/medical-records/new?booking=<doneBookingId>` (dari booking done).
2. Breadcrumb: "Beranda Klinik > Rekam Medis > Isi" — item terakhir non-link, "Beranda Klinik" link ke `/$tenant/clinic`.
3. Form: 4 `FormTextarea` (Subjektif/Objektif/Assessment/Plan) + `FormSubmit`, validasi `useForm`/zod (nullable string, draf boleh kosong).
4. Submit → rekam medis tersimpan, redirect ke detail.
5. Buka `/{tenant}/clinic/medical-records` → tabel `DataTable` (search patient_name, pagination, kolom tanggal/dokter/ringkasan SOAP), breadcrumb "Beranda Klinik > Rekam Medis".
6. Buka `/{tenant}/clinic/patients/{patientId}/medical-records` → riwayat kronologis per pasien, breadcrumb "Beranda Klinik > Pasien > {Pasien} > Rekam Medis".
7. Edit `/{tenant}/clinic/medical-records/{recordId}` → form SOAP prefill, update + breadcrumb "Beranda Klinik > Rekam Medis > {Ringkasan}".

**Expected**: form SOAP 4 textarea inline (reuse `components/forms/`), DataTable riwayat (reuse `components/datatable/`), breadcrumb benar (reuse `ClinicBreadcrumb`); 0 komponen baru.

## Referensi

- Kontrak endpoint: [contracts/medical-records-api.md](contracts/medical-records-api.md)
- Struktur data: [data-model.md](data-model.md)
- Keputusan desain: [research.md](research.md)
# Quickstart — Booking & Jadwal Klinik (008-booking-schedule)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Contracts**: [bookings-api.md](contracts/bookings-api.md)

Panduan validasi end-to-end runnable. Tidak berisi kode implementasi penuh — detail implementasi di `tasks.md` (fase `/speckit-tasks`).

## Prasyarat

- Docker DB jalan: `docker compose up -d db` (PostgreSQL port 5435).
- Backend siap: `apps/api/.env` terkonfigurasi, `php artisan migrate` terbaru.
- Frontend siap: `apps/web` dependency terinstall.
- Seeded: minimal 1 tenant + 1 user admin klinik (clinic_role=admin). Untuk test permission: 1 user doctor + 1 cashier. Untuk immutability: 1 booking berstatus `done` + rekam medis terisi.

## Setup (setelah implementasi)

```bash
cd apps/api && php artisan migrate          # jalankan migration FK restrict patient/service
cd apps/api && php artisan test             # seluruh test booking
```

Jangan jalankan `php artisan serve` / `bun run dev` otomatis — jalankan sendiri saat ingin validasi manual.

## Skenario validasi

### 1. CRUD booking + overlap warning (FR-030, FR-035)

1. Login sebagai admin klinik → token.
2. `POST /{tenant}/clinic/bookings` body `{patient_id, service_id, assignee_id, start_at:"2026-08-15T10:00", end_at:"2026-08-15T11:00"}` → 201, status `pending`, `meta.overlap_warnings` = [].
3. `POST /{tenant}/clinic/bookings` body assignee sama, `start_at:"2026-08-15T10:30", end_at:"2026-08-15T11:30"` → 201 (tidak diblokir), `meta.overlap_warnings` berisi 1 item (booking pertama).
4. `GET /{tenant}/clinic/bookings` → data berisi 2 booking, urut `start_at desc`.
5. `PUT /{tenant}/clinic/bookings/{id}` body `{notes:"pasien telat"}` → 200, notes berubah.

**Expected**: create sukses, overlap hanya warning (tidak block), list & update bekerja.

### 2. State transition enforced (FR-031)

1. Buat booking (status `pending`).
2. `PATCH /{tenant}/clinic/bookings/{id}/status` body `{status:"confirmed"}` → 200, status `confirmed`, `status_changed_at` terisi.
3. `PATCH .../status` body `{status:"done"}` → 200, status `done`.
4. `PATCH .../status` body `{status:"cancelled"}` → 422 `clinic.invalid_transition` (done tidak → cancelled).
5. Booking lain status `pending` → `PATCH {status:"cancelled"}` → 200. Lalu `PATCH {status:"confirmed"}` → 422 (cancelled final).
6. Cek `audit_logs`: ada row `booking.status_changed` dengan narasi "Status booking {pasien} diubah dari '{lama}' ke '{baru}'." dan properties `old`/`new` status.

**Expected**: transisi ilegal ditolak; transisi valid sukses; audit log naratif lama→baru.

### 3. Immutability patient_id saat rekam medis ada (FR-037, Anomali #2)

1. Pastikan ada booking berstatus `done` dengan rekam medis (via flow medical record atau seeder).
2. `GET /{tenant}/clinic/bookings/{id}` → `data.has_medical_record` = true.
3. `PUT /{tenant}/clinic/bookings/{id}` body `{patient_id: <id pasien lain>}` → 422, error pada field `patient_id` "Pasien tidak dapat diubah karena rekam medis sudah ada."
4. `PUT /{tenant}/clinic/bookings/{id}` body `{start_at, end_at}` (ubah jadwal, tidak sentuh patient_id) → 200 sukses.
5. Booking tanpa rekam medis: `PUT` body `{patient_id: <lain>}` → 200 sukses (pasien boleh diubah).

**Expected**: patient_id immutable bila rekam medis ada (422); field lain tetap bisa diubah; booking tanpa rekam medis bebas ubah pasien.

### 4. FK restrictOnDelete (FR-038)

1. Buat booking yang menunjuk pasien A + layanan B + dokter C.
2. Tinker/artisan: `Patient::find($idA)->delete();` → melempar `QueryException` (FK restrict). Sama untuk `Service::find($idB)->delete()` (restrict). `assignee` sudah restrict (migration 031000).
3. Nonaktifkan pasien A (soft, spec 006) / arsipkan layanan B (spec 005) → booking tetap ada, FK valid.

**Expected**: hard-delete parent yang direferensi booking diblokir DB (PostgreSQL); nonaktif/arsip tidak putus relasi.
**Catatan SQLite**: migration restrict skip SQLite (test :memory:) — verifikasi DB-level restrict pada PostgreSQL (R7).

### 5. Jadwal harian/mingguan (FR-032)

1. `GET /{tenant}/clinic/bookings/schedule?from=2026-08-15&to=2026-08-15&view=day` → data booking hari itu (status ≠ cancelled), dipetakan assignee.
2. `GET .../schedule?from=2026-08-11&to=2026-08-17&view=week` → data booking minggu itu.
3. Buka FE `/{tenant}/clinic/bookings` → ScheduleGrid tampil, toggle Harian/Mingguan, pilih tanggal.

**Expected**: jadwal mengembalikan booking rentang dipilih; FE tampil grid per assignee (harian) / per hari (mingguan).

### 6. FE form booking create + edit + disable pasien (AC FE)

1. Buka `/{tenant}/clinic/bookings` → klik "Tambah Booking" → modal form (pasien, layanan, assignee, start, end, notes).
2. Isi & simpan → toast sukses + (bila bentrokan) toast warning overlap; jadwal ter-update.
3. Klik aksi "Ubah" pada booking (row/menu) → modal edit prefill nilai; simpan → sukses.
4. Klik "Ubah" pada booking yang `has_medical_record=true` → field Pasien **disabled** + note "Pasien terkunci karena rekam medis sudah ada."; field lain bisa diubah.
5. Breadcrumb: "Beranda Klinik > Booking" — "Booking" item terakhir (non-link), "Beranda Klinik" link ke `/$tenant/clinic`.

**Expected**: form create+edit reuse; field pasien disabled bila rekam medis ada (UX mencegah 422); breadcrumb benar (tidak self-link).

### 7. Permission (R10)

1. Login doctor → `GET bookings` 200, `POST bookings` 200, `PATCH status` 200 (doctor rw).
2. Login therapist → `GET`/`POST`/`PATCH` 200 (therapist rw).
3. Login cashier → `GET bookings` 403, semua write 403 (cashier tidak punya modul booking).

**Expected**: matriks permission ditegakkan (admin/doctor/therapist rw, cashier 403).

### 8. Validasi (FR-036)

1. `POST bookings` body `{..., start_at:"2020-01-01T10:00"}` (masa lalu) → 422 pada `start_at`.
2. `POST bookings` body `{..., end_at sebelum start_at}` → 422 pada `end_at`.
3. `POST bookings` body `{..., assignee_id: <user cashier/admin>}` → 422 pada `assignee_id` "Staf penanggung jawab harus dokter atau terapis."

**Expected**: validasi waktu + role assignee ditegakkan.

## Referensi

- Kontrak endpoint: [contracts/bookings-api.md](contracts/bookings-api.md)
- Struktur data: [data-model.md](data-model.md)
- Keputusan desain: [research.md](research.md)
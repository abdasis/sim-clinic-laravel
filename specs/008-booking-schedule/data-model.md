# Data Model — Booking & Jadwal Klinik (008-booking-schedule)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md)

Sumber kebenaran struktur: [`docs/erd/bookings.md`](../../docs/erd/bookings.md) + [`docs/erd/medical_records.md`](../../docs/erd/medical_records.md) + [`docs/normalization/README.md`](../../docs/normalization/README.md). Fitur ini revisi entitas `bookings` eksisting; **tidak ada entitas/kolom baru**.

## Entity: Booking

Janji temu pasien dengan penanggung jawab (dokter/terapis) untuk layanan utama pada rentang waktu tertentu. Tenant-scoped. Non-soft-delete (status `cancelled` menandai berakhir, FR-040).

| Field | Type | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, NOT NULL, **cascadeOnDelete** | BelongsToTenant; auto-fill saat create; pengecualian (hapus tenant = hapus semua data) |
| patient_id | bigint unsigned | FK→patients, NOT NULL, **restrictOnDelete** (revisi R1; sebelumnya cascadeOnDelete) | FR-038; immutable setelah rekam medis ada (FR-037) |
| service_id | bigint unsigned | FK→services, NOT NULL, **restrictOnDelete** (revisi R1; sebelumnya cascadeOnDelete) | FR-038; layanan utama tunggal (R9) |
| assignee_id | bigint unsigned | FK→users, NOT NULL, **restrictOnDelete** (sudah, migration 031000) | FR-038; dokter/terapis |
| start_at | datetime | NOT NULL, after now | FR-036; `BookingRequest` `after:now` |
| end_at | datetime | NOT NULL, after `start_at` | FR-036 |
| status | enum(BookingStatus: pending, confirmed, done, cancelled) | default `pending` | FR-031; `BookingStatus` enum + `canTransitionTo` |
| notes | text | nullable | FR-030; form FE tambah field |
| status_changed_at | timestamp | nullable | FR-034; di-set `ChangeBookingStatusAction` |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### Index (sudah ada, tidak berubah)

- `(tenant_id, assignee_id, start_at, end_at)` — overlap detection + jadwal (FR-035, SC-008).
- `(tenant_id, start_at)` — view jadwal harian/mingguan (FR-032, SC-013).

### Relationships

| Relasi | Tipe | Delete rule (target) | Catatan |
|--------|------|----------------------|--------|
| belongsTo `Tenant` | n:1 | cascadeOnDelete | pengecualian multi-tenant |
| belongsTo `Patient` | n:1 | **restrictOnDelete** (R1) | pasien di-nonaktifkan, bukan hard-delete |
| belongsTo `Service` | n:1 | **restrictOnDelete** (R1) | layanan di-arsip, bukan hard-delete |
| belongsTo `User` (assignee) | n:1 | **restrictOnDelete** (sudah) | user di-nonaktifkan, bukan hard-delete |
| hasOne `MedicalRecord` | 1:1 | (FK `medical_records.booking_id` → restrict, lihat medical_records.md) | kehadiran → immutability `patient_id` (FR-037) |
| hasOne `Transaction` | 1:1 (opsional) | — | booking done → rujukan transaksi (FR-033) |

### Validation (store/update) — `BookingRequest`

- `patient_id`: required|exists:patients,id (tenant-scoped via TenantScope). **Update:** bila berbeda dari existing & `medicalRecord` exists → 422 `booking.patient_immutable` (FR-037, R2).
- `service_id`: required|exists:services,id. Layanan arsip tidak muncul di pilihan FE (index services default active, R3 spec005); validasi `exists` tetap lolos untuk booking lama yang layanannya terarsip.
- `assignee_id`: required|exists:users,id + `withValidator` cek `ClinicRole::Doctor/Therapist` (FR-036).
- `start_at`: required|date|after:now (FR-036).
- `end_at`: required|date|after:start_at (FR-036).
- `notes`: nullable|string.

### State transitions (FR-031) — `BookingStatus::canTransitionTo`

```
            create
              │
              ▼
          ┌─────────┐  confirm  ┌────────────┐  complete  ┌──────┐
          │ pending │ ────────▶ │ confirmed  │ ─────────▶ │ done │
          └─────────┘           └────────────┘            └──────┘
              │                      │
              │ cancel               │ cancel
              ▼                      ▼
          ┌───────────┐        ┌───────────┐
          │ cancelled │ ◀───── │ cancelled │
          └───────────┘        └───────────┘
```

- `pending` → `confirmed` | `cancelled`
- `confirmed` → `done` | `cancelled`
- `done` → (tidak ada — final)
- `cancelled` → (tidak ada — final)

Transisi ilegal → `ChangeBookingStatusAction` `abort(422, __('clinic.invalid_transition'))`. Enforce di enum (sumber kebenaran) + Action.

## Immutability invariant (FR-037, Anomali #2)

| Kolom | Kondisi immutability | Ditegakkan di |
|-------|---------------------|---------------|
| `bookings.patient_id` | setelah `medical_records` ada untuk booking ini | `BookingRequest` (update, 422 per-field) + `UpdateBookingAction` (defense in depth, abort 422) |

Verifikasi: test assert ubah `patient_id` pada booking dengan rekam medis → 422; booking tanpa rekam medis → sukses (R7).

## Permission — `SyncTenantClinicRolesAction::MATRIX` (tidak berubah, R10)

| Role | booking | Catatan |
|------|---------|---------|
| admin | rw (CRUD + status + jadwal) | |
| doctor | rw (CRUD + status + jadwal) | |
| therapist | rw (CRUD + status + jadwal) | |
| cashier | (tidak ada — 403) | kasir tidak kelola booking |

Otorisasi via `BookingPolicy` → Gate `clinic.access` ['booking', 'r'|'w'] → permission spatie `booking.view`/`booking.manage`. FE sidebar visibility `bookings` roles `["admin","doctor","therapist"]` (mirror matriks).

## Activity log

Setiap aksi ubah-data → `LogAuditAction` (spatie/laravel-activitylog, tabel `audit_logs`):

| Aksi | event/log_name | Deskripsi naratif | Properties |
|------|----------------|-------------------|------------|
| store | `booking.created` | "Membuat booking {layanan} untuk {pasien} pada {tanggal}." | `attributes` (full) + `tenant_id` |
| update | `booking.updated` | "Memperbarui booking {pasien}." | `old` + `new` (diff field yang diubah) + `tenant_id` |
| changeStatus | `booking.status_changed` | "Status booking {pasien} diubah dari {lama} ke {baru}." | `old: {status: lama}`, `new: {status: baru}` + `tenant_id` |
| delete | `booking.deleted` | "Menghapus booking {pasien}." | `attributes` (snapshot) + `tenant_id` |

Causer: auth user (auto via `LogAuditAction`). Narasi status lama→baru (FR-039) sudah compliant di `ChangeBookingStatusAction`.

## Migration changes

Satu migration baru: `2026_08_14_*_change_bookings_patient_service_foreign_keys_to_restrict` — drop + recreate 2 FK (`patient_id`, `service_id`) dengan `restrictOnDelete`. Skip pada driver SQLite (preseden migration 031000 — rebuild tabel tidak praktis di test :memory:), PostgreSQL produksi tetap RESTRICT. **Tidak ada perubahan kolom `bookings`** — tidak ada kolom baru, tidak ada soft-delete.

## Resource shape — `BookingResource` (revisi R3)

```json
{
  "id": 1,
  "patient_id": 2,
  "patient_name": "Siti Aminah",
  "service_id": 3,
  "service_name": "Facial Glow",
  "assignee_id": 4,
  "assignee_name": "dr. Andi",
  "start_at": "2026-08-15T10:00:00+00:00",
  "end_at": "2026-08-15T11:00:00+00:00",
  "status": "pending",
  "status_label": "Menunggu",
  "notes": null,
  "has_medical_record": false,
  "created_at": "2026-08-14T08:00:00+00:00"
}
```

`has_medical_record` di-expose pada response show/update (eager-load relasi `medicalRecord`). Pada `schedule` (list jadwal) flag tidak disertakan (tidak edit pasien di jadwal) — tetap ringan.

## Tidak ada entity baru

Fitur murni revisi `bookings` (FK + immutability + resource flag) + FE. Tidak ada tabel/kolom baru. `BookingFactory` perlu dibuat/diperiksa (untuk kebutuhan test, R7) bila belum ada.
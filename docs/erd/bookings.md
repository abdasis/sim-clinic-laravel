# `bookings`

Booking pasien & jadwal (Booking & Jadwal, US4).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| patient_id | bigint unsigned | FK→patients, not null | FR-030 |
| service_id | bigint unsigned | FK→services, not null | FR-030; satu layanan utama (R9) |
| assignee_id | bigint unsigned | FK→users, not null | FR-030; Dokter/Terapis |
| start_at | datetime | not null | FR-030 |
| end_at | datetime | not null | after `start_at` |
| status | enum(BookingStatus: pending, confirmed, done, cancelled) | default `pending` | FR-031 |
| notes | text | nullable | |
| status_changed_at | timestamp | nullable | FR-034 audit sederhana |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- `(tenant_id, assignee_id, start_at, end_at)` — overlap detection + jadwal (FR-035, SC-008).
- `(tenant_id, start_at)` — view jadwal harian/mingguan (FR-032).

## Relasi

- belongsTo `Patient`, `Service`, `Assignee` (User)
- hasOne `MedicalRecord` (1 record per booking, R10)
- hasOne `Transaction` (opsional, kalau booking jadi transaksi — FR-033)

## Delete Rule

- Booking TIDAK soft-delete — status `cancelled` (FR-031) sudah cukup penanda berakhirnya booking. Riwayat jadwal tetap utuh untuk audit.
- FK `patient_id`, `assignee_id`, `service_id` → **`restrictOnDelete`**, bukan `cascadeOnDelete`. Hapus pasien/dokter/layanan tidak boleh menghapus booking — parent di-soft-delete (pasien/dokter) atau di-arsip (layanan), booking tetap.
- FK `tenant_id` → `cascadeOnDelete` (pengecualian: hapus tenant = hapus semua datanya).
- `medical_records.booking_id` sudah `restrictOnDelete` (lihat medical_records.md) — hapus booking tidak menghapus rekam medis walau booking dihapus.

## Validation

- `patient_id` exists in tenant
- `service_id` exists + active
- `assignee_id` exists + clinic_role in (doctor, therapist)
- `start_at` required|date|after:now
- `end_at` required|date|after:start_at

## State Transitions (FR-031)

- `pending` → `confirmed` → `done`
- `pending`/`confirmed` → `cancelled`
- `done` TIDAK → `cancelled` (edge case)
- Enforce di `BookingController`/FormRequest.

## Catatan

- **Overlap detection** (FR-035, R8): post-validation, query booking lain `assignee_id` sama + `start_at < other.end_at AND end_at > other.start_at` + status ≠ cancelled → flag `overlap_warnings`. Tidak block.
- Booking `done` menjadi dasar rekam medis (FR-033, FR-040) dan rujukan transaksi.
- `status_changed_at` mencatat waktu perubahan status (FR-034).
# `medical_records`

Rekam medis SOAP (Rekam Medis, US7).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| booking_id | bigint unsigned | FK→bookings, not null, unique | FR-040; 1 record per booking (R10) |
| patient_id | bigint unsigned | FK→patients, not null | denormalized untuk query riwayat |
| author_id | bigint unsigned | FK→users, not null | dokter/terapis pengisi |
| subjective | text | nullable | FR-040 SOAP — S |
| objective | text | nullable | FR-040 SOAP — O |
| assessment | text | nullable | FR-040 SOAP — A |
| plan | text | nullable | FR-040 SOAP — P |
| deleted_at | timestamp | nullable | soft delete; rekam medis tidak pernah hard-delete |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- `(tenant_id, booking_id)` UNIQUE — 1 record per booking.
- `(tenant_id, patient_id, created_at)` INDEX — query riwayat rekam medis per pasien (FR-022) tanpa full scan.

## Relasi

- belongsTo `Booking`, `Patient`, `Author` (User)
- hasMany `TreatmentRecord`
- hasMany `MedicalPhoto`

## Delete Rule

- **Soft delete** (`deleted_at`). Rekam medis adalah catatan klinis legal — tidak boleh hard-delete.
- FK `booking_id`, `patient_id`, `author_id` → **`restrictOnDelete`**, bukan `cascadeOnDelete`.
  - Hapus booking (parent) TIDAK boleh menghapus rekam medis walau booking dihapus — ini perubahan dari implementasi awal yang memakai cascade.
  - Hapus dokter/author TIDAK boleh menghapus rekam medis yang pernah ditulisnya. User sebaiknya di-soft-delete (`status=inactive`), bukan hard-delete.
  - Hapus pasien → restrict (pasien di-soft-delete, rekam medis tetap).

## Business Rules

- Booking harus `status=done` sebelum/serupa mengisi (FR-033, FR-040).
- Hanya role dokter/therapist/admin yang boleh mengisi (FR-044, Policy).

## Catatan

- `patient_id` denormalized dari booking untuk query riwayat pasien tanpa join ke bookings.
- Riwayat kunjungan pasien (FR-022) = agregasi `medical_records` (via `patient_id`).
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
- `(tenant_id, deleted_at)` INDEX — daftar rekam medis aktif per tenant.

ponytail: alter FK dilewati di SQLite (tidak mendukung drop foreign key), jadi
aturan restrict hanya ditegakkan di PostgreSQL. `ForeignKeyRestrictTest`
karena itu di-skip di SQLite — jalankan `phpunit.pgsql.xml` sebelum rilis.

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

Anak-anaknya (`treatment_records`, `medical_photos`) juga `restrictOnDelete` ke
`medical_records` (R2) — hard-delete rekam medis yang masih punya treatment atau
foto ditolak database. Ini menimpa langkah 15/16 workflow yang semula cascade.

## Business Rules

- Booking harus `status=done` sebelum/serupa mengisi (FR-033, FR-040).
- Hanya role dokter/therapist/admin yang boleh mengisi (FR-044, Policy).
- `patient_id` diturunkan dari `booking->patient_id` saat pembuatan dan tidak
  bisa diubah sesudahnya (R4). `booking_id` ditolak (`prohibited`) pada PATCH —
  kunjungan yang menentukan pemilik catatan, jadi tidak boleh dipindah.
  Sisi booking dijaga terpisah: PATCH `bookings/{booking}` dengan `patient_id`
  berbeda ditolak 422 selama rekam medisnya sudah ada
  (`BookingPatientImmutableTest`).
- Revisi SOAP dicatat activity `medical_record.updated` dengan properti diff
  `old`/`new`; soft-delete dicatat `medical_record.deleted`.

## Catatan

- `patient_id` denormalized dari booking untuk query riwayat pasien tanpa join ke bookings.
- Riwayat kunjungan pasien (FR-022) = agregasi `medical_records` (via `patient_id`).
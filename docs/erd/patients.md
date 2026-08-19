# `patients`

Data pasien (Manajemen Pasien, US3).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| name | string(255) | not null | FR-020 |
| birth_date | date | nullable | FR-020 |
| gender | enum(male, female, other) | nullable | FR-020 |
| whatsapp | string(50) | not null | FR-020, FR-023 (peringatan duplikat); satu-satunya nomor pasien |
| address | text | nullable | |
| notes | text | nullable | |
| deleted_at | timestamp | nullable | soft delete; data klinis/riwayat pasien wajib bertahan |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- TIDAK ada unique constraint pada `whatsapp` (FR-023 = peringatan, bukan block).
- `(tenant_id, whatsapp)` INDEX untuk deteksi duplikat.
- `(tenant_id, deleted_at)` INDEX — list pasien aktif per tenant (query `whereNull('deleted_at')`).

## Relasi

- belongsTo `Tenant`
- hasMany `Booking`
- hasMany `MedicalRecord`
- hasMany `Transaction`

## Delete Rule

- **Soft delete** (`deleted_at`), bukan hard delete. Pasien nonaktif tetap di-scope per tenant; riwayat kunjungan & rekam medis tidak hilang.
- FK dari `bookings`/`medical_records`/`transactions` ke `patients` → `restrictOnDelete` (blokir hard-delete parent yang masih punya referensi). Soft delete tidak memicu restrict, jadi aman.

## Validation

- `name` required
- `whatsapp` required|string|max:50
- `birth_date` nullable|date|before:today
- `gender` nullable|enum
- `address` nullable|string

## Catatan

- Duplikat dideteksi di `PatientService::findDuplicateByWhatsapp()` → response flag `duplicate_warning` (FR-023).
- Kolom `phone` dihapus (migrasi `2026_08_19_100000`): nomornya disalin ke `whatsapp` yang masih kosong, lalu `whatsapp` mengambil alih perannya. Satu nomor saja, dan itu yang dipakai pengingat serta broadcast.
- Riwayat kunjungan pasien (FR-022) = agregasi `medical_records` + `bookings` per pasien.
- Admin dapat memperbarui data kontak pasien (FR-024).
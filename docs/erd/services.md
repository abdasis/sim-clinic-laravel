# `services`

Master layanan/treatment (Manajemen Layanan, US2).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| name | string(255) | not null | FR-011 |
| description | text | nullable | |
| price | decimal(12,2) | not null, ≥0 | FR-011: tidak negatif |
| status | enum(ServiceStatus: active, archived) | default `active` | FR-013 |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- `(tenant_id, status)` — filter layanan aktif.

## Relasi

- belongsTo `Tenant`
- hasMany `Booking` (layanan utama booking)
- hasMany `TreatmentRecord` (nullable FK; snapshot di `treatment_records.service_name`)
- hasMany `TransactionItem` (nullable FK; snapshot di `transaction_items.name`)

## Delete Rule

- **Tidak ada hard delete.** Nonaktif = `status=archived` (FR-013), soft hide dari pilihan baru.
- FK dari `bookings.service_id`, `treatment_records.service_id`, `transaction_items.service_id` → **`restrictOnDelete`** — blokir hapus layanan yang masih direferensi. Pakai arsip, bukan hapus.

## Validation (store/update)

- `name` required|string|max:255
- `price` required|decimal|gte:0
- `description` nullable|string
- `status` enum

## Catatan

- Arsip (FR-013) = set `status=archived`, soft hide dari pilihan baru. Tidak hapus permanen.
- Perubahan harga berlaku untuk booking/transaksi baru (FR-012). Booking/transaksi lama memakai snapshot harga di `transaction_items.unit_price` (FR-056).
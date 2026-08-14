# `tenants`

Entitas klinik/organisasi. Acuan isolasi seluruh data (multi-tenant single DB, spec 001).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK, auto-increment | |
| name | string(255) | not null | Nama perusahaan/klinik |
| slug | string(255) | unique, not null, URL-safe | Diturunkan dari name; reject non-URL-safe (FR-004, FR-005) |
| phone | string(50) | not null | Format lokal/internasional divalidasi |
| status | enum(active, inactive) | default `active` | FR-011: nonaktif tidak hapus data |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- `slug` UNIQUE INDEX (DB constraint, FR-004/FR-005).

## Relasi

- hasMany `users`
- hasMany semua entitas tenant-scopeable (`services`, `patients`, `products`, dst.) via `tenant_id`

## Delete Rule

- `status=inactive` (FR-011) untuk nonaktif tanpa hapus data. Hapus permanen tenant di luar scope v1.
- Semua FK `tenant_id` → **`cascadeOnDelete`** (pengecualian aturan umum): hapus tenant = hapus semua datanya. Karena `users`/`patients`/`medical_records`/`transactions` di-soft-delete, cascade DB hard-delete hanya terjadi pada operasi hapus tenant eksplisit (jarang/terlarang di v1); arus normal (nonaktif) tidak menyentuh cascade.

## State Transitions

- `active` ↔ `inactive` (FR-011, FR-006). Nonaktif → akhiri sesi aktif (FR-009).
- Hapus permanen di luar scope v1.

## Catatan

- **Central tenant**: tenant khusus dengan slug `central` (seeded). Titik masuk autentikasi platform (FR-027). Admin platform = user pada central tenant dengan role `platform_admin`.
- Bukan tabel terpisah — hanya baris `tenants` dengan slug `central`.
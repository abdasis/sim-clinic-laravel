# `invitations`

Undangan anggota klinik/tenant (spec 001).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK → tenants.id, not null | |
| email | string(255) | not null | Email diundang |
| role | enum(tenant_admin, member) | default `member` | FR-024 |
| token | string(64) | unique, not null | Untuk accept URL |
| expires_at | timestamp | not null | Masa kedaluwarsa |
| status | enum(pending, accepted, cancelled, expired) | default `pending` | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- `(tenant_id, email)` INDEX.

## Relasi

- belongsTo `Tenant`

## Business Rules

- Tolak undangan email yang sudah user aktif di tenant sama (FR-022).
- Admin tenant bisa batalkan.
- Saat accept → buat `User` status `active` (atau `pending` set password) + invitation `accepted`.

## Catatan

- Klinik MVP (spec 002) menggunakan `clinic_role` di `users` (admin/doctor/therapist/cashier). `invitations.role` (spec 001) untuk keanggotaan tenant-level; peran klinik ditentukan saat/after accept.
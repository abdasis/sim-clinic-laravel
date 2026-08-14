# `users`

Akun pengguna. Terikat tepat satu tenant (v1). Admin pertama dibuat saat registrasi tenant (FR-014). Modifikasi dari spec 001 — tambah `clinic_role` (spec 002).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK → tenants.id, not null | BelongsToTenant; central tenant untuk admin platform |
| name | string(255) | not null | |
| email | string(255) | unique (global), not null | FR-015: unik lintas tenant |
| password | string(255) | not null (hashed) | FR-016: min 8, campur huruf+angka |
| role | enum(platform_admin, tenant_admin, member) | not null | FR-024; platform_admin hanya di central tenant |
| clinic_role | enum(ClinicRole: admin, doctor, therapist, cashier) | nullable | FR-001; null untuk user non-klinik (mis. platform admin). Satu user satu peran klinik |
| status | enum(pending, active, inactive) | default `active` | FR-020: undangan → pending; FR-023 |
| email_verified_at | timestamp | nullable | v1 opsional |
| deleted_at | timestamp | nullable | soft delete; user nonaktif tetap jadi author/causer di riwayat |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- `email` UNIQUE INDEX global (FR-015).
- `tenant_id` INDEX (filter per tenant).
- `(tenant_id, deleted_at)` INDEX — list user aktif per tenant.

## Delete Rule

- **Soft delete** (`deleted_at`). Nonaktifkan user → `status=inactive` + `deleted_at` (atau `status=inactive` saja bila masih butuh login audit). Hard-delete dilarang karena user adalah author rekam medis & causer audit log — penghapusan akan memutus riwayat.
- FK dari `bookings.assignee_id`, `medical_records.author_id`, `transactions.cashier_id` ke `users` → **`restrictOnDelete`** (bukan cascade). Data buatan user tetap milik tenant walau user nonaktif (FR-023).

## Relasi

- belongsTo `Tenant`
- hasMany `Booking` (sebagai assignee — dokter/terapis)
- hasMany `MedicalRecord` (sebagai author)
- hasMany `Transaction` (sebagai cashier)
- Audit log sebagai causer via spatie Activity (morph `causer_id`/`causer_type`)

## State Transitions (status)

- Registrasi tenant → `active` (admin pertama, FR-017).
- Undangan admin tenant → `pending` → set password → `active` (FR-020).
- Nonaktifkan/hapus keanggotaan → `inactive` / delete (FR-023).
- Tolak nonaktifkan/hapus admin terakhir tenant (FR-005, FR-025).

## Catatan

- Satu email satu tenant di v1 (FR-015, assumption).
- Hapus user → data buatan user tetap milik tenant.
- `clinic_role` membatasi akses modul (FR-002, FR-004).
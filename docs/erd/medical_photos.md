# `medical_photos`

Foto before & after treatment (Rekam Medis, US7).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| medical_record_id | bigint unsigned | FK→medical_records, not null | |
| type | enum(MedicalPhotoType: before, after) | not null | FR-042 |
| path | string(255) | not null | R3: `medical-photos/{tenant}/{record}/{file}` |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Relasi

- belongsTo `MedicalRecord`

## Delete Rule

- FK `medical_record_id` → **`restrictOnDelete`** (R2). Semula cascade; diubah karena cascade menjadikan hard-delete rekam medis ikut menghapus baris foto sementara berkas fisiknya tertinggal di storage. Sekarang hard-delete parent ditolak database selama masih ada foto — penghapusan yang sah hanya lewat soft-delete parent, dan foto tetap utuh.

  ponytail: pembersihan berkas fisik saat rekam medis di-soft-delete belum ada. Ceiling: berkas menumpuk di storage untuk rekam medis yang sudah dihapus. Upgrade: observer `deleted` pada `MedicalRecord` yang mengantre penghapusan berkas.

## Validation (upload, R3)

- `file` required|image|mimes:jpg,jpeg,png|max:2048

## Catatan

- Path storage mengikuti konvensi `medical-photos/{tenant}/{record}/{file}` (R3) untuk isolasi per tenant.
- Pasangan before/after ditentukan via `type` pada `medical_record_id` yang sama.
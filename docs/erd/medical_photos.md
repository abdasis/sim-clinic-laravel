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

- FK `medical_record_id` → **`cascadeOnDelete`** aman: parent (`medical_records`) di-soft-delete, cascade DB hanya saat hard-delete parent (kasus terlarang/jarang). File fisik di storage tetap di-clean via observer/listener saat medical record di-soft-delete (queue), bukan bergantung cascade DB.

## Validation (upload, R3)

- `file` required|image|mimes:jpg,jpeg,png|max:2048

## Catatan

- Path storage mengikuti konvensi `medical-photos/{tenant}/{record}/{file}` (R3) untuk isolasi per tenant.
- Pasangan before/after ditentukan via `type` pada `medical_record_id` yang sama.
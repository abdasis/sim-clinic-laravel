# `treatment_records`

Treatment aktual di dalam rekam medis (Rekam Medis, US7).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| medical_record_id | bigint unsigned | FK→medical_records, not null | |
| service_id | bigint unsigned | FK→services, nullable | FR-041; nullable jika tindakan di luar master |
| service_name | string(255) | not null | snapshot nama (R6 spirit) |
| notes | text | nullable | catatan klinis |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Relasi

- belongsTo `MedicalRecord`, `Service` (nullable)

## Delete Rule

- FK `medical_record_id` → **`restrictOnDelete`** (R2). Semula cascade; diubah karena cascade menjadikan hard-delete rekam medis diam-diam ikut menghapus treatment. Sekarang hard-delete parent ditolak database selama masih ada treatment — penghapusan yang sah hanya lewat soft-delete parent, dan treatment tetap utuh.
- FK `service_id` nullable → `restrictOnDelete` (service di-arsip `status=archived`, bukan hapus; `service_name` snapshot tetap utuh walau master berubah — R6).

## Catatan

- `service_name` snapshot nama layanan agar riwayat tetap utuh walau layanan diubah/arsip (R6).
- `service_id` nullable untuk tindakan ad-hoc di luar master layanan (FR-041).
- Riwayat treatment pasien (US1) = agregasi `treatment_records` via `medical_records.patient_id`.
# Data Model — Master Pasien Klinik (006-patient-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md)

Sumber kebenaran struktur: [`docs/erd/patients.md`](../../docs/erd/patients.md) + [`docs/normalization/README.md`](../../docs/normalization/README.md). Fitur ini revisi entitas `patients` eksisting; tidak ada entitas baru.

## Entity: Patient

Data pasien klinik. Tenant-scoped. Soft delete untuk nonaktifkan.

| Field | Type | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, NOT NULL, cascadeOnDelete | BelongsToTenant; auto-fill saat create |
| name | string(255) | NOT NULL | FR-020; tidak unique per tenant |
| birth_date | date | nullable | FR-020; `before:today` |
| gender | enum(male, female, other) | nullable | FR-020 |
| phone | string(50) | NOT NULL | FR-020/023; **tidak unique** — duplikat diizinkan dengan peringatan |
| whatsapp | string(50) | nullable | FR-020 |
| address | text | nullable | FR-020 |
| notes | text | nullable | FR-020 |
| deleted_at | timestamp | nullable | **NEW (R1)**: soft delete; `SoftDeletes` trait |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### Index

- `(tenant_id, phone)` — sudah ada; deteksi duplikat phone per tenant (FR-021/023).
- `(tenant_id, deleted_at)` — **NEW (R1)**: list pasien aktif per tenant (`whereNull('deleted_at')`); `SoftDeletes` global scope otomatis filter.

### Relationships

| Relasi | Tipe | Delete rule (target) | Riwayat tetap utuh? |
|--------|------|----------------------|---------------------|
| belongsTo `Tenant` | n:1 | — | — |
| hasMany `Booking` | 1:n | **restrictOnDelete** (revisi R2; sebelumnya cascadeOnDelete) | booking tetap merujuk pasien; `BookingResource` baca `patient->name` via `whenLoaded` (live) |
| hasMany `MedicalRecord` | 1:n | **restrictOnDelete** (revisi R2; sebelumnya cascadeOnDelete) | rekam medis tetap utuh; `patient_id` denormalized tetap valid |
| hasMany `Transaction` | 1:n (nullable FK) | **restrictOnDelete** (revisi R2; sebelumnya nullOnDelete) | transaksi tetap merujuk pasien; snapshot item tidak tersinkron |

### Validation (store/update)

- `name`: required|string|max:255
- `phone`: required|string|max:50
- `birth_date`: nullable|date|before:today
- `gender`: nullable|in:male,female,other
- `whatsapp`: nullable|string|max:50
- `address`: nullable|string
- `notes`: nullable|string

### State transitions

```
        create
          │
          ▼
      ┌────────┐  deactivate (soft delete)  ┌──────────┐
      │ active │ ─────────────────────────▶ │ inactive │
      └────────┘                             └──────────┘
        (deleted_at IS NULL)                  (deleted_at NOT NULL)
```

- `active → inactive`: via `DeactivatePatientAction` (FR-025) — `$patient->delete()` set `deleted_at`.
- `inactive → active`: **tidak diekspos MVP** (restore/reaktifkan = YAGNI; bila butuh, `Patient::withTrashed()->restore()` — add saat diminta).
- Tidak ada state `deleted` permanen — hard-delete tidak diekspos endpoint (R3). DB `restrictOnDelete` blokir hard-delete bila masih direferensi.

## Soft delete & riwayat invariant (FR-022, FR-028)

| Aturan | Mekanisme | Verifikasi |
|--------|-----------|------------|
| Pasien nonaktif tidak muncul di list aktif (FR-026) | `SoftDeletes` global scope exclude `whereNotNull('deleted_at')` dari query default | Test: nonaktifkan → `index` tidak return pasien |
| Riwayat tetap dapat diakses walau nonaktif (FR-022) | `history`/`show` query `withTrashed()` (R5); TenantScope tetap aktif | Test: pasien nonaktif → `history` 200 + lengkap |
| Relasi tetap utuh saat nonaktif (FR-028) | soft delete hanya isi `deleted_at`; FK `patient_id` di bookings/medical_records/transactions tidak berubah | Test: nonaktifkan → booking/rekam medis/transaksi tetap merujuk pasien |
| Hard-delete direferensi diblokir (FR-027) | DB `restrictOnDelete` pada 3 FK | Test: `forceDelete()` dengan referensi → `QueryException` |

## Duplicate phone (FR-021/023)

- `phone` **tidak unique** (tidak ada unique constraint). Duplikat diizinkan.
- Deteksi di Controller (store + update): `Patient::where('tenant_id', …)->where('phone', …)->where('id', '!=', $currentId)->exists()` → response `meta.duplicate_warning=true` + `meta.duplicate_patient_id`.
- Tidak memblokir penyimpanan — peringatan saja.
- Scope per-tenant (telepon sama di klinik lain tidak memicu).

## Permission

`ClinicPermission::MATRIX` (tidak berubah, R8 research):

| Role | patient |
|------|---------|
| admin | rw (CRUD + nonaktifkan) |
| doctor | rw (CRUD pasien) |
| therapist | r (view) |
| cashier | rw (CRUD pasien — perlu data pasien untuk transaksi POS) |

Otorisasi via `PatientPolicy` → Gate `clinic.access` ['patient', 'r'|'w']. `PatientPolicy` saat ini tidak punya `delete` method — tambah `delete(User)` delegasi `clinic.access` ['patient', 'w'] untuk route `destroy`.

## Activity log

Setiap aksi ubah-data → `LogAuditAction` (spatie/laravel-activitylog, tabel `audit_logs`):

| Aksi | event/log_name | Deskripsi naratif |
|------|----------------|-------------------|
| store | `patient.created` | "Membuat pasien {name}" |
| update | `patient.updated` | "Memperbarui pasien {name}" |
| destroy (deactivate) | `patient.deactivated` | "Menonaktifkan pasien {name}" |

Properties: `tenant_id` (auto dari container). Causer: auth user (auto).

## Migration changes

1. **Edit** `2026_07_06_120000_create_patients_table.php`: +`$table->softDeletes();` +`$table->index(['tenant_id', 'deleted_at']);` (repo pre-production, pola spec 004).
2. **NEW** `2026_08_14_*_restrict_patient_foreign_keys.php`: drop + recreate 3 FK (`bookings.patient_id`, `medical_records.patient_id`, `transactions.patient_id`) dengan `restrictOnDelete`.

## Tidak ada entity baru

Fitur ini murni revisi `patients` + relasinya. Tidak ada tabel baru. `PatientFactory` perlu dibuat (saat ini belum ada) untuk kebutuhan test (R8).
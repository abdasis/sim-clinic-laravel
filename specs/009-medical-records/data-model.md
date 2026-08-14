# Data Model — Rekam Medis SOAP (009-medical-records)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Sumber kebenaran**: `docs/erd/medical_records.md`, `docs/erd/bookings.md`, `docs/erd/treatment_records.md`, `docs/erd/medical_photos.md`, `docs/normalization/README.md`

Tidak ada entity baru. Perubahan: kolom baru (`deleted_at`), index baru, FK delete rule (parent + child), soft delete trait, endpoint shape.

## Entity: `medical_records`

Rekam medis SOAP pasien per kunjungan (tenant-scoped via `BelongsToTenant` + `TenantScope`, **+ `SoftDeletes`**). Aggregate root untuk `treatment_records` + `medical_photos`.

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant, cascadeOnDelete |
| booking_id | bigint unsigned | FK→bookings, not null, unique(tenant) | **R2: cascadeOnDelete→restrictOnDelete** (sebelumnya cascade); FR-088; 1 record per booking (R10) |
| patient_id | bigint unsigned | FK→patients, not null | **R2: cascadeOnDelete→restrictOnDelete** (sebelumnya cascade); denormalized dari booking (FR-022); immutable setelah record ada (anomali #2, R4) |
| author_id | bigint unsigned | FK→users, not null | restrictOnDelete (sudah via migration existing); dokter/terapis pengisi |
| subjective | text | nullable | FR-040 SOAP — S |
| objective | text | nullable | FR-040 SOAP — O |
| assessment | text | nullable | FR-040 SOAP — A |
| plan | text | nullable | FR-040 SOAP — P |
| **deleted_at** | **timestamp** | **nullable** | **NEW (R1)** soft delete; FR-090; rekam medis tidak pernah hard-delete |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Relasi**: belongsTo `Booking`, `Patient`, `Author` (User); hasMany `TreatmentRecord`; hasMany `MedicalPhoto`.

**Constraint & Index**:
- `(tenant_id, booking_id)` UNIQUE (sudah) — 1 record per booking (R10, FR-088).
- `(tenant_id, patient_id, created_at)` INDEX **NEW (R3)** — query riwayat rekam medis per pasien (FR-022) tanpa full scan.
- `(tenant_id, deleted_at)` INDEX **NEW (R1)** — list rekam medis aktif per tenant (FR-090/096).

**Business Rules**:
- Booking harus `status=done` sebelum rekam medis bisa diisi (FR-033/040) — guard di `MedicalRecordService::create` (existing).
- Hanya satu rekam medis per booking (unique `booking_id`, R10, FR-088) — guard existing.
- Hanya role dokter/therapist/admin (FR-044) — `MedicalRecordPolicy` Gate `medical_record.view`/`medical_record.manage`.
- `patient_id` diisi dari `booking->patient_id` saat create, immutable setelahnya (R4) — tidak ada field `patient_id` di update request.

**Delete Rule**:
- **Soft delete** (`deleted_at`) — catatan klinis legal, tidak hard-delete (FR-090).
- FK `booking_id`, `patient_id`, `author_id` → **`restrictOnDelete`** (FR-093) — booking/pasien/dokter direferensi tidak boleh dihapus begitu record ada. `author_id` sudah restrict; `booking_id`+`patient_id` revisi cascade→restrict.
- FK child `medical_record_id` (`treatment_records`, `medical_photos`) → **`restrictOnDelete`** (FR-092, **override workflow langkah 15/16 cascade**) — blokir hard-delete parent bila child ada. Soft-delete parent tidak trigger FK → child tetap utuh (FR-091/SC-007).

## Entity: `treatment_records` (child, revisi FK)

Tidak berubah kolom. FK `medical_record_id` delete rule: `cascadeOnDelete`→**`restrictOnDelete`** (FR-092, override workflow). `service_id` tetap restrictOnDelete (sudah).

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| medical_record_id | bigint unsigned | FK→medical_records, not null | **R2: cascadeOnDelete→restrictOnDelete** (sebelumnya cascade); FR-092 |
| (kolom lain) | tidak berubah | | |

## Entity: `medical_photos` (child, revisi FK)

Tidak berubah kolom. FK `medical_record_id` delete rule: `cascadeOnDelete`→**`restrictOnDelete`** (FR-092, override workflow). File fisik cleanup via queue listener saat parent soft-delete (detail spec langkah 16, bukan cascade DB).

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| medical_record_id | bigint unsigned | FK→medical_records, not null | **R2: cascadeOnDelete→restrictOnDelete** (sebelumnya cascade); FR-092 |
| (kolom lain) | tidak berubah | | |

## Perubahan FK — migration R2

| Tabel | FK | Sebelum | Sesudah | Alasan |
|-------|----|---------|---------|--------|
| medical_records | booking_id | cascadeOnDelete | **restrictOnDelete** | blokir hapus booking direferensi rekam medis (FR-093) |
| medical_records | patient_id | cascadeOnDelete | **restrictOnDelete** | blokir hapus pasien direferensi rekam medis (FR-093) |
| medical_records | author_id | (sudah restrict) | restrictOnDelete | sudah via migration 2026_08_14_032000 |
| treatment_records | medical_record_id | cascadeOnDelete | **restrictOnDelete** | blokir hard-delete parent bila treatment ada (FR-092) |
| medical_photos | medical_record_id | cascadeOnDelete | **restrictOnDelete** | blokir hard-delete parent bila foto ada (FR-092) |

**Catatan SQLite**: alter FK delete rule tidak didukung SQLite. Migration guard `if (Schema::getConnection()->getDriverName() === 'sqlite') { return; }`. `ForeignKeyRestrictTest` hanya jalan via `phpunit.pgsql.xml` (R8, konvensi CLAUDE.md).

## Migration summary (R8 strategi)

| Migration | Isi | SQLite | PostgreSQL |
|-----------|-----|--------|------------|
| 1. add_medical_records_softdelete_index_restrict_fk | +deleted_at softDeletes, +index(tenant_id,patient_id,created_at), +index(tenant_id,deleted_at), FK booking_id/patient_id cascade→restrict, FK child medical_record_id cascade→restrict | softDeletes+index jalan; FK alter guard skip | semua jalan |

## Validation (`MedicalRecordRequest` — revisi)

| Field | Rule (create) | Rule (update) |
|-------|---------------|---------------|
| booking_id | required, exists:bookings,id | tidak di-accept (immutable, dari route record) |
| subjective | nullable, string | nullable, string |
| objective | nullable, string | nullable, string |
| assessment | nullable, string | nullable, string |
| plan | nullable, string | nullable, string |

**Invariant R4**: `patient_id` tidak ada di request (create maupun update) — diisi otomatis dari `booking->patient_id` di action, immutable setelahnya. Booking side tolak ubah `patient_id` bila record ada (sudah ada).

## State transitions

- `deleted_at`: null → soft-delete (FR-090). Tidak expose restore di MVP (`ponytail:`).
- SOAP field: nullable (draf) → diisi kapan saja via `update` (FR-094). Tidak ada state enum.

## Invariant yang diuji (bukan kolom baru)

1. **FR-088 / R10**: 1 rekam medis per booking — unique `booking_id`; duplikat ditolak 422. Test.
2. **FR-033/040**: booking non-`done` → 422. Test (existing guard).
3. **FR-044**: kasir/member akses → 403 (Policy). Test.
4. **FR-022 / R3**: riwayat per pasien kronologis, scoped tenant, pakai index. Test.
5. **FR-090 / R1**: soft-delete → `deleted_at` terisi, tidak muncul di list aktif, data utuh. Test.
6. **FR-091**: soft-delete parent → treatment/photo tetap utuh. Test.
7. **FR-092 / R2**: hard-delete parent bila child ada → diblokir restrict (pgsql test). Test.
8. **FR-093 / R2**: hapus booking/pasien/dokter direferensi → diblokir restrict (pgsql test). Test.
9. **FR-094 / R5**: audit naratif "Mengisi rekam medis pasien {patient}" (create); update = diff old/new. Test.
10. **R4**: ubah `patient_id` booking bila record ada → 422 (sudah ada, verifikasi). Test.
11. **Konstitusi III**: rekam medis tenant A tidak terlihat tenant B (`TenantScope`). Test.

## Activity log (FR-094, R5)

| Aksi | Action | Event | Narasi | Properties |
|------|--------|-------|--------|-----------|
| create | `CreateMedicalRecordAction` | `medical_record.created` | "Mengisi rekam medis pasien {patient}" | full attributes (booking_id, patient_id, SOAP) |
| update | `UpdateMedicalRecordAction` | `medical_record.updated` | "Memperbarui rekam medis pasien {patient}" | old/new diff SOAP |
| soft-delete | `SoftDeleteMedicalRecordAction` | `medical_record.deleted` | "Menghapus rekam medis pasien {patient}" | subject context |

Flow: Controller → Service → Action → `LogAuditAction::handle(action, subject, causer, context, description, tenant)`. Causer = auth user, subject = MedicalRecord, tenant via `properties->tenant_id`.
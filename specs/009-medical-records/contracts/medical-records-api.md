# API Contracts — Rekam Medis SOAP (009-medical-records)

**Date**: 2026-08-14 | **Data model**: [data-model.md](../data-model.md)

Semua endpoint tenant-scoped, prefix `/{tenant}/clinic`, middleware `resolve.tenant` + `ensure.tenant.active` + `auth:sanctum`. Otorisasi `MedicalRecordPolicy` → Gate `clinic.access` modul `medical_record` (`medical_record.view` read, `medical_record.manage` write — FR-044: admin/dokter/terapis, kasir ditolak). Response shape `{ data, meta }`; error `{ message, errors }` (422) / `{ message }` (403/404).

Route eksisting: `POST medical-records`, `POST medical-records/{medicalRecord}/treatments`, `POST medical-records/{medicalRecord}/photos`. **Perubahan**: +`GET medical-records` (index), +`GET medical-records/{medicalRecord}` (show), +`PATCH medical-records/{medicalRecord}` (update SOAP), +`DELETE medical-records/{medicalRecord}` (soft-delete), +`GET patients/{patient}/medical-records` (riwayat per pasien FR-022). `patientTreatments` controller method → `patientRecords` + daftarkan route. Tidak ada route hard-delete/restore.

## Endpoints

### List medical records — `GET /{tenant}/clinic/medical-records`

**Permission**: `medical_record` `r` (admin/dokter/terapis per Policy).

**Query params** (DataTable, `InteractsWithDataTable`):

| Param | Type | Default | Catatan |
|-------|------|---------|---------|
| page | int | 1 | |
| per_page | int | 10 | max 100 |
| sort | string | null | `created_at`/`patient_name`/`author_name` |
| direction | asc\|desc | desc | |
| search | string | null | LIKE `%search%` on `patient_name`/`author_name` |
| filter[patient_id] | int | null | filter per pasien |

**Behavior**: default exclude soft-deleted (`SoftDeletes` global scope). Index `(tenant_id, deleted_at)`. Load `patient`+`author`+`booking` (eager).

**Response 200**:

```json
{
  "data": [
    {
      "id": 1,
      "booking_id": 12,
      "patient_id": 5,
      "patient_name": "Siti Aminah",
      "author_id": 2,
      "author_name": "dr. Andi",
      "booking_status": "done",
      "booking_start_at": "2026-08-14T09:00:00+00:00",
      "soap_summary": "S: keluh... O: ... A: ... P: ...",
      "created_at": "2026-08-14T10:00:00+00:00",
      "updated_at": "2026-08-14T10:30:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 }
}
```

### Show medical record — `GET /{tenant}/clinic/medical-records/{medicalRecord}`

**Permission**: `medical_record` `r`. Load `patient`+`author`+`booking`+`treatmentRecords`+`medicalPhotos`.

**Response 200**: `{ "data": MedicalRecordResource(full SOAP + treatments + photos), "meta": [] }`

**404**: record tidak ditemukan di tenant (TenantScope) atau soft-deleted.

### Create medical record — `POST /{tenant}/clinic/medical-records`

**Permission**: `medical_record` `w` (admin/dokter/terapis — FR-044).

**Body**:

```json
{
  "booking_id": 12,
  "subjective": "Pasien keluh...",
  "objective": "Pemeriksaan...",
  "assessment": "Diagnosa...",
  "plan": "Rencana..."
}
```

- `booking_id` required|exists:bookings,id — guard: booking status=done (FR-033/040), unique (R10/FR-088) di Service.
- `subjective`/`objective`/`assessment`/`plan` nullable|string (SOAP, draf boleh parsial).
- `patient_id` tidak di body — otomatis dari `booking->patient_id` (immutable, R4).
- `author_id` otomatis dari auth user.

**Side effect (R5)**: `MedicalRecordController::store` → `MedicalRecordService::create()` → `CreateMedicalRecordAction` → `LogAuditAction` event `medical_record.created`, narasi "Mengisi rekam medis pasien {patient}", properties full SOAP.

**Response 201**: `{ "data": MedicalRecordResource, "meta": { "message": "Rekam medis berhasil diisi." } }`

**422**: booking non-done (`medical_record.booking_not_done`), sudah ada rekam medis (`medical_record.already_exists`), validasi gagal.

### Update medical record — `PATCH /{tenant}/clinic/medical-records/{medicalRecord}`

**Permission**: `medical_record` `w` (admin/dokter/terapis — FR-044).

**Body**:

```json
{
  "subjective": "Diperbarui...",
  "objective": "...",
  "assessment": "...",
  "plan": "..."
}
```

- 4 field SOAP nullable|string. `booking_id`/`patient_id` tidak di-accept (immutable).

**Side effect (R5)**: `MedicalRecordController::update` → `MedicalRecordService::update()` → `UpdateMedicalRecordAction` → `LogAuditAction` event `medical_record.updated`, narasi "Memperbarui rekam medis pasien {patient}", properties old/new diff SOAP.

**Response 200**: `{ "data": MedicalRecordResource(updated), "meta": { "message": "Rekam medis berhasil diperbarui." } }`

### Soft-delete medical record — `DELETE /{tenant}/clinic/medical-records/{medicalRecord}`

**Permission**: `medical_record` `w` (admin — Policy `delete`).

**Behavior**: soft-delete (FR-090) — set `deleted_at`, bukan hard-delete. `MedicalRecordController::destroy` → `MedicalRecordService::softDelete()` → `SoftDeleteMedicalRecordAction` → `LogAuditAction` event `medical_record.deleted`, narasi "Menghapus rekam medis pasien {patient}". Hard-delete permanen tidak diekspos; DB restrict FK child (`medical_record_id`) memblokir bila treatment/photo ada (FR-092). Treatment/photo tetap utuh saat parent soft-delete (FR-091).

**Response 200**: `{ "data": MedicalRecordResource(deleted_at terisi), "meta": { "message": "Rekam medis berhasil dihapus." } }`

### Patient medical records history — `GET /{tenant}/clinic/patients/{patient}/medical-records`

**Permission**: `medical_record` `r`.

**Behavior**: riwayat rekam medis pasien kronologis (FR-022), query `WHERE tenant_id=? AND patient_id=? ORDER BY created_at`, index `(tenant_id, patient_id, created_at)` (R3). Load `author`+`booking`.

**Response 200**:

```json
{
  "data": [
    {
      "id": 1,
      "author_name": "dr. Andi",
      "booking_start_at": "2026-08-14T09:00:00+00:00",
      "soap_summary": "S: ... O: ... A: ... P: ...",
      "created_at": "2026-08-14T10:00:00+00:00"
    }
  ],
  "meta": []
}
```

### Add treatment (existing, tidak berubah) — `POST /{tenant}/clinic/medical-records/{medicalRecord}/treatments`

Detail di spec langkah 15 (`treatment_records`). Tidak diubah di 009.

### Add photo (existing, tidak berubah) — `POST /{tenant}/clinic/medical-records/{medicalRecord}/photos`

Detail di spec langkah 16 (`medical_photos`). Tidak diubah di 009.

## Resource shape — `MedicalRecordResource` (revisi)

```json
{
  "id": 1,
  "booking_id": 12,
  "patient_id": 5,
  "patient_name": "Siti Aminah",
  "author_id": 2,
  "author_name": "dr. Andi",
  "booking": { "id": 12, "status": "done", "start_at": "2026-08-14T09:00:00+00:00" },
  "subjective": "...",
  "objective": "...",
  "assessment": "...",
  "plan": "...",
  "treatments": [{ "id": 1, "service_name": "Facial", "notes": "..." }],
  "photos": [{ "id": 1, "type": "before", "type_label": "Sebelum", "path": "...", "url": "..." }],
  "deleted_at": null,
  "created_at": "2026-08-14T10:00:00+00:00",
  "updated_at": "2026-08-14T10:30:00+00:00"
}
```

`patient_name` (R6), `deleted_at` (R1), `updated_at` baru — sebelumnya resource hanya `created_at` + `author_name`. List view pakai `soap_summary` (concat truncate 4 field) untuk ringkas.
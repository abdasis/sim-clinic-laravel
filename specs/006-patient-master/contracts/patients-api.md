# API Contracts — Master Pasien Klinik (006-patient-master)

**Date**: 2026-08-14 | **Data model**: [data-model.md](../data-model.md)

Semua endpoint tenant-scoped, prefix `/{tenant}/clinic`, middleware `resolve.tenant` + `ensure.tenant.active` + `auth:sanctum`. Otorisasi `PatientPolicy` → Gate `clinic.access` (`ponytail:` exception konstitusi v1.1.0 VI — role klinik statik fixed; lihat [data-model.md](../data-model.md#permission)). Response shape `{ data, meta }`; error `{ message, errors }` (422) / `{ message }` (403/404).

**Layering (konstitusi v1.1.0 VI + CLAUDE.md)**: Controller→Service→Action. Controller `authorize` + resolve `PatientRequest` + panggil `PatientService` + bentuk response — **tidak menyentuh DB & tidak langsung ke Action**. `PatientService` orkestrasi (panggil Action + duplicate detection, no DB write). DB write (create/update/deactivate) WAJIB via Action di `app/Actions/Patient/` (`Create/Update/DeactivatePatientAction`). Setiap Action ubah-data WAJIB log via `LogAuditAction` (`activity()`) dengan `withProperties` — create→full attributes, update→old+new, deactivate→old+new. Validasi WAJIB via `PatientRequest` (dilarang inline validation di Controller). Read (index/show/history) di Controller langsung (read exception). Exception ditangkap WAJIB `Log::error` sebelum re-throw.

## Endpoints

### List patients — `GET /{tenant}/clinic/patients`

**Permission**: `patient` `r` (admin, doctor, cashier; therapist view only).

**Query params** (DataTable, `InteractsWithDataTable`):

| Param | Type | Default | Catatan |
|-------|------|---------|---------|
| page | int | 1 | |
| per_page | int | 10 | max 100 |
| sort | string | null | `name`/`phone`/`created_at` |
| direction | asc\|desc | asc | |
| search | string | null | LIKE `%search%` on `name` + `phone` |

**Behavior**: `SoftDeletes` global scope otomatis exclude pasien nonaktif (`deleted_at NOT NULL`) — list hanya pasien aktif (FR-026). Tidak ada filter `status` eksplisit (beda dari service); pasien nonaktif tidak diekspos di list MVP.

**Response 200**:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Siti Aminah",
      "birth_date": "1995-03-12",
      "gender": "female",
      "phone": "08123456789",
      "whatsapp": "08123456789",
      "address": "Jl. Mawar No. 1",
      "notes": "Alergi penisilin",
      "deleted_at": null,
      "created_at": "2026-08-14T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 }
}
```

### Create patient — `POST /{tenant}/clinic/patients`

**Permission**: `patient` `w` (admin, doctor, cashier).

**Body**:

```json
{
  "name": "Siti Aminah",
  "birth_date": "1995-03-12",
  "gender": "female",
  "phone": "08123456789",
  "whatsapp": "08123456789",
  "address": "Jl. Mawar No. 1",
  "notes": "Alergi penisilin"
}
```

- `name` required|string|max:255
- `phone` required|string|max:50
- `birth_date` nullable|date|before:today
- `gender` nullable|in:male,female,other
- `whatsapp` nullable|string|max:50
- `address` nullable|string
- `notes` nullable|string

**Side effect**: Controller→`PatientService::create`→`CreatePatientAction` (`app/Actions/Patient/`) → `Patient::create()` + `LogAuditAction` event `patient.created`, narasi "Membuat pasien Siti Aminah", `withProperties` = `tenant_id` + **full attributes**. Duplicate detection di Service. DB write di Action (konstitusi v1.1.0 VI).

**Duplicate detection (FR-021/023)**: bila `phone` sama dengan pasien lain di tenant → tetap 201 (tidak ditolak), `meta.duplicate_warning=true` + `meta.duplicate_patient_id={id lama}`.

**Response 201**:
```json
{ "data": PatientResource, "meta": { "duplicate_warning": false, "duplicate_patient_id": null, "message": "Pasien berhasil ditambahkan." } }
```

**422**: validasi gagal (name/phone kosong, birth_date masa depan, gender invalid).

### Show patient — `GET /{tenant}/clinic/patients/{patient}`

**Permission**: `patient` `r`.

**Behavior**: resolve `withTrashed` (R5) — pasien nonaktif tetap dapat diambil (untuk riwayat/detail).

**Response 200**: `{ "data": PatientResource, "meta": [] }`

### Update patient — `PUT /{tenant}/clinic/patients/{patient}`

**Permission**: `patient` `w` (admin, doctor, cashier).

**Body**: sama dengan create (validasi sama untuk field yang dikirim).

**Side effect**: Controller→`PatientService::update`→`UpdatePatientAction` (`app/Actions/Patient/`) → `$patient->update()` + `LogAuditAction` event `patient.updated`, narasi "Memperbarui pasien {name}", `withProperties` = `tenant_id` + **`old` (nilai sebelum) + `new` (nilai setelah)**. Duplicate detection di Service. DB write di Action (konstitusi v1.1.0 VI).

**Duplicate detection (FR-021)**: bila `phone` baru sama dengan pasien lain di tenant → tetap 200, `meta.duplicate_warning=true` + `meta.duplicate_patient_id`.

**Response 200**:
```json
{ "data": PatientResource, "meta": { "duplicate_warning": false, "duplicate_patient_id": null, "message": "Pasien berhasil diperbarui." } }
```

### Deactivate patient — `DELETE /{tenant}/clinic/patients/{patient}`

**Permission**: `patient` `w` (admin, doctor, cashier). `PatientPolicy::delete` delegasi `clinic.access` ['patient', 'w'].

**Behavior**: tidak hard-delete. Controller→`PatientService::deactivate`→`DeactivatePatientAction` (`app/Actions/Patient/`) → `$patient->delete()` (soft delete, set `deleted_at`) + `LogAuditAction` event `patient.deactivated`, narasi "Menonaktifkan pasien {name}", `withProperties` = `tenant_id` + **`old` (`deleted_at:null`) + `new` (`deleted_at:{timestamp}`)**. DB write di Action (konstitusi v1.1.0 VI).

**Response 200**: `{ "data": PatientResource(deleted_at terisi), "meta": { "message": "Pasien berhasil dinonaktifkan." } }`

**Catatan**: Hard-delete permanen **tidak diekspos endpoint** (R3). DB `restrictOnDelete` pada 3 FK tetap memblokir hard-delete via path internal (artisan/manual) bila pasien masih direferensi — dibuktikan via test, bukan endpoint.

### Patient history — `GET /{tenant}/clinic/patients/{patient}/history`

**Permission**: `patient` `r`.

**Behavior**: resolve `withTrashed` (R5) — riwayat pasien nonaktif tetap dapat diakses (FR-022/028). Agregasi booking + treatment terurut kronologis.

**Response 200**:
```json
{
  "data": [
    {
      "date": "2026-08-10T09:00:00+00:00",
      "service_name": "Facial Glow",
      "status": "done",
      "assignee_name": "dr. Andi",
      "type": "booking"
    }
  ],
  "meta": []
}
```

## Resource shape — PatientResource

```json
{
  "id": 1,
  "name": "Siti Aminah",
  "birth_date": "1995-03-12",
  "gender": "female",
  "phone": "08123456789",
  "whatsapp": "08123456789",
  "address": "Jl. Mawar No. 1",
  "notes": "Alergi penisilin",
  "deleted_at": null,
  "created_at": "2026-08-14T10:00:00+00:00"
}
```

`deleted_at`: `null` = aktif; ISO-8601 = nonaktif. FE dapat gunakan untuk penanda status.

## Error contract

| Status | Kapan |
|--------|-------|
| 403 | role tanpa izin (therapist pada write; atau module tidak ada di matriks role) |
| 404 | tenant slug tidak dikenal / patient id tidak milik tenant (TenantScope). Catatan: `history`/`show` resolve `withTrashed` jadi pasien nonaktif tidak 404 |
| 422 | validasi body gagal |
| 423 | tenant Inactive (middleware `ensure.tenant.active`) |

## Route changes

- Hapus `->except(['destroy'])` pada `apiResource('patients')` ATAU daftar `Route::delete('patients/{patient}', [PatientController::class, 'destroy'])` eksplisit — agar `destroy` (nonaktifkan) tersedia.
- `history`/`show` binding resolve `withTrashed` (R5) — implementasi di Controller method (query `Patient::withTrashed()->findOrFail($id)` dengan TenantScope aktif) atau explicit route binding.
- `PatientController::store/update/destroy` panggil `PatientService` (R4) → Service panggil `Create/Update/DeactivatePatientAction` — tidak ada `Patient::create()`/`->update()`/`->delete()` di Controller/Service (Controller→Service→Action).
- `index`/`show`/`history` query read langsung di Controller (read exception CLAUDE.md) — `history`/`show` resolve `withTrashed`.
- Validasi field pasien WAJIB via `PatientRequest` (`app/Http/Requests`), dilarang inline validation di Controller.

## Tidak ada kontrak entity baru

Endpoint `apiResource('patients')` sudah ada (minus destroy); kontrak ini dokumentasi + perubahan behavior (soft delete, duplicate di update, withTrashed history) + side effect activity log + route `destroy` baru. Tidak ada endpoint/entity baru.
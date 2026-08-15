# API Contracts — Booking & Jadwal Klinik (008-booking-schedule)

**Date**: 2026-08-14 | **Data model**: [data-model.md](../data-model.md)

Semua endpoint tenant-scoped, prefix `/{tenant}/clinic`, middleware `resolve.tenant` + `ensure.tenant.active` + `auth:sanctum`. Otorisasi `BookingPolicy` → Gate `clinic.access` → permission spatie `booking.view`/`booking.manage`. Response shape `{ data, meta }`; error `{ message, errors }` (422) / `{ message }` (403/404).

## Endpoints

### List bookings — `GET /{tenant}/clinic/bookings`

**Permission**: `booking` `r` (admin, doctor, therapist). Cashier 403.

**Query params** (DataTable, `InteractsWithDataTable`):

| Param | Type | Default | Catatan |
|-------|------|---------|---------|
| page | int | 1 | |
| per_page | int | 10 | max 100 |
| sort | string | start_at | `start_at`/`status`/`created_at`/dll |
| direction | asc\|desc | desc | default `start_at desc` |
| search | string | null | (tidak ada search LIKE eksplisit saat ini) |
| filter[status] | pending\|confirmed\|done\|cancelled | null | filter status |
| filter[assignee_id] | int | null | filter penanggung jawab |

**Response 200**:

```json
{
  "data": [ BookingResource ],
  "meta": { "current_page": 1, "per_page": 10, "total": 25, "last_page": 3 }
}
```

### Create booking — `POST /{tenant}/clinic/bookings`

**Permission**: `booking` `w` (admin, doctor, therapist).

**Body**:

```json
{
  "patient_id": 2,
  "service_id": 3,
  "assignee_id": 4,
  "start_at": "2026-08-15T10:00",
  "end_at": "2026-08-15T11:00",
  "notes": "Catatan opsional"
}
```

- `patient_id` required|exists:patients,id (tenant-scoped)
- `service_id` required|exists:services,id
- `assignee_id` required|exists:users,id + role doctor/therapist (FR-036)
- `start_at` required|date|after:now
- `end_at` required|date|after:start_at
- `notes` nullable|string

**Side effect**: `CreateBookingAction` → `LogAuditAction` event `booking.created`, narasi "Membuat booking {layanan} untuk {pasien} pada {tanggal}.". Status awal `pending`.

**Overlap**: setelah create, `BookingOverlapService::detect` mengembalikan `overlap_warnings` (FR-035) — non-blocking.

**Response 201**:

```json
{
  "data": BookingResource,
  "meta": {
    "overlap_warnings": [
      { "booking_id": 9, "patient_name": "Budi", "start_at": "...", "end_at": "..." }
    ],
    "message": "Booking berhasil dibuat."
  }
}
```

**422**: validasi gagal (waktu, role assignee, field kosong).

### Show booking — `GET /{tenant}/clinic/bookings/{booking}`

**Permission**: `booking` `r`.

**Response 200**: `{ "data": BookingResource, "meta": [] }` ( BookingResource dengan `has_medical_record` eager-loaded).

### Update booking — `PUT/PATCH /{tenant}/clinic/bookings/{booking}`

**Permission**: `booking` `w`.

**Body**: sama dengan create (field optional-ish; validasi sama untuk field yang dikirim).

**Immutability (FR-037)**: bila body mengandung `patient_id` berbeda dari existing AND `booking.medicalRecord` exists → 422 pada field `patient_id`, error `booking.patient_immutable` ("Pasien tidak dapat diubah karena rekam medis sudah ada."). Defense in depth: `UpdateBookingAction` juga guard (R2/R6).

**Side effect**: `UpdateBookingAction` → `LogAuditAction` event `booking.updated`, narasi "Memperbarui booking {pasien}.", properties `old`/`new` diff.

**Overlap**: `BookingOverlapService::detect` mengembalikan `overlap_warnings` (FR-035) — non-blocking.

**Response 200**:

```json
{
  "data": BookingResource,
  "meta": {
    "overlap_warnings": [ ... ],
    "message": "Booking berhasil diperbarui."
  }
}
```

**422**: validasi gagal / immutability `patient_id` saat rekam medis ada.

### Change status — `PATCH /{tenant}/clinic/bookings/{booking}/status`

**Permission**: `booking` `w`.

**Body**:

```json
{ "status": "confirmed" }
```

- `status` required|enum(pending, confirmed, done, cancelled)

**State transition (FR-031)**: `BookingStatus::canTransitionTo` — `pending`→`confirmed`/`cancelled`, `confirmed`→`done`/`cancelled`, `done`→(final), `cancelled`→(final). Transisi ilegal → 422 `clinic.invalid_transition`.

**Side effect**: `ChangeBookingStatusAction` set `status` + `status_changed_at` → `LogAuditAction` event `booking.status_changed`, narasi "Status booking {pasien} diubah dari {lama} ke {baru}.", properties `old: {status}`, `new: {status}`.

**Response 200**: `{ "data": BookingResource, "meta": { "message": "Status booking berhasil diperbarui." } }`

**422**: transisi ilegal (`done`→`cancelled`, `cancelled`→apapun, dll).

### Schedule (jadwal) — `GET /{tenant}/clinic/bookings/schedule`

**Permission**: `booking` `r`.

**Query params**:

| Param | Type | Default | Catatan |
|-------|------|---------|---------|
| from | date | required | awal rentang |
| to | date | required, after_or_equal:from | akhir rentang |
| view | day\|week | null | hint FE (tidak memengaruhi query) |

**Behavior**: booking dengan `start_at` dalam `[from, to]`, status ≠ `cancelled`, eager-load `patient`/`service`/`assignee`, urut `start_at` lalu `assignee_id`. Flag `has_medical_record` tidak disertakan (list jadwal tidak edit pasien).

**Response 200**:

```json
{
  "data": [
    {
      "id": 1,
      "patient_name": "Siti Aminah",
      "service_name": "Facial Glow",
      "assignee_id": 4,
      "assignee_name": "dr. Andi",
      "start_at": "2026-08-15T10:00:00+00:00",
      "end_at": "2026-08-15T11:00:00+00:00",
      "status": "confirmed"
    }
  ],
  "meta": []
}
```

### Delete booking — `DELETE /{tenant}/clinic/bookings/{booking}`

**Permission**: `booking` `w`.

**Behavior**: `DeleteBookingAction` hard-delete booking (tidak soft-delete — FR-040, `cancelled` untuk berakhir). DB `restrictOnDelete` pada FK tidak memblokir delete booking (restrict ada di sisi parent: pasien/layanan/assignee). Catatan: delete booking yang punya rekam medis diblokir FK `medical_records.booking_id` restrict (lihat medical_records.md) → 422/QueryException.

**Side effect**: `LogAuditAction` event `booking.deleted`, narasi "Menghapus booking {pasien}.", properties snapshot.

**Response 200**: `{ "data": null, "meta": { "message": "Booking berhasil dihapus." } }`

## Resource shape — BookingResource (revisi R3)

```json
{
  "id": 1,
  "patient_id": 2,
  "patient_name": "Siti Aminah",
  "service_id": 3,
  "service_name": "Facial Glow",
  "assignee_id": 4,
  "assignee_name": "dr. Andi",
  "start_at": "2026-08-15T10:00:00+00:00",
  "end_at": "2026-08-15T11:00:00+00:00",
  "status": "pending",
  "status_label": "Menunggu",
  "notes": null,
  "has_medical_record": false,
  "created_at": "2026-08-14T08:00:00+00:00"
}
```

`has_medical_record` disertakan pada show/update (eager-load `medicalRecord`). Pada `schedule` (list jadwal) tidak disertakan.

## Error contract

| Status | Kapan |
|--------|-------|
| 403 | role tanpa izin (cashier pada semua; doctor/therapist OK) |
| 404 | tenant slug tidak dikenal / booking id tidak milik tenant (TenantScope) |
| 422 | validasi body gagal / transisi status ilegal (FR-031) / immutability `patient_id` saat rekam medis ada (FR-037) |
| 423 | tenant Inactive (middleware `ensure.tenant.active`) |

## Tidak ada endpoint baru

Semua endpoint (`apiResource('bookings')` + `bookings/schedule` + `bookings/{booking}/status`) sudah ada di `routes/api.php`. Kontrak ini dokumentasi + perubahan behavior (immutability 422, resource flag `has_medical_record`) + side effect audit. Tidak ada route/endpoint baru.
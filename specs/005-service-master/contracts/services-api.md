# API Contracts — Master Layanan Klinik (005-service-master)

**Date**: 2026-08-14 | **Data model**: [data-model.md](../data-model.md)

Semua endpoint tenant-scoped, prefix `/{tenant}/clinic`, middleware `resolve.tenant` + `ensure.tenant.active` + `auth:sanctum`. Otorisasi `ServicePolicy` → Gate `clinic.access`. Response shape `{ data, meta }`; error `{ message, errors }` (422) / `{ message }` (403/404).

## Endpoints

### List services — `GET /{tenant}/clinic/services`

**Permission**: `service` `r` (admin, doctor, therapist).

**Query params** (DataTable, `InteractsWithDataTable`):

| Param | Type | Default | Catatan |
|-------|------|---------|---------|
| page | int | 1 | |
| per_page | int | 10 | max 100 |
| sort | string | null | `name`/`price`/`status`/`created_at` |
| direction | asc\|desc | asc | |
| search | string | null | LIKE `%search%` on `name` |
| filter[status] | active\|archived | **active** | R3: default hanya active; kirim `archived` untuk lihat arsip, `all` untuk semua |

**Behavior change (R3)**: bila `filter[status]` tidak dikirim → query `where status = 'active'` (hide arsip dari konsumen options). Halaman master kirim filter eksplisit untuk lihat arsip.

**Response 200**:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Facial Glow",
      "description": "Perawatan wajah glow",
      "price": "350000.00",
      "status": "active",
      "status_label": "Aktif",
      "created_at": "2026-08-14T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 }
}
```

### Create service — `POST /{tenant}/clinic/services`

**Permission**: `service` `w` (admin only).

**Body**:

```json
{ "name": "Facial Glow", "description": "Perawatan wajah glow", "price": 350000, "status": "active" }
```

- `name` required|string|max:255
- `description` nullable|string
- `price` required|numeric|gte:0
- `status` nullable|enum(active, archived), default `active`

**Side effect**: `LogAuditAction` event `service.created`, narasi "Membuat layanan Facial Glow".

**Response 201**: `{ "data": ServiceResource, "meta": { "message": "Layanan berhasil ditambahkan." } }`

**422**: validasi gagal (price negatif, name kosong).

### Show service — `GET /{tenant}/clinic/services/{service}`

**Permission**: `service` `r`.

**Response 200**: `{ "data": ServiceResource, "meta": [] }`

### Update service — `PUT /{tenant}/clinic/services/{service}`

**Permission**: `service` `w` (admin).

**Body**: sama dengan create (semua field optional-ish, validasi sama untuk field yang dikirim).

**Side effect**: `LogAuditAction` event `service.updated`, narasi "Memperbarui layanan {name}".

**Response 200**: `{ "data": ServiceResource, "meta": { "message": "Layanan berhasil diperbarui." } }`

### Archive service — `DELETE /{tenant}/clinic/services/{service}`

**Permission**: `service` `w` (admin).

**Behavior**: tidak hard-delete. Memanggil `ArchiveServiceAction` → set `status=archived` + `LogAuditAction` event `service.archived`, narasi "Mengarsipkan layanan {name}".

**Response 200**: `{ "data": ServiceResource(status=archived), "meta": { "message": "Layanan berhasil diarsipkan." } }`

**Catatan**: Hard-delete permanen **tidak diekspos endpoint** (R2). DB `restrictOnDelete` pada 3 FK tetap memblokir hard-delete via path internal (artisan/manual) bila layanan masih direferensi — dibuktikan via test, bukan endpoint.

## Resource shape — ServiceResource

```json
{
  "id": 1,
  "name": "Facial Glow",
  "description": "...",
  "price": "350000.00",
  "status": "active",
  "status_label": "Aktif",
  "created_at": "ISO-8601"
}
```

## Error contract

| Status | Kapan |
|--------|-------|
| 403 | role tanpa izin (cashier, atau doctor pada write) |
| 404 | tenant slug tidak dikenal / service id tidak milik tenant (TenantScope) |
| 422 | validasi body gagal |
| 423 | tenant Inactive (middleware `ensure.tenant.active`) |

## Tidak ada kontrak baru

Endpoint `apiResource('services')` sudah ada; kontrak ini dokumentasi + perubahan behavior `index` (default active filter) + side effect activity log. Tidak ada endpoint/route baru.
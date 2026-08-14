# API Contracts — Master Produk Klinik (007-product-master)

**Date**: 2026-08-14 | **Data model**: [data-model.md](../data-model.md)

Semua endpoint tenant-scoped, prefix `/{tenant}/clinic`, middleware `resolve.tenant` + `ensure.tenant.active` + `auth:sanctum`. Otorisasi `ProductPolicy` → Gate `clinic.access` modul `product`. Response shape `{ data, meta }`; error `{ message, errors }` (422) / `{ message }` (403/404).

Endpoint `apiResource('products')` + nested `products/{product}/stock-movements` sudah ada. Kontrak ini dokumentasi + **perubahan behavior** (R3, R8) + **side effect activity log** (R4). Tidak ada endpoint/route baru.

## Endpoints

### List products — `GET /{tenant}/clinic/products`

**Permission**: `product` `r` (admin; per matriks `ClinicPermission`).

**Query params** (DataTable, `InteractsWithDataTable`):

| Param | Type | Default | Catatan |
|-------|------|---------|---------|
| page | int | 1 | |
| per_page | int | 10 | max 100 |
| sort | string | null | `name`/`stock_balance`/`min_threshold`/`price`/`status`/`created_at` |
| direction | asc\|desc | asc | |
| search | string | null | LIKE `%search%` on `name` |
| filter[status] | active\|archived\|all | **active** | R8: default hanya active; `archived` lihat arsip, `all` semua |

**Behavior change (R8)**: bila `filter[status]` tidak dikirim → query `where status = 'active'` (hide arsip dari konsumen options: POS, inventory dropdown). Halaman master kirim filter eksplisit (`all`/`archived`) untuk lihat arsip.

**Response 200**:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Serum Vitamin C",
      "unit": "botol",
      "stock_balance": 3,
      "min_threshold": 5,
      "price": "150000.00",
      "status": "active",
      "status_label": "Aktif",
      "is_low_stock": true,
      "created_at": "2026-08-14T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 }
}
```

### Create product — `POST /{tenant}/clinic/products`

**Permission**: `product` `w` (admin).

**Body** (R3 — tanpa `stock_balance`):

```json
{ "name": "Serum Vitamin C", "unit": "botol", "min_threshold": 5, "price": 150000 }
```

- `name` required|string|max:255
- `unit` required|string|max:50
- `min_threshold` required|integer|gte:0
- `price` required|numeric|gte:0
- `status` nullable|enum(active, archived), default `active`
- `stock_balance` **tidak diterima** — saldo diawali 0 (FR-060). Bila dikirim, diabaikan (tidak divalidasi).

**Side effect (R4)**: `ProductController::store` → `ProductService::create()` → `CreateProductAction` → `LogAuditAction` event `product.created`, narasi "Membuat produk {name}", properties full attributes.

**Response 201**: `{ "data": ProductResource(stock_balance=0), "meta": { "message": "Produk berhasil ditambahkan." } }`

**422**: validasi gagal (price negatif, name/unit kosong).

### Show product — `GET /{tenant}/clinic/products/{product}`

**Permission**: `product` `r`.

**Response 200**: `{ "data": ProductResource, "meta": [] }`

### Update product — `PUT /{tenant}/clinic/products/{product}`

**Permission**: `product` `w` (admin).

**Body**: sama dengan create (field optional, validasi sama untuk field dikirim). `stock_balance` tidak diterima — saldo tidak berubah via endpoint ini (FR-063).

**Side effect (R4)**: `ProductController::update` → `ProductService::update()` → `UpdateProductAction` → `LogAuditAction` event `product.updated`, narasi "Memperbarui produk {name}", properties old/new diff.

**Response 200**: `{ "data": ProductResource, "meta": { "message": "Produk berhasil diperbarui." } }`

### Archive product — `DELETE /{tenant}/clinic/products/{product}`

**Permission**: `product` `w` (admin).

**Behavior**: tidak hard-delete. `ProductController::destroy` → `ProductService::archive()` → `ArchiveProductAction` → set `status=archived` + `LogAuditAction` event `product.archived`, narasi "Mengarsipkan produk {name}", properties status old→new.

**Response 200**: `{ "data": ProductResource(status=archived), "meta": { "message": "Produk berhasil diarsipkan." } }`

**Catatan**: Hard-delete permanen **tidak diekspos endpoint** (R7). DB `restrictOnDelete` (R2) memblokir hard-delete via path internal bila produk direferensi — dibuktikan via test.

### Stock movement (existing, tidak berubah kontrak)

- `POST /{tenant}/clinic/products/{product}/stock-movements` — catat mutasi via `StockService::adjust()`. Body `{type, quantity, note}`. `type` ∈ {`in`, `out_manual`} (manual); `sold_pos`/`rollback` internal dari transaksi. Response 201 + movement shape.
- `GET /{tenant}/clinic/products/{product}/stock-movements` — riwayat mutasi per produk (FR-064), paginasi, terurut `created_at` desc.

## Resource shape — ProductResource

```json
{
  "id": 1,
  "name": "Serum Vitamin C",
  "unit": "botol",
  "stock_balance": 3,
  "min_threshold": 5,
  "price": "150000.00",
  "status": "active",
  "status_label": "Aktif",
  "is_low_stock": true,
  "created_at": "ISO-8601"
}
```

## Error contract

| Status | Kapan |
|--------|-------|
| 403 | role tanpa izin (`product` r/w) |
| 404 | tenant slug tidak dikenal / product id tidak milik tenant (TenantScope) |
| 422 | validasi body gagal (price negatif, name/unit kosong) |
| 423 | tenant Inactive (middleware `ensure.tenant.active`) |
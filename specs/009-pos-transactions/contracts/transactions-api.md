# API Contracts — Transaksi POS & Pembayaran (009-pos-transactions)

**Date**: 2026-08-14 | **Data model**: [data-model.md](../data-model.md)

Semua endpoint tenant-scoped, prefix `/{tenant}/clinic`, middleware `resolve.tenant` + `ensure.tenant.active` + `auth:sanctum`. Otorisasi `TransactionPolicy` → Gate `clinic.access` → permission spatie `transaction.view`/`transaction.manage`; `InvoicePolicy` → `invoice.view`. Response shape `{ data, meta }`; error `{ message, errors }` (422) / `{ message }` (403/404). Role diizinkan: admin + cashier (doctor/therapist 403).

## Endpoints

### List transactions — `GET /{tenant}/clinic/transactions`

**Permission**: `transaction` `r` (admin, cashier).

**Query params** (DataTable, `InteractsWithDataTable`):

| Param | Type | Default | Catatan |
|-------|------|---------|---------|
| page | int | 1 | |
| per_page | int | 10 | max 100 |
| sort | string | created_at | `invoice_number`/`subtotal`/`payment_status`/`created_at` |
| direction | asc\|desc | desc | |
| search | string | null | LIKE `invoice_number` |
| filter[payment_status] | unpaid\|partially_paid\|paid | null | filter status pembayaran |
| filter[cancelled] | 0\|1 | null | 1 = transaksi batal (default: exclude batal? — tampilkan semua, FE filter) |

Soft-deleted (`deleted_at` not null) otomatis di-exclude (Eloquent `SoftDeletes`). Pembatalan (`cancelled_at`) tetap tampil (badge batal).

**Response 200**:

```json
{
  "data": [ TransactionResource ],
  "meta": { "current_page": 1, "per_page": 10, "total": 25, "last_page": 3 }
}
```

### Create transaction — `POST /{tenant}/clinic/transactions`

**Permission**: `transaction` `w` (admin, cashier).

**Body**:

```json
{
  "patient_id": 2,
  "booking_id": 5,
  "items": [
    { "service_id": 3, "qty": 1 },
    { "product_id": 7, "qty": 2 }
  ]
}
```

- `patient_id` required|exists:patients,id (FR-050)
- `booking_id` nullable|exists:bookings,id + harus booking `done` (FR-044)
- `items` required|array|min:1; tiap item `qty` >0, tepat satu dari `product_id`/`service_id` (exclusive-arc FR-049)
- Stok produk cukup (FR-053) — validasi di Service

**Side effect**: `TransactionService::create` dalam DB transaction: snapshot nama/harga item (FR-056), generate `invoice_number` race-safe (FR-042), kurangi stok produk via `StockService::adjust(SoldPos)` (FR-052), buat `Invoice` (`issued_at`), audit log `transaction.created`.

**Response 201**:

```json
{
  "data": TransactionResource,
  "meta": { "message": "Transaksi berhasil dibuat." }
}
```

**422**: `patient_id` kosong; `booking_id` booking belum done; exclusive-arc (item tanpa produk+layanan / keduanya); `qty` <=0; stok tidak cukup.

### Show transaction — `GET /{tenant}/clinic/transactions/{transaction}`

**Permission**: `transaction` `r`.

**Response 200**: `{ "data": TransactionResource (items eager-loaded), "meta": [] }`.

### Delete (soft-delete) transaction — `DELETE /{tenant}/clinic/transactions/{transaction}`

**Permission**: `transaction` `w` (`transaction.manage`).

Soft-delete — tandai `deleted_at`, transaksi tidak muncul di daftar aktif, record tetap audit. Bukan pembatalan (stok tidak di-rollback). Audit log `transaction.deleted`.

**Response 200**: `{ "data": TransactionResource, "meta": { "message": "Transaksi diarsipkan." } }`.

### Record payment — `POST /{tenant}/clinic/transactions/{transaction}/payments`

**Permission**: `transaction` `w` (`transaction.manage` — via `update` policy).

**Body**:

```json
{ "method": "cash", "amount": 200000, "paid_at": "2026-08-14T10:00" }
```

- `method` required|enum(cash, transfer, qris, debit) (FR-054)
- `amount` required|numeric|gt:0
- `paid_at` required|date

**Side effect**: `PayTransactionAction` dalam DB transaction: buat `Payment`, update `paid_amount += amount`, turunkan `payment_status` 3-state (FR-055), audit log `transaction.payment_recorded` narasi status lama→baru (FR-057).

**Response 200**:

```json
{
  "data": TransactionResource,
  "meta": {
    "payment_status": "partially_paid",
    "overpaid": false,
    "message": "Pembayaran berhasil dicatat."
  }
}
```

**422**: validasi gagal. `overpaid: true` bila `amount` melebih sisa (peringatan, status tetap `paid`).

### Cancel transaction — `POST /{tenant}/clinic/transactions/{transaction}/cancel`

**Permission**: `transaction` `w` (`transaction.manage` — via `update` policy).

Tandai `cancelled_at` + rollback stok produk via `StockService::adjust(Rollback)` (FR-058) dalam DB transaction. Guard: transaksi sudah batal → 422. Audit log `transaction.cancelled`.

**Response 200**:

```json
{
  "data": TransactionResource,
  "meta": { "message": "Transaksi berhasil dibatalkan." }
}
```

**422**: transaksi sudah dibatalkan (double-cancel).

### Show invoice — `GET /{tenant}/clinic/transactions/{transaction}/invoice`

**Permission**: `invoice` `r` (`invoice.view`).

Render HTML invoice via `InvoiceService::render` (R4 — konten dari relasi: transaction + items + payments + patient + cashier + tenant + issued_at). Bukan JSON — returns `text/html` view (untuk cetak).

**Response 200**: `text/html` (view `invoice`).

## Resource: TransactionResource

Lihat [data-model.md](../data-model.md) section "Resource shape". Field: `id`, `invoice_number`, `patient_id`, `patient_name`, `booking_id`, `booking_label`, `cashier_name`, `subtotal`, `paid_amount`, `balance_due` (computed), `payment_status`, `payment_status_label`, `is_cancelled`, `cancelled_at`, `items` (whenLoaded), `created_at`.

## Error shapes

- **403**: `{ "message": "This action is unauthorized." }` — role tanpa izin modul transaction/invoice.
- **404**: `{ "message": "Not found." }` — transaksi tidak ada / lintas-tenant (TenantScope).
- **422**: `{ "message": "...", "errors": { "patient_id": ["..."], "items.0": ["..."] } }` — validasi + exclusive-arc + booking-done + stok.
- **500**: race invoice_number lolot advisory lock + unique constraint → ditangani retry 1x di Service (jarang; bila persist, 500 dengan konteks log).
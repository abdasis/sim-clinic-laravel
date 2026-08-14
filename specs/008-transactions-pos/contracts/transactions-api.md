# API Contracts — Transaksi POS & Pembayaran (008-transactions-pos)

**Date**: 2026-08-14 | **Data model**: [data-model.md](../data-model.md)

Semua endpoint tenant-scoped, prefix `/{tenant}/clinic`, middleware `resolve.tenant` + `ensure.tenant.active` + `auth:sanctum`. Otorisasi `TransactionPolicy` → Gate `clinic.access` modul `transaction`. Response shape `{ data, meta }`; error `{ message, errors }` (422) / `{ message }` (403/404/409).

Route eksisting: `apiResource('transactions')->only(['index','store','show'])` + `POST transactions/{transaction}/payments` + `POST transactions/{transaction}/cancel` + `GET transactions/{transaction}/invoice`. **Perubahan**: +`DELETE transactions/{transaction}` (soft-delete, FR-081). Invoice render dari transaction (F0 merge, R7). Tidak ada route update/hard-delete/restore.

## Endpoints

### List transactions — `GET /{tenant}/clinic/transactions`

**Permission**: `transaction` `r` (admin/cashier per matriks `ClinicPermission`).

**Query params** (DataTable, `InteractsWithDataTable`):

| Param | Type | Default | Catatan |
|-------|------|---------|---------|
| page | int | 1 | |
| per_page | int | 10 | max 100 |
| sort | string | null | `invoice_number`/`subtotal`/`paid_amount`/`payment_status`/`created_at` |
| direction | asc\|desc | desc | |
| search | string | null | LIKE `%search%` on `invoice_number` |
| filter[payment_status] | unpaid\|partially_paid\|paid | null | R3: 3-state faceted |

**Behavior**: default exclude soft-deleted (`SoftDeletes` global scope). Index `(tenant_id, deleted_at)`.

**Response 200**:

```json
{
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-20260814-0001",
      "patient_id": 5,
      "patient_name": "Siti Aminah",
      "cashier_id": 2,
      "cashier_name": "Kasir 1",
      "booking_id": null,
      "subtotal": "300000.00",
      "paid_amount": "100000.00",
      "payment_status": "partially_paid",
      "payment_status_label": "Dibayar Sebagian",
      "cancelled_at": null,
      "issued_at": "2026-08-14T10:00:00+00:00",
      "created_at": "2026-08-14T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 }
}
```

### Create transaction — `POST /{tenant}/clinic/transactions`

**Permission**: `transaction` `w` (admin/cashier).

**Body** (R9 exclusive arc):

```json
{
  "patient_id": 5,
  "booking_id": null,
  "items": [
    { "service_id": 3, "qty": 1 },
    { "product_id": 7, "qty": 2 }
  ]
}
```

- `patient_id` required|exists:patients,id (FR-050)
- `booking_id` nullable|exists:bookings,id; bila terisi, booking status=done (FR-033)
- `items` required|array|min:1
- `items.*.product_id` nullable|exists:products,id — **XOR `service_id`** (R9)
- `items.*.service_id` nullable|exists:services,id — **XOR `product_id`** (R9)
- `items.*.qty` required|integer|gt:0

`cashier_id` otomatis dari auth user (tidak di body). `invoice_number` auto-generate `lockForUpdate` (FR-077). `subtotal` dihitung dari item. `payment_status` awal `unpaid`, `paid_amount` 0. `issued_at` terisi saat create (F0 merge, R7).

**Side effect (R13)**: `TransactionController::store` → `TransactionService::create()` → `LogAuditAction` event `pos.transaction.created`, narasi "Mencatat transaksi {invoice}", properties full attributes. Setiap item produk → `StockService::adjust(sold_pos)` (FR-052); stok kurang → 422 (FR-053).

**Response 201**: `{ "data": TransactionResource(payment_status=unpaid, paid_amount=0), "meta": { "message": "Transaksi berhasil dicatat." } }`

**422**: validasi gagal (item tanpa product+service, qty <=0, stok kurang, booking non-done).

### Show transaction — `GET /{tenant}/clinic/transactions/{transaction}`

**Permission**: `transaction` `r`. Load items + patient + payments.

**Response 200**: `{ "data": TransactionResource(+items, +payments whenLoaded), "meta": [] }`

### Soft-delete transaction — `DELETE /{tenant}/clinic/transactions/{transaction}`

**Permission**: `transaction` `w` (admin).

**Behavior**: soft-delete (FR-081) — set `deleted_at`, bukan hard-delete. `TransactionController::destroy` → `SoftDeleteTransactionAction` → `LogAuditAction` event `pos.transaction.deleted`, narasi "Menghapus transaksi {invoice}". Hard-delete permanen tidak diekspos; DB restrict FK (`transaction_id` payments/items cascade, tapi soft-delete parent tidak trigger FK — child tetap utuh audit).

**Response 200**: `{ "data": TransactionResource(deleted_at terisi), "meta": { "message": "Transaksi berhasil dihapus." } }`

**Catatan**: transaksi dibatalkan pakai `cancel` endpoint (FR-058), berbeda dari soft-delete.

### Pay transaction — `POST /{tenant}/clinic/transactions/{transaction}/payments`

**Permission**: `transaction` `w` (admin/cashier).

**Body**:

```json
{ "method": "cash", "amount": 100000, "paid_at": "2026-08-14T10:05:00+00:00" }
```

- `method` required|enum(PaymentMethod: cash, transfer, qris, debit) (FR-054)
- `amount` required|numeric|gt:0
- `paid_at` required|date

**Side effect (R3, R13)**: `PaymentController::store` → `PayTransactionAction` — DB transaction: `payments()->create` → `transaction->paid_amount += amount` (`lockForUpdate` row transaction) → set `payment_status` (`paid`/`partially_paid`/`unpaid`) → `LogAuditAction` event `pos.payment.created`, narasi "Mencatat pembayaran {invoice} — status {lama}→{baru}", properties old_status/new_status/amount/paid_amount.

**Response 201**: `{ "data": TransactionResource(payment_status + paid_amount updated), "meta": { "message": "Pembayaran berhasil dicatat.", "overpaid": false } }`

**Overpaid**: bila `paid_amount > subtotal` → response `meta.overpaid: true` + pesan peringatan; tidak ada saldo otomatis (FR-055 edge case). Tidak 422 (pembayaran tetap dicatat).

### Cancel transaction — `POST /{tenant}/clinic/transactions/{transaction}/cancel`

**Permission**: `transaction` `w` (admin/cashier).

**Behavior**: `CancelTransactionAction` — guard: tolak bila `cancelled_at` sudah terisi (R10, 409 double-cancel). Rollback stok produk via `StockService::adjust(rollback)` (FR-058) → set `cancelled_at`. `LogAuditAction` event `pos.transaction.cancelled`, narasi "Membatalkan transaksi {invoice}".

**Response 200**: `{ "data": TransactionResource(cancelled_at terisi), "meta": { "message": "Transaksi berhasil dibatalkan." } }`

**409**: transaksi sudah dibatalkan (R10).

### Invoice (render dari transaction) — `GET /{tenant}/clinic/transactions/{transaction}/invoice`

**Permission**: `transaction` `r` (authorize via `TransactionPolicy@view`, bukan `InvoicePolicy` — dihapus F0 merge).

**Behavior**: `InvoiceController::show` → `InvoiceService::render($transaction)` — load items/payments/patient/cashier, baca `issued_at` dari transaction (R7). Return HTML view print.

**Response 200**: HTML (view `invoice`, `Content-Type: text/html`).

## Resource shape — TransactionResource

```json
{
  "id": 1,
  "invoice_number": "INV-20260814-0001",
  "patient_id": 5,
  "patient_name": "Siti Aminah",
  "cashier_id": 2,
  "cashier_name": "Kasir 1",
  "booking_id": null,
  "subtotal": "300000.00",
  "paid_amount": "100000.00",
  "payment_status": "partially_paid",
  "payment_status_label": "Dibayar Sebagian",
  "cancelled_at": null,
  "issued_at": "2026-08-14T10:00:00+00:00",
  "items": [ { "id": 1, "name": "Facial Basic", "unit_price": "200000.00", "qty": 1, "subtotal": "200000.00" } ],
  "payments": [ { "id": 1, "method": "cash", "method_label": "Tunai", "amount": "100000.00", "paid_at": "ISO-8601" } ],
  "created_at": "ISO-8601"
}
```

`paid_amount`, `issued_at`, `booking_id` baru di-expose (R2/R7). `items`/`payments` saat `whenLoaded`.

## Resource shape — PaymentResource (NEW)

```json
{ "id": 1, "method": "cash", "method_label": "Tunai", "amount": "100000.00", "paid_at": "ISO-8601", "created_at": "ISO-8601" }
```

## Error contract

| Status | Kapan |
|--------|-------|
| 403 | role tanpa izin (`transaction` r/w) |
| 404 | tenant slug tidak dikenal / transaction id tidak milik tenant (TenantScope) / soft-deleted tidak muncul |
| 409 | cancel transaksi sudah dibatalkan (R10) |
| 422 | validasi body gagal (item XOR, qty <=0, stok kurang, booking non-done, amount <=0) |
| 423 | tenant Inactive (middleware `ensure.tenant.active`) |
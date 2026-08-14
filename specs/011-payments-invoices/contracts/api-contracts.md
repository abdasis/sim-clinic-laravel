# API Contracts: Integritas Item Transaksi, Pembayaran Cicilan & Cetak Invoice

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Data Model**: [data-model.md](data-model.md)

## Konteks

Endpoint backend inti sudah ada dari spec 008. Spec 011 tidak menambah endpoint baru — hanya migration integritas (CHECK + FK restrict) yang memengaruhi response error. Kontrak ini mendokumentasikan endpoint yang dipakai FE 011 (halaman cicilan + invoice render payments) + response error baru dari constraint.

## Endpoint dipakai FE 011

### 1. Detail transaksi (untuk halaman cicilan)

Sudah ada. FE `pos/transactions/$id.tsx` pakai ini.

```
GET /api/{tenant}/clinic/transactions/{transaction}
```

**Auth**: Bearer token, `clinic.access` gate (`transactions`, `r`).

**Response 200** `{ data: Transaction, meta }`:

```json
{
  "data": {
    "id": 12,
    "invoice_number": "INV-20260814-0001",
    "patient_id": 5,
    "patient_name": "Siti Aminah",
    "cashier_name": "Rudi",
    "booking_id": null,
    "subtotal": "300000.00",
    "paid_amount": "100000.00",
    "outstanding_amount": "200000.00",
    "payment_status": "partially_paid",
    "payment_status_label": "Dibayar Sebagian",
    "issued_at": "2026-08-14T10:00:00+00:00",
    "cancelled_at": null,
    "deleted_at": null,
    "payments": [
      { "id": 1, "method": "cash", "method_label": "Tunai", "amount": "100000.00", "paid_at": "2026-08-14T10:05:00+00:00" }
    ],
    "items": [
      { "id": 1, "name": "Facial Basic", "unit_price": "200000.00", "qty": 1, "subtotal": "200000.00" },
      { "id": 2, "name": "Serum Vitamin C", "unit_price": "50000.00", "qty": 2, "subtotal": "100000.00" }
    ],
    "created_at": "2026-08-14T10:00:00+00:00"
  }
}
```

`payments` + `items` dimuat via `whenLoaded` — `TransactionController@show` sudah `load('items','patient','payments')`.

### 2. Catat pembayaran (cicilan)

Sudah ada. FE `payment-form.tsx` POST ke sini.

```
POST /api/{tenant}/clinic/transactions/{transaction}/payments
```

**Auth**: Bearer token, `clinic.access` gate (`transactions`, `w`) — `PaymentController@store` authorize `update`.

**Request body**:

```json
{
  "method": "cash",
  "amount": 100000,
  "paid_at": "2026-08-14"
}
```

| Field | Validasi |
|-------|----------|
| method | required, Enum(cash,transfer,qris,debit) |
| amount | required, numeric, gt:0 |
| paid_at | required, date |

**Response 200** `{ data: Transaction (refreshed), meta }`:

```json
{
  "data": { /* TransactionResource sama GET detail, load items+patient */ },
  "meta": {
    "payment_status": "partially_paid",
    "overpaid": false,
    "message": "Pembayaran berhasil dicatat."
  }
}
```

- `meta.overpaid`: `true` bila `paid_amount > subtotal` (FR-055 edge case) — FE tampilkan peringatan.
- `meta.payment_status`: status baru setelah pembayaran.

**Response 422** (validasi): `{ message, errors }` — FE `applyServerErrors` map ke form.

### 3. Cetak invoice (server-side print)

Sudah ada. Blade view HTML print.

```
GET /api/{tenant}/clinic/transactions/{transaction}/invoice
```

**Auth**: Bearer token, `TransactionPolicy@view`.

**Response 200**: `text/html` — view `invoice` render dari relasi (R4): tenant, patient, cashier, items, payments, subtotal, invoice_number, issued_at. Tombol print + `@media print`.

**Response 403**: belum berhak. **404**: transaksi tidak ada / soft-deleted.

## Response error baru dari constraint integritas (011)

### CHECK exclusive arc (transaction_items)

Bila insert/update item melanggar exclusive arc langsung di DB (jalur non-UI: seed, job, bug):

**PostgreSQL**: `SQLSTATE[23514]: Check Violation` — query gagal, exception `QueryException`. App-level: tidak tercapai karena app validation mencegah lebih dulu (422). Ini pertahanan terakhir.

**SQLite (test)**: CHECK juga didukung — test bisa verifikasi.

### FK restrict product/service

Bila hapus permanen produk/layanan yang masih dirujuk item:

**PostgreSQL**: `SQLSTATE[23503]: Foreign Key Violation` — `QueryException`. App menangkap → 422/409 dengan pesan "Tidak bisa menghapus {produk/layanan}: masih dirujuk transaksi."

**SQLite**: FK alter tidak didukung → restrict tidak teruji di sqlite. WAJIB `phpunit.pgsql.xml` sebelum rilis.

## Kontrak FE (UI contract)

### Halaman detail transaksi + cicilan (`pos/transactions/$id.tsx`)

- **Breadcrumb**: "Beranda Klinik > Transaksi > {invoice_number}" — item terakhir non-link, parent link ke `pos/transactions`.
- **Header**: invoice_number, pasien, cashier, `StatusBadge(payment_status)`, issued_at.
- **Ringkasan finansial**: subtotal, paid_amount, outstanding_amount — `formatCurrency`, `tabular-nums`.
- **Form catat pembayaran** (`payment-form.tsx`): `FormSelect` method + `FormInput` amount + `FormInput`/`FormDatePicker` paid_at (default hari ini) + `FormSubmit`. `useForm` zod + `applyServerErrors`. Tombol submit → POST payments. Overpaid → alert/peringatan dari `meta.overpaid`.
- **Riwayat pembayaran** (`payment-history.tsx`): `DataTable` atau Table sederhana — method_label, amount, paid_at, terurut. Empty state "Belum ada pembayaran."
- **Aksi**: tombol "Cetak Invoice" → link ke `pos/invoices/$id` (route existing).

### Halaman invoice (`pos/invoices/$id.tsx` — edit)

- **Tambah**: section pembayaran — daftar payments (method_label, amount, paid_at) + total paid + outstanding (bila >0).
- **Sumber data**: `GET transactions/{id}` (sama, sudah load payments).
- **Print**: `window.print()` (existing) + `print:hidden` pada elemen non-cetak.
- **Breadcrumb**: existing (sudah benar).

## i18n keys (lang/id/clinic.php — tambahan bila perlu)

| Key | Value |
|-----|-------|
| `pos.payment_history` | "Riwayat Pembayaran" |
| `pos.record_payment` | "Catat Pembayaran" |
| `pos.overpaid_warning` | "Pembayaran melebihi sisa. Periksa kembali." |
| `pos.no_payments` | "Belum ada pembayaran." |
| `pos.view_invoice` | "Cetak Invoice" |
| `invoice.payments` | "Pembayaran" |
| `invoice.paid_amount` | "Total Dibayar" |
| `invoice.outstanding` | "Sisa" |

Identifier English, value Indonesia semi-formal friendly. `payment_method.*` + `payment_status.*` sudah ada dari 008.
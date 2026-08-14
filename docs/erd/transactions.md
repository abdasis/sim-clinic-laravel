# `transactions`

Penjualan POS treatment & produk (Kasir/POS, US5).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| patient_id | bigint unsigned | FK→patients, not null | FR-050 transaksi dengan pasien |
| booking_id | bigint unsigned | FK→bookings, nullable | FR-033: opsional link dari booking done |
| cashier_id | bigint unsigned | FK→users, not null | kasir pembuat |
| invoice_number | string(50) | unique(tenant), not null | generate: `INV-YYYYMMDD-XXXX` |
| subtotal | decimal(12,2) | not null | sum(item.subtotal) |
| paid_amount | decimal(12,2) | not null, default 0 | denormalized sum(payments.amount); hindari SUM relasi tiap query laporan |
| payment_status | enum(PaymentStatus: unpaid, partially_paid, paid) | default `unpaid` | FR-055; `partially_paid` saat 0 < paid_amount < subtotal |
| cancelled_at | timestamp | nullable | FR-058 |
| deleted_at | timestamp | nullable | soft delete; transaksi finansial tidak hard-delete |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- `(tenant_id, invoice_number)` UNIQUE.
- `(tenant_id, payment_status, created_at)` — query omzet lunas per rentang (FR-070).
- `(tenant_id, deleted_at)` INDEX — list transaksi aktif per tenant.

## Relasi

- belongsTo `Patient`, `Booking` (nullable), `Cashier` (User)
- hasMany `TransactionItem`
- hasMany `Payment`
- hasOne `Invoice` (1 invoice per transaksi)

## Delete Rule

- **Soft delete** (`deleted_at`). Transaksi finansial wajib bertahan untuk audit & laporan. Pembatalan bukan hapus — pakai `cancelled_at` (FR-058).
- FK `patient_id`, `cashier_id` → **`restrictOnDelete`** (bukan cascade/null). FK `booking_id` nullable → `restrictOnDelete` (booking di-soft-delete, transaksi tetap).

## State Transitions

- `payment_status`: `unpaid` → `partially_paid` (saat 0 < `paid_amount` < `subtotal`) → `paid` (saat `paid_amount >= subtotal`). Kembali `unpaid`/`partially_paid` bila payment di-rollback.
- Status transaksi (aktif/batal): `cancelled_at` null → set untuk pembatalan (FR-058 rollback stok).

## Catatan

- `PayTransactionAction` update `paid_amount += payment.amount` lalu set `payment_status`: `paid` bila `paid_amount >= subtotal`, `partially_paid` bila `0 < paid_amount < subtotal`, `unpaid` bila 0. Kelebihan bayar → peringatan, tidak ada saldo otomatis (edge case).
- `paid_amount` denormalized dari `payments` — dijaga sinkron oleh `PayTransactionAction` dalam DB transaction. `ponytail: reconcile dari sum(payments) add saat drift terdeteksi`.
- Pembatalan (FR-058) → rollback stok produk via `StockService` (StockMovement type `rollback`).
- Laporan omzet (FR-070) = agregasi `transactions` lunas (`payment_status=paid`) per rentang tanggal. Filter `partially_paid` bila laporan ingin sertakan penerimaan parsial.
# `payments`

Pembayaran transaksi POS (Kasir/POS, US5).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| transaction_id | bigint unsigned | FK→transactions, not null | |
| method | enum(PaymentMethod: cash, transfer, qris, debit) | not null | FR-054 |
| amount | decimal(12,2) | not null, >0 | |
| paid_at | datetime | not null | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Relasi

- belongsTo `Transaction`

## Delete Rule

- FK `transaction_id` → **`cascadeOnDelete`** aman: parent (`transactions`) di-soft-delete, cascade DB hanya terjadi saat hard-delete parent (kasus terlarang/jarang). Payment tidak punya nilai mandiri tanpa transaksi.

## Business Rules (FR-055)

- `PayTransactionAction` tambah payment → update `transactions.paid_amount += amount` → set `payment_status` (`paid`/`partially_paid`/`unpaid`) dalam satu DB transaction.
- Kelebihan bayar → peringatan, tidak ada saldo otomatis (edge case).

## Catatan

- Bisa lebih dari satu payment per transaksi (mis. cicilan / split payment) → `paid_amount` denormalized supaya status parsial terlihat tanpa SUM relasi.
- `paid_at` = waktu pembayaran diterima, dipakai laporan omzet per periode.
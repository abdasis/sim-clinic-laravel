# `stock_movements`

Pergerakan stok produk (Inventory + Kasir, US6/US5).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| product_id | bigint unsigned | FK→products, not null | |
| type | enum(StockMovementType: in, out_manual, sold_pos, rollback) | not null | R7 |
| quantity | integer | not null | positif untuk `in`/`rollback`; positif tapi mengurangi saldo untuk `out_manual`/`sold_pos` |
| balance_after | integer | not null | saldo setelah mutasi (audit) |
| related_type | string(255) | nullable | morph: `Transaction::class` untuk sold_pos/rollback |
| related_id | bigint unsigned | nullable | |
| note | string(255) | nullable | keterangan/alasan (FR-061, FR-062) |
| created_at | timestamp | | |

## Constraint & Index

- `(tenant_id, product_id, created_at)` — riwayat per produk (FR-064).
- `(related_type, related_id)` — reverse lookup mutasi per transaksi (sold_pos/rollback). Pakai helper migration `nullableMorphs('related')` yang sekaligus buat kolom + composite index ini.
- Hanya `created_at` (tidak `updated_at`) — mutasi immutable.

## Relasi

- belongsTo `Product`
- morphTo `related` (Transaction, untuk sold_pos/rollback)

## Business Rules (R7)

- Semua mutasi lewat `StockService::adjust()` dalam DB transaction.
- `quantity` selalu positif; arah saldo ditentukan `type`.
- `balance_after` dicatat untuk audit.
- `in` → tambah saldo (FR-061); `out_manual` → kurang saldo (FR-062); `sold_pos` → kurang saat transaksi POS (FR-052); `rollback` → kembalikan saat transaksi dibatalkan (FR-058).

## Catatan

- Riwayat stok masuk & keluar per produk (FR-064) = query `stock_movements` dengan filter `type` + `tenant_id` + `product_id`.
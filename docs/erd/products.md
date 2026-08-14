# `products`

Master produk inventory (Inventory, US6).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| name | string(255) | not null | |
| unit | string(50) | not null | FR-060 (pcs/botol/ml) |
| stock_balance | integer | not null, default 0 | R7; satu sumber saldo |
| min_threshold | integer | not null, default 0 | FR-065 ambang "stok menipis" |
| price | decimal(12,2) | not null, ≥0 | harga jual |
| status | enum(ServiceStatus: active, archived) | default `active` | FR-066 arsip, tidak hapus permanen |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Relasi

- belongsTo `Tenant`
- hasMany `StockMovement`
- hasMany `TransactionItem`

## Delete Rule

- **Tidak ada hard delete.** Nonaktif = `status=archived` (FR-066), soft hide dari pilihan baru.
- FK dari `stock_movements.product_id`, `transaction_items.product_id` → **`restrictOnDelete`** — blokir hapus produk yang masih direferensi. Pakai arsip, bukan hapus. `stock_balance` tetap dijaga selama produk masih arsip (riwayat mutasi utuh).

## Validation

- `name` required
- `unit` required|string
- `stock_balance` integer|gte:0
- `min_threshold` integer|gte:0
- `price` decimal|gte:0

## Computed

- `is_low_stock = stock_balance <= min_threshold` (FR-065).

## Catatan

- **ponytail**: `stock_balance` kolom denormalized; konsistensi dijaga `StockService` + DB transaction. Reconcile job opsional kalau drift terdeteksi (R7).
- Arsip (FR-066) = set `status=archived`, tidak hapus permanen.
- `stock_balance` diubah hanya via `StockMovement` (`StockService::adjust()`), bukan update langsung.
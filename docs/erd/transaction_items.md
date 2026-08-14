# `transaction_items`

Line item penjualan (Kasir/POS, US5 + R6).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| transaction_id | bigint unsigned | FK→transactions, not null | |
| product_id | bigint unsigned | FK→products, nullable | nullable: item bisa produk atau layanan |
| service_id | bigint unsigned | FK→services, nullable | nullable: item bisa layanan atau produk |
| name | string(255) | not null | R6 snapshot nama |
| unit_price | decimal(12,2) | not null | R6 snapshot harga historik (FR-056) |
| qty | integer | not null, >0 | |
| subtotal | decimal(12,2) | not null | unit_price * qty |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Constraint & Index

- `(tenant_id, transaction_id)` — agregasi per transaksi.
- `(tenant_id, product_id)` — laporan penjualan produk (FR-072).
- `(tenant_id, service_id)` — laporan penjualan treatment (FR-071).

## Relasi

- belongsTo `Transaction`, `Product` (nullable), `Service` (nullable)

## Delete Rule

- FK `transaction_id` → **`cascadeOnDelete`** aman: parent (`transactions`) di-soft-delete, cascade DB hanya saat hard-delete parent (kasus terlarang/jarang). Line item tidak punya nilai mandiri tanpa transaksi.
- FK `product_id`/`service_id` nullable → `restrictOnDelete`. Master di-arsip (`status=archived`), bukan hapus; snapshot `name`+`unit_price` tetap utuh walau master berubah (R6, FR-056).

## Business Rules

- Salah satu `product_id`/`service_id` terisi (item = produk ATAU layanan).
- `name` & `unit_price` snapshot agar transaksi lama tetap utuh walau master diubah (R6, FR-056).
- Stok produk di-check (FR-053) & adjust (FR-052) saat simpan transaksi via `StockService` (StockMovement type `sold_pos`).

## Catatan

- Laporan penjualan treatment (FR-071) = agregasi `transaction_items` dengan `service_id` not null.
- Laporan penjualan produk (FR-072) = agregasi `transaction_items` dengan `product_id` not null.
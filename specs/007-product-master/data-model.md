# Data Model — Master Produk Klinik (007-product-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Sumber kebenaran**: `docs/erd/products.md`, `docs/erd/stock_movements.md`, `docs/erd/transaction_items.md`, `docs/normalization/README.md`

Tidak ada entity/tabel/kolom baru. Dokumen ini merangkum entitas terlibat + perubahan FK + invariant yang diuji.

## Entity: `products`

Master produk inventory klinik (tenant-scoped via `BelongsToTenant` + `TenantScope`).

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant, cascadeOnDelete |
| name | string(255) | not null | input |
| unit | string(50) | not null | FR-060 satuan (pcs/botol/ml) |
| stock_balance | integer | not null, default 0 | R7 denormalized; **bukan input** (FR-060/063); hanya via `StockService::adjust()` |
| min_threshold | integer | not null, default 0 | FR-065 ambang stok menipis; input |
| price | decimal(12,2) | not null, >=0 | harga jual; input |
| status | enum(ServiceStatus: active, archived) | default `active` | FR-066; arsip via row action, bukan field form |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Computed**: `is_low_stock = stock_balance <= min_threshold` (FR-065) — `getIsLowStockAttribute`, di-`append` ke `ProductResource`.

**Relasi**: belongsTo `Tenant`; hasMany `StockMovement`; hasMany `TransactionItem`.

**State transition** (status): `active` → `archived` (via `DELETE` endpoint / row action "Arsipkan"). Tidak ada transisi balik di MVP (`ponytail:` un-archive bila butuh).

### Validation (`ProductRequest` — revisi R3)

| Field | Rule |
|-------|------|
| name | required, string, max:255 |
| unit | required, string, max:50 |
| min_threshold | required, integer, gte:0 |
| price | required, numeric, gte:0 |
| status | nullable, enum(ServiceStatus) |
| ~~stock_balance~~ | **dihapus dari request** — bukan input (R3) |

`stock_balance` tetap di `$fillable` (dibutuhkan `StockService::adjust()`), tetapi **tidak ada di `ProductRequest`** → tidak diterima dari request. Pertahanan SC-007.

## Entity: `stock_movements`

Catatan mutasi stok (immutable, hanya `created_at`). Tidak berubah di spec ini.

| Field | Tipe | Catatan |
|-------|------|---------|
| id | bigint unsigned | PK |
| tenant_id | FK→tenants | BelongsToTenant |
| product_id | FK→products | **R2: restrictOnDelete** (sebelumnya cascadeOnDelete) |
| type | enum(in, out_manual, sold_pos, rollback) | R7 |
| quantity | integer | >0; arah saldo via `type` |
| balance_after | integer | saldo setelah mutasi (audit) |
| related_type / related_id | nullable morph | Transaction untuk sold_pos/rollback |
| note | string(255), nullable | FR-061/062 keterangan |
| created_at | timestamp | immutable (no `updated_at`) |

**Invariant R7**: semua mutasi via `StockService::adjust()` dalam DB transaction + row lock → `balance_after` = saldo baru + `products.stock_balance` update atomik.

## Entity: `transaction_items`

Line item penjualan. Field `product_id`, `name`, `unit_price` relevan untuk snapshot (R5).

| Field | Catatan |
|-------|---------|
| product_id | FK→products, **R2: restrictOnDelete** (sebelumnya nullOnDelete) |
| name | R6 snapshot nama produk — immutable |
| unit_price | R6 snapshot harga — immutable |

**Invariant FR-069**: `name` + `unit_price` snapshot immutable; tidak ada path sinkron ke master. Diverifikasi via test (R5).

## Perubahan FK — migration R2

| Tabel | FK | Sebelum | Sesudah | Alasan |
|-------|----|---------|---------|--------|
| stock_movements | product_id | cascadeOnDelete | **restrictOnDelete** | blokir hapus produk dengan riwayat mutasi (FR-068) |
| transaction_items | product_id | nullOnDelete | **restrictOnDelete** | blokir hapus produk dengan transaksi historis (FR-068, R6) |

## Invariant yang diuji (bukan kolom baru)

1. **SC-007 / FR-063**: `stock_balance` tidak berubah via request langsung — request bawa `stock_balance` tidak mengubah saldo (test).
2. **FR-068**: hard-delete produk direferensi → `QueryException` (FK restrict) — test.
3. **FR-069 / R6**: snapshot `transaction_items.name`/`unit_price` utuh setelah master diubah/arsip — test.
4. **FR-065**: `is_low_stock` true saat `stock_balance <= min_threshold` (termasuk equality) — test.
5. **Konstitusi III**: produk tenant A tidak terlihat tenant B (`TenantScope`) — test.

## Activity log (FR-073, R4)

| Aksi | Action | Event | Narasi | Properties |
|------|--------|-------|--------|-----------|
| create | `CreateProductAction` | `product.created` | "Membuat produk {name}" | full attributes |
| update | `UpdateProductAction` | `product.updated` | "Memperbarui produk {name}" | old/new diff |
| archive | `ArchiveProductAction` | `product.archived` | "Mengarsipkan produk {name}" | status old→new |

Flow: `ProductController` → `ProductService::{create,update,archive}()` → Action → `LogAuditAction`. Via `LogAuditAction` (spatie `activity()` wrapper). Causer = authenticated user, subject = `Product`, tenant via `properties->tenant_id`. Service mengorkestrasi, Action eksekusi DB + audit (tidak boleh inject Service).
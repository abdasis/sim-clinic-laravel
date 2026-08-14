# Data Model: Integritas Mutasi Stok & Riwayat Stok Produk

**Branch**: `012-stock-movements` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

Entitas `StockMovement` + `Product` (read-only di spec ini) sudah ada. Spec ini **merevisi skema `stock_movements`** + menambah guard + audit, bukan tabel baru.

## Entity: StockMovement (revisi)

Jejak audit mutasi stok produk. Immutable (hanya `created_at`).

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant; inherit dari product via StockService (anomali #3) |
| product_id | bigint unsigned | FK→products, **restrictOnDelete** | migration 060000 eksisting; blokir hard-delete produk dengan riwayat |
| type | enum(StockMovementType: in, out_manual, sold_pos, rollback) | not null | R7 |
| quantity | integer | not null | positif; arah saldo ditentukan type |
| balance_after | integer | not null | saldo setelah mutasi (audit); >= 0 dijaga guard service (FR-015) |
| related_type | string(255) | nullable | morph alias via `enforceMorphMap` ('transaction'); sebelumnya FQCN |
| related_id | bigint unsigned | nullable | morph target |
| note | string(255) | nullable | keterangan/alasan (FR-061, FR-062) |
| created_at | timestamp | useCurrent | immutable; tidak ada updated_at |

### Index (revisi)

| Index | Kolom | Tujuan |
|-------|-------|--------|
| `stock_movements_tenant_product_created` | (tenant_id, product_id, created_at) | riwayat per produk (FR-064) — SUDAH ADA, dipertahankan |
| `stock_movements_related_type_related_id_index` | (related_type, related_id) | reverse lookup per transaksi (FR-012, FR-006) — NEW via `nullableMorphs('related')` |

### Migration revisi (`2026_08_14_140000_revise_stock_movements_related_morph.php`)

```php
// up():
// 1. dropForeign? tidak — related_type/related_id bukan FK
// 2. dropColumns(['related_type', 'related_id'])
// 3. nullableMorphs('related') → buat related_type + related_id + composite index
// down(): reverse — dropMorphs('related'), restore manual columns + index
```

`ponytail`: composite index + FK restrict SQLite skip; uji via `phpunit.pgsql.xml`.

## Entity: Product (read-only, tidak diubah spec ini)

Master produk. Field relevan: `stock_balance` (integer, denormalized, satu sumber saldo R7), `min_threshold`, `is_low_stock` computed, `status` (active/archived). Mutasi `stock_balance` hanya via `StockService::adjust()` — spec ini menambah guard + audit, tidak ubah skema produk. FK dari `stock_movements.product_id` restrictOnDelete (migration 060000 eksisting).

## Relasi

- `StockMovement` belongsTo `Product`
- `StockMovement` morphTo `related` (Transaction, untuk sold_pos/rollback) — via `nullableMorphs('related')`
- `StockMovement` belongsTo `Tenant` (BelongsToTenant trait + TenantScope)

## Morph Map

`AppServiceProvider::boot()`:
```php
Relation::enforceMorphMap([
    'transaction' => \App\Models\Transaction::class,
    // extensibel: audit_logs causer/subject morph pakai map sama saat migrasi spatie
]);
```
`related_type` simpan alias `'transaction'`, bukan FQCN. `StockService::adjust()` set `related?->getMorphClass()` (otomatis alias saat map aktif).

## Business Rules (R7, revisi)

| Rule | Implementasi | FR |
|------|-------------|-----|
| Semua mutasi lewat `StockService::adjust()` dalam DB transaction + row lock | eksisting | FR-002 |
| `balance_after` = saldo sebelum ± quantity (arah via type) | eksisting | FR-001 |
| `stock_balance` update = `balance_after` dalam transaksi sama | eksisting | FR-003 |
| Immutable (tidak ada updated_at, tidak ada update/delete path) | eksisting `$timestamps=false`, tidak ada route update/delete | FR-004 |
| `sold_pos`/`rollback` terhubung transaksi via morph `related` | eksisting + morph map R2 | FR-005 |
| Guard saldo negatif: bila outbound dan `balance_after < 0` → abort 422 | NEW di StockService | FR-015 |
| Rollback idempoten: pembatalan berulang ditolak (cancelled_at check) | eksisting CancelTransactionAction | US3 edge |
| Audit log naratif "Menyesuaikan stok {product} — {type} {qty}" | NEW di StockService via LogAuditAction | FR-014 |
| tenant_id inherit dari product (anomali #3) | eksisting StockService `$locked->tenant_id` | FR-009 |

## State Transition

Tidak ada state pada `StockMovement` — immutable sekali tulis. `type` bukan state, itu kategori mutasi (ditentukan saat create, tidak berubah).

## Validation (StockMovementRequest, eksisting)

- `type`: required, in `['in', 'out_manual']` (hanya manual lewat endpoint; `sold_pos`/`rollback` lewat service internal, tidak lewat request ini)
- `quantity`: required, integer, gt:0
- `note`: nullable, string, max:255

Guard saldo negatif (FR-015) = service-level, bukan FormRequest (backstop untuk semua path termasuk sold_pos).

## Activity Log Context

`LogAuditAction::handle('inventory.stock.adjusted', $movement, $causer, $context, $description)`:
- `log_name` = 'inventory' (prefix action)
- `event` = 'inventory.stock.adjusted'
- `subject` = `$movement` (StockMovement)
- `causer` = `auth()->user()` (nullable di job)
- `properties` = full attributes: `product_id`, `type`, `quantity`, `balance_after`, `note`, `related_type`, `related_id`, `tenant_id`, `product_name`
- `description` = `"Menyesuaikan stok {product_name} — {type_label} {quantity}"`
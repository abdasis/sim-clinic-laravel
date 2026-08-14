# Data Model — Transaksi POS & Pembayaran (008-transactions-pos)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Sumber kebenaran**: `docs/erd/transactions.md`, `docs/erd/payments.md`, `docs/erd/transaction_items.md`, `docs/erd/invoices.md`, `docs/erd/README.md`, `docs/normalization/README.md`

Tidak ada entity baru. Perubahan: kolom baru (`paid_amount`, `deleted_at`, `issued_at`), enum +state (`partially_paid`), FK delete rule, drop tabel `invoices` (F0 merge).

## Entity: `transactions`

Pencatatan penjualan POS (tenant-scoped via `BelongsToTenant` + `TenantScope`, **+ `SoftDeletes`**).

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant, cascadeOnDelete |
| patient_id | bigint unsigned | FK→patients, not null | **R6: restrictOnDelete** (sebelumnya nullOnDelete); FR-050 |
| booking_id | bigint unsigned | FK→bookings, nullable | **R6: restrictOnDelete** (sebelumnya nullOnDelete); FR-033 opsional link booking done |
| cashier_id | bigint unsigned | FK→users, not null | restrictOnDelete (sudah via migration kedua); kasir pembuat otomatis |
| invoice_number | string(50) | not null, unique(tenant) | generate `INV-YYYYMMDD-XXXX` lockForUpdate (FR-077) |
| subtotal | decimal(12,2) | not null | sum(item.subtotal) |
| **paid_amount** | **decimal(12,2)** | **not null, default 0** | **NEW (R2)** denormalized sum(payments.amount); sync `PayTransactionAction` |
| payment_status | enum(PaymentStatus: unpaid, **partially_paid**, paid) | default `unpaid` | **R3: +partially_paid**; FR-055 |
| cancelled_at | timestamp | nullable | FR-058 pembatalan bisnis |
| **issued_at** | **datetime** | **nullable** | **NEW (R7, F0 merge)** — pindah dari tabel `invoices`; terisi saat transaksi di-issue |
| **deleted_at** | timestamp | nullable | **NEW (R5)** soft delete; FR-081 |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Computed/exposed**: sisa bayar = `subtotal - paid_amount` (FR-080, di FE dari resource).

**Relasi**: belongsTo `Patient`, `Booking` (nullable), `Cashier` (User); hasMany `TransactionItem`; hasMany `Payment`. ~~hasOne `Invoice`~~ (dihapus F0 merge).

**Constraint & Index**:
- `(tenant_id, invoice_number)` UNIQUE (sudah).
- `(tenant_id, payment_status, created_at)` INDEX (sudah) — laporan omzet lunas per rentang (FR-070).
- `(tenant_id, deleted_at)` INDEX **NEW (R5)** — list transaksi aktif per tenant.

**State transitions**:
- `payment_status`: `unpaid` → `partially_paid` (0 < paid_amount < subtotal) → `paid` (paid_amount >= subtotal). Kembali `unpaid`/`partially_paid` bila payment di-rollback (`ponytail:` rollback payment add bila butuh).
- `cancelled_at`: null → set untuk pembatalan (FR-058, rollback stok). Guard: tolak bila sudah terisi (R10 double-cancel).
- `deleted_at`: null → soft-delete (FR-081). Tidak expose restore di MVP.

### Validation (`TransactionRequest` — revisi R9)

| Field | Rule |
|-------|------|
| patient_id | required, exists:patients,id (FR-050 — sebelumnya nullable, **kini required**) |
| booking_id | nullable, exists:bookings,id; bila terisi harus booking status=done (FR-033) |
| items | required, array, min:1 |
| items.*.product_id | nullable, exists:products,id; **XOR service_id** (R9 exclusive arc) |
| items.*.service_id | nullable, exists:services,id; **XOR product_id** (R9 exclusive arc) |
| items.*.qty | required, integer, gt:0 |

**Exclusive arc rule** (R9): `items.*.product_id` dan `items.*.service_id` — tepat satu terisi. Validasi app-layer: `required_without:service_id` + `prohibits:service_id` (atau custom rule). DB CHECK dikerjakan langkah 11.

## Entity: `payments`

Pembayaran transaksi (cicilan/split). Tidak berubah di spec ini.

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant, cascadeOnDelete |
| transaction_id | bigint unsigned | FK→transactions, not null | cascadeOnDelete (child admin — soft delete parent tidak trigger) |
| method | enum(PaymentMethod: cash, transfer, qris, debit) | not null | FR-054 |
| amount | decimal(12,2) | not null, >0 | |
| paid_at | datetime | not null | laporan omzet per periode |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Relasi**: belongsTo `Transaction`.

**Business rule (FR-055, R3)**: `PayTransactionAction` — setelah `payments()->create`: `transaction->paid_amount += amount` (lockForUpdate row transaction) → set `payment_status`: `paid` bila `>= subtotal`, `partially_paid` bila `0 < < subtotal`, `unpaid` bila 0. Dalam satu DB transaction. Overpaid → peringatan, tidak ada saldo otomatis.

## Entity: `transaction_items`

Line item penjualan. Tidak berubah kolom di spec ini (FK restrict + CHECK = langkah 11), tapi spec 008 tambah app validation exclusive arc (R9).

| Field | Tipe | Catatan |
|-------|------|---------|
| id | bigint unsigned | PK |
| tenant_id | FK→tenants | BelongsToTenant |
| transaction_id | FK→transactions | cascadeOnDelete (child admin) |
| product_id | FK→products, nullable | nullOnDelete (restrictOnDelete = langkah 11); XOR service_id |
| service_id | FK→services, nullable | nullOnDelete (restrictOnDelete = langkah 11); XOR product_id |
| name | string(255) | R6 snapshot immutable (FR-056) |
| unit_price | decimal(12,2) | R6 snapshot immutable (FR-056) |
| qty | integer | >0 |
| subtotal | decimal(12,2) | unit_price * qty, immutable |
| created_at | timestamp | |

**Invariant R9**: exclusive arc app validation (`product_id` XOR `service_id`) di `TransactionRequest`. DB CHECK langkah 11.

**Invariant R6/FR-056**: `name` + `unit_price` snapshot immutable; tidak ada path sinkron ke master.

## Entity: ~~`invoices`~~ (DIHAPUS — F0 merge, R7)

Tabel `invoices` di-drop. `issued_at` pindah ke `transactions`. Model `Invoice`, `InvoicePolicy` dihapus. `InvoiceController::show` render dari `Transaction` (authorize via `TransactionPolicy@view`). `InvoiceService::render` baca `$transaction->issued_at`. Nomor invoice = `transactions.invoice_number` (sudah ada, tidak terpisah).

## Perubahan FK — migration R6

| Tabel | FK | Sebelum | Sesudah | Alasan |
|-------|----|---------|---------|--------|
| transactions | patient_id | nullOnDelete | **restrictOnDelete** | blokir hapus pasien direferensi transaksi (FR-083) |
| transactions | booking_id | nullOnDelete | **restrictOnDelete** | blokir hapus booking direferensi transaksi (FR-083) |
| transactions | cashier_id | (sudah restrict) | restrictOnDelete | sudah via migration kedua |
| payments | transaction_id | cascadeOnDelete | cascadeOnDelete (tetap) | child admin — soft delete parent tidak trigger |
| transaction_items | transaction_id | cascadeOnDelete | cascadeOnDelete (tetap) | child admin |
| transaction_items | product_id/service_id | nullOnDelete | restrictOnDelete (langkah 11) | di spec 008 hanya app validation; DB restrict langkah 11 |

**Catatan SQLite**: alter FK delete rule + alter enum tidak didukung SQLite. Migration guard `if (DB::getDriverName() === 'pgsql')`. `ForeignKeyRestrictTest` hanya jalan via `phpunit.pgsql.xml` (konvensi CLAUDE.md).

## Migration summary (R8 strategi)

| Migration | Isi | SQLite | PostgreSQL |
|-----------|-----|--------|------------|
| 1. add_transactions_paid_amount_softdelete_issued_at | +paid_amount default 0, +deleted_at softDeletes, +index(tenant_id,deleted_at), +issued_at | jalan | jalan |
| 2. alter_payment_status_add_partially_paid | enum +partially_paid | guard (SQLite CHECK recreate / skip) | `ALTER TYPE` / raw |
| 3. restrict_transaction_foreign_keys | patient_id/booking_id nullOnDelete → restrictOnDelete | skip (guard pgsql) | dropForeign+foreignConstrained restrict |
| 4. drop_invoices_table | drop `invoices` + hapus relasi | jalan | jalan |

## Invariant yang diuji (bukan kolom baru)

1. **SC-002 / FR-077**: invoice_number unik walau konkuren — `lockForUpdate` count dalam DB transaction. Test simulasikan 2+ transaksi konkuren per tenant per hari.
2. **FR-055 / R3**: `payment_status` 3-state akurat: unpaid/partially_paid/paid sesuai paid_amount vs subtotal. Test pembayaran parsial + pelunasan.
3. **FR-079 / R2**: `paid_amount` sync akumulasi payments dalam DB transaction. Test.
4. **FR-081 / R5**: soft-delete transaksi → `deleted_at` terisi, tidak muncul di list aktif, data utuh audit. Test.
5. **FR-082**: hard-delete transaksi dengan payment/item → diblokir restrict (pgsql test). Test.
6. **FR-083 / R6**: hapus pasien/booking direferensi transaksi → diblokir restrict (pgsql test). Test.
7. **R9**: item product_id+service_id keduanya null/terisi → ditolak 422 (app validation). Test.
8. **R10**: cancel transaksi sudah cancelled → 422/409 (guard double-cancel). Test.
9. **R7 / F0**: `issued_at` terisi di transactions saat issue; tabel invoices hilang; invoice render dari transaction. Test.
10. **Konstitusi III**: transaksi tenant A tidak terlihat tenant B (`TenantScope`). Test.

## Activity log (FR-084, R13)

| Aksi | Action/Service | Event | Narasi | Properties |
|------|----------------|-------|--------|-----------|
| create | `TransactionService::create` | `pos.transaction.created` | "Mencatat transaksi {invoice}" | full attributes (patient, items, subtotal) |
| payment | `PayTransactionAction` | `pos.payment.created` | "Mencatat pembayaran {invoice} — status {lama}→{baru}" | old_status, new_status, amount, paid_amount |
| cancel | `CancelTransactionAction` | `pos.transaction.cancelled` | "Membatalkan transaksi {invoice}" | cancelled_at, rollback items |
| soft-delete | `SoftDeleteTransactionAction` | `pos.transaction.deleted` | "Menghapus transaksi {invoice}" | subject context |

Flow: Controller → Service/Action → `LogAuditAction::handle(action, subject, causer, context, description, tenant)`. Causer = auth user, subject = Transaction/Payment, tenant via `properties->tenant_id`.
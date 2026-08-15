# Data Model — Transaksi POS & Pembayaran (009-pos-transactions)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md)

Sumber kebenaran struktur: [`docs/erd/transactions.md`](../../docs/erd/transactions.md) + [`docs/erd/payments.md`](../../docs/erd/payments.md) + [`docs/erd/transaction_items.md`](../../docs/erd/transaction_items.md) + [`docs/erd/invoices.md`](../../docs/erd/invoices.md) + [`docs/normalization/README.md`](../../docs/normalization/README.md). Fitur ini revisi entitas `transactions` (kolom `paid_amount` + `deleted_at` + enum + FK) + `PaymentStatus` enum; entitas `transaction_items`/`payments`/`invoices` tidak ada perubahan struktur (app-level enforcement saja).

## Entity: Transaction

Penjualan POS treatment & produk. Tenant-scoped. Soft-delete (`deleted_at`); pembatalan via `cancelled_at` (bukan hapus).

| Field | Type | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, NOT NULL, **cascadeOnDelete** | BelongsToTenant; auto-fill saat create; pengecualian (hapus tenant = hapus semua data) |
| patient_id | bigint unsigned | FK→patients, NOT NULL, **restrictOnDelete** (revisi R3; sebelumnya nullOnDelete) | FR-050 wajib pasien; FR-045 restrict |
| booking_id | bigint unsigned | FK→bookings, nullable, **restrictOnDelete** (revisi R3; sebelumnya nullOnDelete) | FR-044 hanya bila booking `done` (validasi app) |
| cashier_id | bigint unsigned | FK→users, NOT NULL, **restrictOnDelete** (sudah, migration 033000) | FR-045 kasir pembuat |
| invoice_number | string(50) | UNIQUE(tenant), NOT NULL | FR-042 generate `INV-YYYYMMDD-XXXX`, race-safe (R1) |
| subtotal | decimal(12,2) | NOT NULL | FR-043 sum(item.subtotal); denormalized |
| paid_amount | decimal(12,2) | NOT NULL, default 0 | **NEW (R2)** denormalized sum(payments.amount); dijaga sinkron `PayTransactionAction` |
| payment_status | varchar(20) (enum app) | default `unpaid` | FR-047; `unpaid`/`partially_paid`/`paid`; alter dari DB-enum ke varchar (R2) untuk portabilitas + `partially_paid` |
| cancelled_at | timestamp | nullable | FR-058 pembatalan (rollback stok) |
| deleted_at | timestamp | nullable | **NEW (R7)** soft-delete |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### Index

- `(tenant_id, invoice_number)` UNIQUE — FR-042 (sudah ada).
- `(tenant_id, payment_status, created_at)` — query omzet lunas per rentang (FR-070) (sudah ada).
- `(tenant_id, deleted_at)` INDEX — **NEW (R7)** list transaksi aktif per tenant.

### Relationships

| Relasi | Tipe | Delete rule (target) | Catatan |
|--------|------|----------------------|--------|
| belongsTo `Tenant` | n:1 | cascadeOnDelete | pengecualian multi-tenant |
| belongsTo `Patient` | n:1 | **restrictOnDelete** (R3) | pasien di-nonaktifkan, bukan hard-delete |
| belongsTo `Booking` | n:1 (opsional) | **restrictOnDelete** (R3) | hanya booking `done` (FR-044) |
| belongsTo `User` (cashier) | n:1 | restrictOnDelete (sudah) | user di-nonaktifkan, bukan hard-delete |
| hasMany `TransactionItem` | 1:n | (FK `transaction_items.transaction_id` → cascadeOnDelete) | line items snapshot |
| hasMany `Payment` | 1:n | (FK `payments.transaction_id` → cascadeOnDelete) | multi payment (split/cicil) |
| hasOne `Invoice` | 1:1 | (FK `invoices.transaction_id` → unique) | R4 render dari relasi; merge ditunda (R5) |

### Validation (store) — `TransactionRequest` (revisi R6)

- `patient_id`: **required**|exists:patients,id (tenant-scoped) — FR-050 (sebelumnya nullable).
- `booking_id`: nullable|exists:bookings,id + `withValidator`: bila diisi → `Booking::find($id)->status === Done` (FR-044). Bukan done → 422 `pos.booking_done_only`.
- `items`: required|array|min:1.
- `items.*.qty`: required|integer|gt:0.
- `items.*.product_id`: nullable|exists:products,id.
- `items.*.service_id`: nullable|exists:services,id.
- **Exclusive-arc (FR-049)** via `withValidator`: tiap item tepat satu dari `product_id`/`service_id` terisi. Keduanya null ATAU keduanya terisi → 422 `pos.items_exclusive_arc` pada `items.{i}`.

### State transitions — `payment_status` (FR-047, FR-055)

```
            create (paid_amount=0)
                  │
                  ▼
            ┌─────────┐  pay (0<paid<subtotal)  ┌──────────────────┐  pay (paid>=subtotal)  ┌──────┐
            │ unpaid  │ ───────────────────────▶ │ partially_paid   │ ─────────────────────▶ │ paid │
            └─────────┘                          └──────────────────┘                        └──────┘
                  │                                    │
                  └──── pay (paid>=subtotal langsung) ─┘
```

- `unpaid` → `partially_paid` (saat 0 < `paid_amount` < `subtotal`)
- `unpaid` → `paid` (saat `paid_amount` >= `subtotal`, pembayaran langsung lunas)
- `partially_paid` → `paid` (saat `paid_amount` >= `subtotal`)
- `paid` → `partially_paid`/`unpaid`: hanya via rollback pembayaran (future, di luar MVP)

Diturunkan otomatis dari `paid_amount` vs `subtotal` di `PayTransactionAction` — bukan input manual.

Status transaksi (aktif/batal) terpisah: `cancelled_at` null → aktif; `cancelled_at` set → batal (FR-058 rollback stok). Soft-delete (`deleted_at`) = sembunyi dari daftar, record tetap audit.

### Invoice number generation (FR-042, R1)

`Transaction::generateInvoiceNumber()` — race-safe:
- PostgreSQL: `pg_advisory_xact_lock(key)` dalam `DB::transaction`, key = stabil per (tenant, tanggal). Count + 1 → sequence `XXXX`.
- SQLite (test): fallback `lockForUpdate()->count()` + unique constraint catch-retry 1x.
- Format: `INV-YYYYMMDD-XXXX` (XXXX 4-digit zero-pad per tenant per hari).
- `ponytail: sequence table per tenant per hari add bila advisory lock tidak cukup di skala tinggi`.

## Entity: TransactionItem (tidak ada perubahan struktur)

Line item penjualan. Exclusive-arc ditegakkan app-level (FormRequest, R6); CHECK constraint DB ditunda (`ponytail: Anomali #1`).

| Field | Type | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, NOT NULL, cascadeOnDelete | BelongsToTenant |
| transaction_id | bigint unsigned | FK→transactions, NOT NULL, cascadeOnDelete | |
| product_id | bigint unsigned | FK→products, nullable, restrictOnDelete | exclusive arc (R6) |
| service_id | bigint unsigned | FK→services, nullable, restrictOnDelete | exclusive arc (R6) |
| name | string(255) | NOT NULL | R6 snapshot immutable (FR-056) |
| unit_price | decimal(12,2) | NOT NULL | R6 snapshot immutable (FR-056) |
| qty | integer | NOT NULL, >0 | |
| subtotal | decimal(12,2) | NOT NULL | `unit_price * qty` |

Index: `(tenant_id, transaction_id)`, `(tenant_id, product_id)` (FR-072), `(tenant_id, service_id)` (FR-071) — sudah ada.

## Entity: Payment (tidak ada perubahan struktur)

Pembayaran transaksi. Pencatatan memperbarui `transactions.paid_amount` + `payment_status` atomik.

| Field | Type | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, NOT NULL, cascadeOnDelete | BelongsToTenant |
| transaction_id | bigint unsigned | FK→transactions, NOT NULL, cascadeOnDelete | |
| method | enum(PaymentMethod: cash, transfer, qris, debit) | NOT NULL | FR-054 |
| amount | decimal(12,2) | NOT NULL, >0 | |
| paid_at | datetime | NOT NULL | waktu diterima, laporan omzet per periode |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Entity: Invoice (tidak ada perubahan struktur, merge ditunda R5)

Penerbitan invoice (1:1 per transaksi). Konten dirender dari relasi (R4) — bukan kolom duplikat.

| Field | Type | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, NOT NULL, cascadeOnDelete | BelongsToTenant |
| transaction_id | bigint unsigned | FK→transactions, NOT NULL, unique | 1 invoice per transaksi |
| issued_at | datetime | NOT NULL | saat pertama diterbitkan |
| created_at | timestamp | | |
| updated_at | timestamp | | |

`ponytail: merge issued_at ke transactions, drop tabel, saat butuh nomor invoice terpisah/multi-cetak/status cetak (Anomali 1:1 YAGNI, R5)`.

## Permission — `SyncTenantClinicRolesAction::MATRIX` (tidak berubah, R9)

| Role | transaction | invoice | Catatan |
|------|-------------|---------|---------|
| admin | rw (CRUD + payment + cancel + soft-delete) | rw (cetak invoice) | |
| cashier | rw (CRUD + payment + cancel + soft-delete) | rw (cetak invoice) | |
| doctor | (tidak ada — 403) | (tidak ada — 403) | dokter tidak kelola POS |
| therapist | (tidak ada — 403) | (tidak ada — 403) | terapis tidak kelola POS |

Otorisasi via `TransactionPolicy` → Gate `clinic.access` ['transaction', 'r'|'w'] → permission spatie `transaction.view`/`transaction.manage`. `InvoicePolicy` → `invoice.view`. FE sidebar visibility `pos` roles `["admin","cashier"]` (mirror matriks).

## Activity log

Setiap aksi ubah-data → `LogAuditAction` (spatie/laravel-activitylog, tabel `audit_logs`). **Saat ini tidak ada audit log — gap diisi (R4).**

| Aksi | event/log_name | Deskripsi naratif | Properties |
|------|----------------|-------------------|------------|
| create | `transaction.created` | "Mencatat transaksi {invoice_number} untuk {pasien} — {subtotal}." | `attributes` (full transaction + items) + `tenant_id` |
| payment | `transaction.payment_recorded` | "Mencatat pembayaran transaksi {invoice_number} — status berubah dari '{lama}' ke '{baru}'." (FR-057) | `old: {paid_amount, payment_status}`, `new: {paid_amount, payment_status}` + `tenant_id` |
| cancel | `transaction.cancelled` | "Membatalkan transaksi {invoice_number} — stok produk dikembalikan." | `old: {cancelled_at: null}`, `new: {cancelled_at}` + `tenant_id` |
| delete | `transaction.deleted` | "Mengarsipkan transaksi {invoice_number}." (soft-delete) | `attributes` (snapshot) + `tenant_id` |

Causer: auth user (auto via `LogAuditAction`). Narasi status lama→baru (FR-057) di `PayTransactionAction` — tangkap status lama sebelum update.

## Migration changes

Dua migration baru:

1. `2026_08_14_*_add_paid_amount_soft_delete_to_transactions` — tambah `paid_amount decimal(12,2) not null default 0`, `deleted_at timestamp nullable`, `index(tenant_id, deleted_at)`; alter `payment_status` ke `varchar(20)` (support `partially_paid`, portabel across DB). Skip SQLite untuk alter enum bila perlu (SQLite simpan string).
2. `2026_08_14_*_change_transactions_patient_booking_foreign_keys_to_restrict` — drop + recreate FK `patient_id` & `booking_id` dengan `restrictOnDelete` (pola 033000, skip SQLite).

Tidak ada migration untuk `transaction_items` exclusive-arc CHECK (app-level MVP, `ponytail: Anomali #1`). Tidak ada perubahan `payments`/`invoices`.

## Resource shape — `TransactionResource` (revisi R8)

```json
{
  "id": 1,
  "invoice_number": "INV-20260814-0001",
  "patient_id": 2,
  "patient_name": "Siti Aminah",
  "booking_id": 5,
  "booking_label": "Facial Glow — dr. Andi (2026-08-14)",
  "cashier_id": 3,
  "cashier_name": "Kasir Rina",
  "subtotal": "500000.00",
  "paid_amount": "200000.00",
  "balance_due": "300000.00",
  "payment_status": "partially_paid",
  "payment_status_label": "Dibayar Sebagian",
  "is_cancelled": false,
  "cancelled_at": null,
  "items": [
    { "id": 1, "name": "Facial Glow", "unit_price": "400000.00", "qty": 1, "subtotal": "400000.00" },
    { "id": 2, "name": "Serum Vitamin C", "unit_price": "100000.00", "qty": 1, "subtotal": "100000.00" }
  ],
  "created_at": "2026-08-14T08:00:00+00:00"
}
```

`balance_due` = `subtotal - paid_amount` (computed di Resource, bukan kolom). `booking_label` di-expose bila `booking` di-load. `is_cancelled` = `cancelled_at !== null`. `paid_amount` + `balance_due` untuk FE badge 3-state + sisa bayar (AC FE). `items` via `whenLoaded`.

## Tidak ada entity baru

Fitur murni revisi `transactions` (kolom + enum + FK + soft-delete) + `PaymentStatus` enum case + FE greenfield. Tidak ada tabel baru. `TransactionFactory`/`TransactionItemFactory`/`PaymentFactory` perlu dibuat/diperiksa untuk test (R11) bila belum ada.
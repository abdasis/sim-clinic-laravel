# Implementation Plan: Transaksi POS & Pembayaran Klinik

**Branch**: `008-transactions-pos` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-transactions-pos/spec.md`

## Summary

Revisi/penyempurnaan modul transaksi POS + pembayaran yang sudah sebagian terimplementasi. Tiga belas gap utama dipenuhi: (1) **F0 merge `invoices`** — `issued_at` pindah ke `transactions`, drop tabel `invoices` + model + policy + controller (keputusan user: MERGE, BCNF pure + YAGNI); (2) kolom `paid_amount decimal(12,2) default 0 not null` (denormalized, FR-079) — sync di `PayTransactionAction`; (3) **`payment_status` 3-state** `partially_paid` (FR-055) — enum + migration alter + `PayTransactionAction` logic + `lockForUpdate` row transaction; (4) **`invoice_number` race fix** `lockForUpdate` count dalam DB transaction (FR-077); (5) **soft delete** `deleted_at` + index `(tenant_id, deleted_at)` + `destroy` endpoint (FR-081); (6) **FK `restrictOnDelete`** `patient_id`/`booking_id` (FR-083, cashier_id sudah); (7) `TransactionResource` expose `paid_amount`/`issued_at`/`booking_id`; (8) **exclusive arc app validation** `TransactionRequest` items product_id XOR service_id (R9); (9) **guard cancel** tolak double-cancel (R10); (10) `PaymentResource` baru; (11) audit log naratif "Mencatat pembayaran … status {lama}→{baru}" (FR-084); (12) FE: `formatCurrency` pindah ke `src/lib/`, `StatusBadge` reusable, `FormCombobox` searchable; (13) FE POS: faceted filter 3-state + kolom `paid_amount`/sisa bayar + `FormCombobox` pasien + validasi `useForm`/zod + i18n `partially_paid` + breadcrumb (FR-080/087). Test oleh `zahiira`. Migration SQLite vs PostgreSQL strategi guard driver (R8). Hard-delete tidak diekspos; DB restrict + soft-delete jadi penjaga integritas.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13 API), TypeScript (TanStack Start, React 19).

**Primary Dependencies**:
- BE: spatie/laravel-activitylog ^5.1 (audit via `LogAuditAction`/`activity()`), Eloquent ORM, PostgreSQL. Enum `PaymentStatus`/`PaymentMethod` native PHP.
- FE: react-hook-form, zod, @tanstack/react-query + @tanstack/react-table, shadcn/ui (radix-nova), Tailwind v4, hugeicons. UI primitives `combobox.tsx`+`command.tsx` sudah ada.

**Storage**: PostgreSQL (multi-tenant single-DB, tenant_id pada `transactions`/`payments`/`transaction_items`). SQLite :memory: untuk test cepat (constraint restrict diverifikasi via `phpunit.pgsql.xml`).

**Testing**: PHPUnit/Pest (feature + unit, sqlite :memory: per phpunit.xml; pgsql suite sebelum rilis). Delegasi penulisan test ke agent `zahiira`.

**Target Platform**: Web — Laravel API port 8000, TanStack Start port 3001.

**Project Type**: Monorepo web app — `apps/api` (backend) + `apps/web` (frontend).

**Performance Goals**: Daftar transaksi tampil <1 detik untuk 1000 transaksi aktif per klinik (SC-010). Invoice number generation konkuren aman (SC-002).

**Constraints**: Class PHP <=300 baris, method <=100 baris (konstitusi V). File komponen React <=300 baris. Isolasi tenant otomatis via `BelongsToTenant`+`TenantScope` (konstitusi III). Teks UI via i18n (konstitusi V). Layering Controller→Service→Action satu arah (CLAUDE.md). Service dilarang sentuh DB langsung.

**Scale/Scope**: Multi-tenant, belasan klinik, ribuan transaksi/tenant. POS = modul inti pendapatan.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Catatan |
|-----------|--------|---------|
| I. Clean Code | PASS | Layering Controller→Service→Action diperkuat; Action satu use case; Service orkestrasi tanpa sentuh DB. No dead code. |
| II. TDD | PASS | Test oleh `zahiira`: feature test endpoint + unit test Action (PayTransactionAction 3-state, generateInvoiceNumber race, cancel guard, soft-delete, FK restrict). Red-Green-Refactor. |
| III. Multi-Tenant Isolation | PASS | `BelongsToTenant`+`TenantScope` otomatis; invoice_number unique per tenant; `lockForUpdate` scoped tenant. Test tenant isolation. |
| IV. Simplicity (YAGNI) | PASS | Merge invoices (YAGNI tabel 1:1). Tidak buat `FormNumericInput`/`DataTableCurrencyCell` (sudah tercakup). 3 komponen reusable baru saja yang gap nyata. `ponytail:` untuk sequence/reconcile/restore/refund. |
| V. Bounded Size | PASS | Class <=300, method <=100. FE komponen <=300. Extract bila melebihi. |
| VI. Permission & Activity Log | PASS | `LogAuditAction` naratif tiap Action ubah-data (create/payment/cancel/soft-delete). `withProperties` old/new. Exception di-log `Log::error`. Role clinic pakai Gate `clinic.access` matrix statik (exception konstitusi VI, role fixed). |

No violations. Complexity Tracking kosong.

## Project Structure

### Documentation (this feature)

```text
specs/008-transactions-pos/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── transactions-api.md
└── tasks.md             # Phase 2 (/speckit-tasks — NOT this command)
```

### Source Code (repository root)

```text
apps/api/
├── database/
│   └── migrations/
│       ├── 2026_08_14_*_add_transactions_paid_amount_softdelete_issued_at.php  # NEW: +paid_amount, +deleted_at, +index, +issued_at
│       ├── 2026_08_14_*_alter_payment_status_add_partially_paid.php            # NEW: enum +partially_paid (guard pgsql)
│       ├── 2026_08_14_*_restrict_transaction_foreign_keys.php                  # NEW: patient_id/booking_id → restrict (guard pgsql)
│       └── 2026_08_14_*_drop_invoices_table.php                                 # NEW: F0 merge — drop invoices
├── app/
│   ├── Enums/
│   │   └── PaymentStatus.php                       # EDIT: +PartiallyPaid case
│   ├── Models/
│   │   ├── Transaction.php                         # EDIT: +SoftDeletes, +paid_amount/issued_at fillable+cast, generateInvoiceNumber lockForUpdate
│   │   └── Invoice.php                             # DELETE (F0 merge)
│   ├── Actions/
│   │   ├── PayTransactionAction.php                # EDIT: +paid_amount sync, 3-state, lockForUpdate, audit naratif
│   │   ├── CancelTransactionAction.php             # EDIT: +guard double-cancel, audit
│   │   └── SoftDeleteTransactionAction.php         # NEW: soft-delete + audit (FR-081)
│   ├── Services/
│   │   ├── TransactionService.php                  # EDIT: generateInvoiceNumber lockForUpdate in DB txn, issued_at saat create (merge), audit
│   │   └── InvoiceService.php                      # EDIT: issued_at dari transaction (merge)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── TransactionController.php           # EDIT: +destroy (soft-delete) via Action/Serdelete
│   │   │   └── InvoiceController.php               # EDIT: authorize via TransactionPolicy, render dari transaction
│   │   ├── Requests/
│   │   │   └── TransactionRequest.php              # EDIT: +exclusive arc product_id XOR service_id
│   │   ├── Resources/
│   │   │   ├── TransactionResource.php             # EDIT: +paid_amount, +issued_at, +booking_id
│   │   │   └── PaymentResource.php                 # NEW
│   │   └── Policies/
│   │       └── InvoicePolicy.php                   # DELETE (F0 merge)
│   └── Policies/
│       └── TransactionPolicy.php                   # verify destroy auth (soft-delete)
└── lang/id/
    └── clinic.php                                  # EDIT: +payment_status.partially_paid

apps/web/src/
├── lib/
│   └── format.ts                                   # NEW: formatCurrency (pindah dari pos/components/format.ts)
├── components/
│   ├── forms/
│   │   └── form-combobox.tsx                       # NEW: searchable select (combobox+command+react-hook-form)
│   ├── datatable/                                  # NO CHANGE — reuse eksisting (DataTable, FacetedFilter, Pagination, Toolbar)
│   └── ui/
│       └── status-badge.tsx                        # NEW: reusable status→variant+label badge (>=4 tempat)
└── routes/$tenant/clinic/pos/
    ├── index.tsx                                   # EDIT: FormCombobox pasien, useForm/zod validasi, badge 3-state, formatCurrency lib
    ├── transactions.tsx                            # EDIT: faceted filter payment_status 3-state, kolom paid_amount+sisa bayar, StatusBadge, formatCurrency lib
    └── components/
        ├── format.ts                               # DELETE (pindah ke src/lib/format.ts)
        ├── payment-panel.tsx                       # EDIT: badge 3-state, paid_amount vs subtotal + sisa, formatCurrency lib
        └── transaction-item-list.tsx               # EDIT: formatCurrency lib (import path)
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` sesuai eksisting. File BE baru: 4 migration, 1 Action (SoftDelete), 1 Resource (Payment). Edit BE: enum, 2 model (1 delete), 2 Action, 2 Service, 2 controller, 1 request, 1 resource, 1 policy (delete), 1 lang. File FE baru: 3 (`format.ts` lib, `form-combobox.tsx`, `status-badge.tsx`). Edit FE: 4 (`pos/index.tsx`, `transactions.tsx`, `payment-panel.tsx`, `transaction-item-list.tsx`), delete 1 (`pos/components/format.ts`). Reuse `components/forms/` (`FormInput`/`FormSelect`/`FormSubmit`/`useForm`) + `components/datatable/` (semua) as-is. Test oleh `zahiira`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| (kosong — no violations) | | |
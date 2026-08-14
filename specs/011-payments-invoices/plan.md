# Implementation Plan: Integritas Item Transaksi, Pembayaran Cicilan & Cetak Invoice

**Branch**: `011-payments-invoices` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/011-payments-invoices/spec.md`

## Summary

Pelengkap spec 008 (Transaksi POS) yang menggarap tiga celah yang ditunda: (1) **integritas data level basis** `transaction_items` — CHECK constraint exclusive arc (anomali #1), FK `product_id`/`service_id` `restrictOnDelete` (ganti `nullOnDelete`), invariant tenant (anomali #3); (2) **pengalaman pembayaran cicilan** — halaman detail transaksi dengan catat pembayaran bertahap, ringkasan sisa, riwayat pembayaran, peringatan overpaid; (3) **cetak invoice lengkap** — FE invoice render section pembayaran (R4, saat ini hanya item). Backend inti (PayTransactionAction sync, PaymentController, PaymentResource, InvoiceService render dari relasi, app validation exclusive arc, TransactionResource expose payments+items) sudah ada dari 008 — tidak ditulis ulang. FE reuse `components/forms/` (`FormSelect`/`FormInput`/`FormCombobox`/`useForm`/`applyServerErrors`) + `components/datatable/` + `StatusBadge`/`PAYMENT_STATUS_VARIANTS` + `formatCurrency` + `ClinicBreadcrumb`.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13, apps/api); TypeScript (React 19, TanStack Start, apps/web)

**Primary Dependencies**: Laravel Sanctum, Eloquent, PostgreSQL; TanStack Router + Query, react-hook-form + zod, shadcn/ui `radix-nova`, Tailwind v4

**Storage**: PostgreSQL (multi-tenant single-DB, `tenant_id` pada semua child tabel)

**Testing**: PHPUnit (Feature/Unit, sqlite :memory: default + `phpunit.pgsql.xml` untuk constraint), vitest (FE)

**Target Platform**: Linux server (API), web browser (FE SSR)

**Project Type**: web-service (API) + web-app (FE) monorepo

**Performance Goals**: Alur cicilan < 1 menit per pembayaran (SC-007); buka+print invoice < 10 detik (SC-008)

**Constraints**: Multi-tenant isolation (constitution III), class PHP <= 300 baris / method <= 100 baris, komponen React <= 300 baris

**Scale/Scope**: 1 migration baru (CHECK + FK restrict), 1 halaman FE baru (detail transaksi + bayar), 1 edit FE (invoice render payments), 0 model/controller/action baru (BE inti sudah ada)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|--------|
| I. Clean Code | PASS | Reuse komponen/forms/datatable eksisting; tidak ada abstraksi baru (YAGNI). Migration + halaman FE minimal. |
| II. TDD | PASS | Test oleh `zahiira`: DB CHECK constraint test (PostgreSQL), tenant invariant test, FE cicilan flow test. Test task di tasks.md. |
| III. Multi-Tenant Isolation | PASS | Child create via relasi `$transaction->items()->create()` / `payments()->create()` → `tenant_id` inherit (anomali #3). TenantScope global scope aktif. Tidak ada query lintas-tenant. |
| IV. Simplicity (YAGNI) | PASS | F0 merge `invoices` sudah diputus (tabel dihapus, `issued_at` di transactions). Tidak buat model/controller/action baru — BE inti sudah ada. Reuse > rewrite. |
| V. Bounded Size | PASS | Halaman detail transaksi split: form bayar (`payment-form.tsx`) + riwayat (`payment-history.tsx`) bila > 300 baris. Migration sederhana. |
| VI. Permission & Activity Log | PASS | `PayTransactionAction` sudah audit naratif (status lama→baru). Activity log via `LogAuditAction` — tidak diulang. CHECK constraint rejection = DB-level, tidak perlu activity log (bukan aksi user). |

**Gate**: LULUS. Tidak ada pelanggaran yang perlu justifikasi Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/011-payments-invoices/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── api-contracts.md
└── tasks.md             # Phase 2 (/speckit-tasks — NOT this command)
```

### Source Code (repository root)

```text
apps/api/
├── database/
│   └── migrations/
│       └── 2026_08_14_*_enforce_transaction_items_integrity.php       # NEW: CHECK exclusive arc + FK restrict product/service
└── lang/id/
    └── clinic.php                                                      # EDIT: +payment_history.* keys (bila perlu)
# Tidak ada model/controller/action/service/resource baru — BE inti dari 008:
#   PayTransactionAction (sync paid_amount + 3-state + audit) — SUDAH ADA
#   PaymentController@store + PaymentRequest + PaymentResource — SUDAH ADA
#   TransactionController@show (load items+patient+payments) — SUDAH ADA
#   InvoiceController@show + InvoiceService::render (items+payments+patient) — SUDAH ADA
#   TransactionRequest (app validation exclusive arc) — SUDAH ADA

apps/web/src/
├── routes/$tenant/clinic/pos/
│   ├── transactions/
│   │   └── $id.tsx                                  # NEW: halaman detail transaksi + catat cicilan + riwayat pembayaran
│   └── invoices/
│       └── $id.tsx                                  # EDIT: render section pembayaran (R4 lengkap)
└── routes/$tenant/clinic/pos/components/
    ├── payment-form.tsx                             # NEW: form catat pembayaran (reuse FormSelect/FormInput/useForm)
    └── payment-history.tsx                          # NEW: tabel riwayat pembayaran (reuse DataTable)
# Reuse as-is (NO CHANGE):
#   components/forms/      — FormSelect, FormInput, FormCombobox, FormSubmit, useForm, applyServerErrors
#   components/datatable/  — DataTable, FacetedFilter, Pagination, Toolbar, ColumnHeader, ViewOptions
#   components/ui/status-badge.tsx — StatusBadge + PAYMENT_STATUS_VARIANTS
#   lib/format.ts          — formatCurrency
#   components/clinic-breadcrumb.tsx — ClinicBreadcrumb
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` sesuai eksisting. File BE baru: 1 migration (CHECK + FK restrict). Edit BE: 0 (intact) — mungkin lang key tambahan. File FE baru: 3 (`pos/transactions/$id.tsx`, `pos/components/payment-form.tsx`, `pos/components/payment-history.tsx`). Edit FE: 1 (`pos/invoices/$id.tsx` render payments). Reuse `components/forms/` + `components/datatable/` + `StatusBadge` + `formatCurrency` + `ClinicBreadcrumb` as-is per instruksi user. Test oleh `zahiira`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| (kosong — no violations) | | |
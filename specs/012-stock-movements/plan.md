# Implementation Plan: Integritas Mutasi Stok & Riwayat Stok Produk

**Branch**: `012-stock-movements` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/012-stock-movements/spec.md`

## Summary

Pelengkap spec 007 (Master Produk) + spec 008 (Transaksi POS) yang menggarap lima celah yang ditunda workflow normalisasi langkah 14: (1) **revisi skema `stock_movements`** — ganti kolom manual `related_type`/`related_id` menjadi `nullableMorphs('related')` + composite index `(related_type, related_id)` untuk reverse lookup; (2) **morph map konsisten** — daftarkan morph map di `AppServiceProvider` supaya `related_type` menyimpan alias stabil (bukan FQCN); (3) **audit log mutasi** — `StockService::adjust()` WAJIB catat activity log naratif "Menyesuaikan stok {product} — {type} {qty}" (saat ini tidak ada); (4) **reverse lookup per transaksi** — endpoint baru `GET /transactions/{transaction}/stock-movements` untuk menelusuri mutasi (sold_pos + rollback) satu transaksi via index morph; (5) **integritas tambahan** — guard saldo negatif (FR-015) + rollback idempoten (pembatalan berulang tidak duplikat mutasi, sudah ada via `cancelled_at` check di `CancelTransactionAction`). FE inti (halaman inventory + form + history) sudah ada — di-*improve* pakai `components/datatable/` reusable (DataTable + pagination + toolbar), tambah kolom transaksi terkait + link, dan state kosong manusiawi. Backend inti (model, enum, policy, controller, request, FK restrict migration) sudah ada dari 007/008 — tidak ditulis ulang.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13, apps/api); TypeScript (React 19, TanStack Start, apps/web)

**Primary Dependencies**: Laravel Sanctum, Eloquent, PostgreSQL; TanStack Router + Query, react-hook-form + zod, shadcn/ui `radix-nova`, Tailwind v4

**Storage**: PostgreSQL (multi-tenant single-DB, `tenant_id` pada semua child tabel)

**Testing**: PHPUnit (Feature/Unit, sqlite :memory: default + `phpunit.pgsql.xml` untuk constraint FK/index), vitest (FE)

**Target Platform**: Linux server (API), web browser (FE SSR)

**Project Type**: web-service (API) + web-app (FE) monorepo

**Performance Goals**: Riwayat stok produk hingga 100 mutasi terbuka < 5 detik (SC-008); reverse lookup per transaksi < 200ms via index morph.

**Constraints**: Multi-tenant isolation (constitution III), class PHP <= 300 baris / method <= 100 baris, komponen React <= 300 baris; FK `restrictOnDelete` PostgreSQL-only (SQLite skip, ponytail di migration 060000 eksisting).

**Scale/Scope**: 1 migration revisi (nullableMorphs + composite index morph), 1 edit service (audit log), 1 endpoint + route baru (reverse lookup), 1 edit AppServiceProvider (morph map), 2 edit FE (history pakai DataTable + reverse lookup, form saldo negatif feedback). 0 model/enum/policy/controller baru — reuse eksisting.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|--------|
| I. Clean Code | PASS | Reuse `components/forms/` + `components/datatable/` eksisting per instruksi user. StockService audit log = satu private method naratif, tidak duplikasi. Migration revisi minimal (drop+add index morph). |
| II. TDD | PASS | Test oleh `zahiira`: saldo konsisten (balance_after vs stock_balance), mutasi konkuren (race), immutability, reverse lookup akurat, invariant tenant, guard saldo negatif, rollback idempoten, audit log tercatat. Test task di tasks.md. PostgreSQL suite wajib untuk FK restrict + composite index. |
| III. Multi-Tenant Isolation | PASS | `tenant_id` mutasi inherit dari produk via `StockService` (anomali #3). TenantScope global scope aktif. Reverse lookup endpoint scoped via `StockMovementPolicy::viewAny` + tenant filter. Tidak ada query lintas-tenant. |
| IV. Simplicity (YAGNI) | PASS | Tidak buat model/enum/policy/controller baru — reuse eksisting. `nullableMorphs('related')` = helper migration (kolom + index sekali jalan), bukan manual 2 kolom + index terpisah. Reconcile job tertunda `ponytail`. |
| V. Bounded Size | PASS | StockService audit log = beberapa baris di method adjust (tetap < 100 baris method). FE history split bila perlu (history + reverse-lookup link); tetap < 300 baris per file. |
| VI. Permission & Activity Log | PASS | `StockService::adjust()` WAJIB `LogAuditAction::handle('inventory.stock.adjusted', …, "Menyesuaikan stok {product} — {type} {qty}")` — inilah celah utama yang dikerjakan. `withProperties` = full attributes mutasi (type, quantity, balance_after, product_id). Reverse lookup = read-only, tidak ada activity log. |

**Gate**: LULUS. Tidak ada pelanggaran yang perlu justifikasi Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/012-stock-movements/
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
├── database/migrations/
│   └── 2026_08_14_140000_revise_stock_movements_related_morph.php   # NEW: drop manual related_type/related_id + index, add nullableMorphs('related') (composite index otomatis)
├── app/
│   ├── Providers/
│   │   └── AppServiceProvider.php                                   # EDIT: Relation::enforceMorphMap(['transaction' => Transaction::class, ...])
│   ├── Services/
│   │   └── StockService.php                                        # EDIT: +LogAuditAction audit log naratif + guard saldo negatif
│   └── Http/Controllers/
│       └── StockMovementController.php                             # EDIT: +indexByTransaction (reverse lookup, FR-012)
└── routes/api.php                                                   # EDIT: +GET transactions/{transaction}/stock-movements
# Tidak ada model/enum/policy/request/resource baru — reuse eksisting:
#   StockMovement (morphTo related SUDAH ADA) — HANYA hapus related_type/related_id manual dari fillable (morph kelola sendiri)
#   StockMovementType (enum 4 value + isInbound) — SUDAH ADA
#   StockMovementPolicy (viewAny/view inventory, create/manage) — SUDAH ADA
#   StockMovementRequest (type in/out_manual, quantity gt:0, note) — SUDAH ADA
#   migration 060000 FK product_id restrictOnDelete — SUDAH ADA

apps/web/src/
├── routes/$tenant/clinic/inventory/
│   ├── index.tsx                                                    # EDIT: pass route untuk reverse lookup, refine layout
│   └── components/
│       ├── stock-movement-form.tsx                                  # EDIT: feedback saldo negatif + reuse FormSelect/FormInput
│       └── stock-movement-history.tsx                               # REWRITE: pakai DataTable reusable + kolom transaksi terkait + link + state kosong
└── routes/$tenant/clinic/inventory/components/
    └── stock-movement-reverse-lookup.tsx                            # NEW (opsional, bila reverse lookup punya UI sendiri) — atau bagian dari transactions/$id
# Reuse as-is (NO CHANGE) per instruksi user:
#   components/forms/      — FormSelect, FormInput, FormTextarea, FormSubmit, useForm, applyServerErrors, FormCombobox, FormDatePicker, FormPassword
#   components/datatable/  — DataTable, DataTablePagination, DataTableToolbar, DataTableColumnHeader, DataTableFacetedFilter, DataGridViewOptions
#   components/ui/         — Table primitives, Badge, Tooltip, Skeleton
#   components/clinic-breadcrumb.tsx — ClinicBreadcrumb
#   lib/api.ts, lib/format.ts, hooks/use-trans.ts
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` sesuai eksisting. File BE baru: 1 migration (nullableMorphs + composite index morph). Edit BE: 3 (`AppServiceProvider` morph map, `StockService` audit log + guard, `StockMovementController` +route reverse lookup). Edit FE: 3 (`inventory/index.tsx`, `stock-movement-form.tsx`, `stock-movement-history.tsx` rewrite pakai DataTable). File FE baru opsional: 1 (`stock-movement-reverse-lookup.tsx` bila reverse lookup puna UI terpisah, atau ditanam di detail transaksi `pos/transactions/$id.tsx` dari spec 011). Reuse `components/forms/` + `components/datatable/` + `ClinicBreadcrumb` as-is per instruksi user. Test oleh `zahiira`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| (kosong — no violations) | | |
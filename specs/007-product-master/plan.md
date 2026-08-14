# Implementation Plan: Master Produk Klinik

**Branch**: `007-product-master` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/007-product-master/spec.md`

## Summary

Revisi/penyempurnaan modul master produk yang sudah sebagian terimplementasi. Sembilan gap utama dipenuhi: (1) FK `stock_movements.product_id` & `transaction_items.product_id` diubah ke `restrictOnDelete` via migration (FR-068, R2); (2) `stock_balance` dikeluarkan dari input — `ProductRequest` hapus field, FE hapus input saldo, saldo diawali 0 hanya via `StockService::adjust()` (FR-060/063); (3) **layering Controller→Service→Action diperbaiki** — `ProductService` (NEW) mengorkestrasi create/update/archive, `CreateProductAction`/`UpdateProductAction`/`ArchiveProductAction` (NEW) eksekusi DB + audit log naratif via `LogAuditAction` (FR-073, R4) — modul produk saat ini langsung menyentuh Eloquent dari controller tanpa Service/Action/audit, melanggar layering CLAUDE.md + konstitusi VI; (4) `ProductController::index` default hanya `active` agar arsip tidak muncul di pilihan POS/inventory baru (FR-067, R8); (5) FE form modal jadi create+edit + hapus field saldo; (6) FE row-actions "Ubah"+"Arsipkan" + faceted filter status; (7) i18n keys tambahan; (8) test oleh `zahiira`; (9) snapshot immutability (FR-069) & no-direct-`stock_balance`-path (SC-007) diverifikasi via test. Form komponen eksisting (`FormInput/FormSelect/FormTextarea/FormSubmit/useForm`) tercover semua field — **tidak ada form komponen baru** di `components/forms/`. Hard-delete permanen tidak diekspos endpoint (YAGNI); DB restrict jadi penjaga FR-068.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13 API), TypeScript (TanStack Start, React 19).

**Primary Dependencies**:
- BE: spatie/laravel-activitylog ^5.1 (audit), spatie/laravel-permission ^8.3 (terpasang; produk pakai Gate matrix statik `ClinicPermission` — konstitusi VI exception, role fixed). Eloquent ORM.
- FE: react-hook-form, zod, @tanstack/react-query, shadcn/ui (radix-nova), Tailwind v4, hugeicons.

**Storage**: PostgreSQL (multi-tenant single-DB, tenant_id pada `products`).

**Testing**: PHPUnit/Pest (feature + unit, sqlite :memory: per phpunit.xml). Delegasi penulisan test ke agent `zahiira`.

**Target Platform**: Web — Laravel API port 8000, TanStack Start port 3001.

**Project Type**: Monorepo web app — `apps/api` (backend) + `apps/web` (frontend).

**Performance Goals**: Daftar produk tampil <1 detik untuk 500 produk per klinik (SC-002). Paginasi server-side max per_page 100.

**Constraints**: Class PHP <=300 baris, method <=100 baris (konstitusi V). File komponen React <=300 baris. Isolasi tenant otomatis via `BelongsToTenant`+`TenantScope` (konstitusi III). Teks UI via i18n (konstitusi V).

**Scale/Scope**: Revisi 1 entitas eksisting + 2 FK + ~6 file (edit) + 2 file FE baru (actions cell, factory test). Tanpa entity/tabel/kolom baru. Tanpa form komponen baru.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|---------|
| I. Clean Code | LULUS | Layering Controller→Service→Action lurus (R4). Reuse `StockService`, `LogAuditAction`, `BelongsToTenant`, form & datatable komponen eksisting. Tidak ada duplikasi. Method controller/service/action tetap <100 baris. |
| II. TDD | LULUS | Test task ditulis lebih dulu oleh `zahiira` (R9): feature controller via Service, Action audit, FK restrict, snapshot immutability, no-direct-`stock_balance`-path, low-stock equality, tenant isolation. Red-Green-Refactor. |
| III. Multi-Tenant Isolation | LULUS | `Product` pakai `BelongsToTenant`+`TenantScope` (sudah ada). Tidak ada query lintas-tenant baru. Test tenant isolation. |
| IV. Simplicity (YAGNI) | LULUS (dengan Complexity Tracking entry) | Tidak buat endpoint hard-delete (R7), tidak buat form komponen baru (R10). `ProductService` + 3 Action baru (R4) bukan over-abstraction — pemenuhan layering CLAUDE.md (controller wajib via service) + konstitusi VI (audit log wajib di Action). Justifikasi di Complexity Tracking. Snapshot & no-direct-saldo diverifikasi via test, bukan kode. |
| V. Bounded Size | LULUS | Semua file dalam batas. `ProductService` ~3 method kecil; tiap Action ~1 method `execute()` <40 baris + audit. `product-actions-cell.tsx` mirror `service-actions-cell` (~100 baris). Form modal create+edit tetap <300 baris. |
| VI. Permission & Activity Log | LULUS | `LogAuditAction` (spatie wrapper) di-inject ke tiap Action untuk audit naratif create/update/archive (R4). Permission via Gate matrix statik `ClinicPermission` (exception konstitusi VI: role fixed, sudah dicatat spec 001 R2). |

**Post-Phase 1 re-check**: desain data-model & contracts tidak menambah pelanggaran. Layering Service→Action memenuhi CLAUDE.md + VI. FK `restrictOnDelete` memperkuat integritas (mendukung III). Satu Complexity Tracking entry (R4: Service + 3 Action untuk operasi Eloquent trivial — justifikasi: layering + audit non-negotiable).

## Project Structure

### Documentation (this feature)

```text
specs/007-product-master/
├── plan.md              # this file
├── research.md          # Phase 0 — 11 keputusan (R1–R11)
├── data-model.md        # Phase 1 — entity Product + FK + invariant
├── quickstart.md        # Phase 1 — 9 skenario validasi
├── contracts/
│   └── products-api.md  # Phase 1 — endpoint contracts
└── tasks.md             # Phase 2 (/speckit-tasks — NOT this command)
```

### Source Code (repository root)

```text
apps/api/
├── database/
│   ├── migrations/
│   │   └── 2026_08_14_*_restrict_product_foreign_keys.php   # NEW (R2)
│   └── factories/
│       └── ProductFactory.php                               # NEW (R9, bila belum ada)
├── app/
│   ├── Actions/
│   │   └── Product/
│   │       ├── CreateProductAction.php                      # NEW (R4): Product::create + LogAuditAction
│   │       ├── UpdateProductAction.php                      # NEW (R4): update + old/new diff audit
│   │       └── ArchiveProductAction.php                     # NEW (R4): status=archived + audit (mirror ArchiveServiceAction)
│   ├── Services/
│   │   └── ProductService.php                               # NEW (R4): orkestrasi create/update/archive → Action; dilarang sentuh DB
│   └── Http/
│       ├── Controllers/
│       │   └── ProductController.php                        # EDIT: store/update/destroy via ProductService (R4) + index default active (R8); index/show read langsung
│       └── Requests/
│           └── ProductRequest.php                           # EDIT: hapus stock_balance dari rules (R3)
└── lang/id/
    ├── product.php                                          # EDIT: +edit/archive/archive_confirm/status labels (R11)
    └── inventory.php                                        # EDIT: isi key hilang (product/history/created_at/movement_recorded) (R11)

apps/web/src/
├── routes/$tenant/clinic/products/
│   ├── index.tsx                                            # EDIT: +actions column, +faceted filter status, kirim filter eksplisit (R8/R10)
│   └── components/
│       ├── product-form-modal.tsx                           # EDIT: hapus field stock_balance, jadi create+edit (R10)
│       └── product-actions-cell.tsx                         # NEW: row actions Ubah/Arsipkan (R10)
└── components/
    ├── datatable/                                           # NO CHANGE — pakai DataTableFacetedFilter eksisting
    └── forms/                                               # NO CHANGE — semua form komponen sudah ada & reusable
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` sesuai eksisting. File baru: 1 migration, 1 factory (test, bila belum ada), 1 Service + 3 Action (R4 layering+audit), 1 komponen FE (actions cell). Edit: 1 file BE (controller via Service + default active), 1 request, 2 lang, 2 file FE (index, form modal). Folder baru: `app/Actions/Product/` (folder per entity, aturan CLAUDE.md). Tidak ada form-komponen baru.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| `ProductService` + 3 Action (`Create/Update/ArchiveProductAction`) untuk operasi Eloquent CRUD yang trivial | Layering CLAUDE.md "Controller WAJIB via Service, Service dilarang sentuh DB, Action eksekusi unit kerja" (diperkuat commit `f72afc1`) + konstitusi VI "audit log naratif wajib di setiap Action ubah-data" | Log langsung di controller (spec 005 pattern) — ditolak: melanggar layering CLAUDE.md + penempatan audit VI; Audit di Service tanpa Action — ditolak: Service dilarang sentuh DB; Satu `SaveProductAction` gabungan — ditolak: satu Action = satu use case, audit shape berbeda (full vs old/new) |
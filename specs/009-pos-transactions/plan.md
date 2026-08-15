# Implementation Plan: Transaksi POS & Pembayaran

**Branch**: `main` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/009-pos-transactions/spec.md`

## Summary

Fitur transaksi POS **sudah terimplementasi sebagian besar di backend**, **frontend belum ada sama sekali**. Backend dasar lengkap (TransactionController, TransactionService, PayTransactionAction, CancelTransactionAction, TransactionRequest, PaymentRequest, TransactionResource, TransactionPolicy, InvoiceController+InvoiceService, PaymentController, 4 migration, routes) namun punya gap terhadap AC: (1) enum `PaymentStatus` hanya `unpaid`/`paid` — perlu `partially_paid`, (2) kolom `paid_amount` belum ada di migration/model, (3) `generateInvoiceNumber()` pakai `count()` tanpa `lockForUpdate` → race condition saat concurrent insert, (4) FK `patient_id`/`booking_id` masih `nullOnDelete` (hanya `cashier_id` sudah restrict), (5) belum ada soft-delete (`deleted_at`) + index `(tenant_id, deleted_at)`, (6) tidak ada audit log di PayTransaction/Cancel/Create/Service, (7) `TransactionRequest` belum enforce exclusive-arc (`product_id` XOR `service_id`) + aturan `booking_id` hanya bila booking `done`, (8) `PayTransactionAction` tidak set `partially_paid`. Frontend greenfield: halaman kasir POS (form transaksi dengan line-item dinamis) + daftar/riwayat transaksi (DataTable + badge status pembayaran 3-state + sisa bayar) + invoice view. Per instruksi user: reuse `components/datatable/` + `components/forms/` eksisting; buat komponen form reusable baru di `components/forms/` hanya bila belum tercover (line-item array dinamis tidak tercover → buat `FormRepeatableItems`).

## Technical Context

**Language/Version**: PHP 8.3+ (backend), TypeScript (frontend TanStack Start / React 19)

**Primary Dependencies**: Laravel 13 API, Sanctum token auth, spatie/laravel-permission (role dinamis via `SyncTenantClinicRolesAction::MATRIX`), spatie/laravel-activitylog (audit `LogAuditAction`), TanStack Router + Query, react-hook-form + zod + `useFieldArray`, shadcn/ui (radix-nova), date-fns. Semua sudah terpasang.

**Storage**: PostgreSQL (prod), SQLite (test `phpunit.xml` :memory:). Catatan: migration FK restrict di-skip pada SQLite (rebuild tabel tidak praktis) — skema produksi PostgreSQL tetap RESTRICT, preseden migration 033000.

**Testing**: PHPUnit/Pest (backend, `php artisan test`), Vitest (frontend, `bun run test`). Delegasi BE authoring → `ammar`, BE test → `zahiira`, FE → `sierly`.

**Target Platform**: Web (Laravel API port 8000 + TanStack Start port 3001)

**Project Type**: Web application (monorepo `apps/api` + `apps/web`)

**Performance Goals**: SC-015 — invoice number unik walau concurrent (race-safe); SC-014 catat transaksi <45 detik; daftar transaksi <1 detik @ ratusan transaksi/klinik.

**Constraints**: Method PHP ≤100 baris, class ≤300 baris (konstitusi V). Form: kasir POS = multi-step/line-item dinamis → **halaman terpisah** (bukan modal) sesuai aturan form design CLAUDE.md (>5 field + logika kompleks: tambah/hapus baris, hitung subtotal, pilih booking done). Komponen FE ≤300 baris.

**Scale/Scope**: Multi-tenant, ratusan transaksi per klinik. Tidak ada entitas/tabel baru — revisi `transactions` (kolom `paid_amount` + `deleted_at` + enum + FK) + relasi + FE greenfield.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|--------|
| I. Clean Code | PASS | Reuse komponen form/datatable eksisting; satu komponen form baru (`FormRepeatableItems`) punya ≥2 konsumen nyata (kasir POS + potensi edit transaksi) — bukan abstraksi prematur. Service tidak sentuh DB langsung (create via Action/unit kerja dalam DB transaction). Race-fix `invoice_number` terisolasi di satu method. |
| II. TDD | PASS | zahiira tulis test per task (feature + unit): concurrent invoice number, status 3-state, paid_amount atomik, FK restrict, soft-delete, exclusive-arc, booking done link, audit log naratif. |
| III. Multi-Tenant Isolation | PASS | `BelongsToTenant` + `TenantScope` aktif; transaksi tenant-scoped; `generateInvoiceNumber` query `withoutGlobalScope` + `where tenant_id` eksplisit (tidak bocor). Tidak ada celah lintas-tenant. |
| IV. Simplicity (YAGNI) | PASS | Merge `invoices` ke `transactions` (Anomali 1:1 YAGNI) — dipertahankan tabel dulu karena `issued_at`+relasi sudah dipakai `InvoiceService::render` & `InvoiceController`; merge = refactor besar di luar scope, catat `ponytail: merge saat butuh nomor invoice terpisah/multi-cetak`. Tidak ada endpoint baru selain yang eksisting. Tidak ada package baru. |
| V. Bounded Size | PASS | Semua file target ≤300 baris. TransactionService saat ini 114 baris — audit-log + race-fix ditambah tetap <200. FormRepeatableItems di-target <300. Kasir POS page split: form + ringkasan + komponen pendukung. |
| VI. Permission & Activity Log | PASS | Role dinamis spatie (`transaction.view`/`transaction.manage` via MATRIX: admin rw, cashier rw; doctor/therapist tidak punya modul transaction — 403). Audit log WAJIB ditambah di `TransactionService::create` (event `transaction.created`), `PayTransactionAction` (`transaction.payment_recorded`, narasi status lama→baru), `CancelTransactionAction` (`transaction.cancelled`). Sekarang **tidak ada audit log sama sekali** — gap kritis diisi. |

Tidak ada pelanggaran konstitusi — Complexity Tracking tidak perlu diisi.

## Project Structure

### Documentation (this feature)

```text
specs/009-pos-transactions/
├── plan.md              # This file
├── research.md          # Phase 0 — audit gap + decisions (race-fix, merge invoices, FE)
├── data-model.md        # Phase 1 — Transaction/Payment/Invoice revision
├── quickstart.md        # Phase 1 — validation guide
├── contracts/
│   └── transactions-api.md  # Phase 1 — endpoint contracts
└── tasks.md             # Phase 2 (/speckit-tasks — not this command)
```

### Source Code (repository root)

```text
apps/api/                                    # Backend Laravel
├── database/migrations/
│   ├── 2026_07_06_150000_create_transactions_table.php      # existing (perlu revisi via migration baru)
│   ├── 2026_07_06_150100_create_transaction_items_table.php # existing (exclusive-arc app-level, no DB change)
│   ├── 2026_07_06_150200_create_payments_table.php          # existing (no change)
│   ├── 2026_07_06_150300_create_invoices_table.php          # existing (no change — merge ditunda ponytail)
│   ├── 2026_08_14_033000_change_transactions_cashier_foreign_key_to_restrict.php  # existing (cashier done)
│   ├── 2026_08_14_*_add_paid_amount_soft_delete_to_transactions.php  # NEW — paid_amount + deleted_at + index
│   └── 2026_08_14_*_change_transactions_patient_booking_foreign_keys_to_restrict.php  # NEW — patient_id + booking_id restrict
├── app/Enums/
│   └── PaymentStatus.php                    # EDIT — tambah case PartiallyPaid
├── app/Models/
│   └── Transaction.php                      # EDIT — fillable paid_amount/deleted_at, casts, SoftDeletes, race-safe generateInvoiceNumber
├── app/Services/
│   └── TransactionService.php               # EDIT — audit log create; generateInvoiceNumber race-safe
├── app/Actions/
│   ├── PayTransactionAction.php             # EDIT — set paid_amount + partially_paid; audit log naratif status lama→baru
│   └── CancelTransactionAction.php          # EDIT — audit log cancel; guard sudah cancelled
├── app/Http/Requests/
│   ├── TransactionRequest.php               # EDIT — patient_id required; exclusive-arc product XOR service; booking_id must be done
│   └── PaymentRequest.php                   # existing (no change expected)
├── app/Http/Resources/
│   └── TransactionResource.php              # EDIT — expose paid_amount, booking_id, booking_label, is_cancelled, is_deleted, sisa_bayar
├── app/Http/Controllers/
│   ├── TransactionController.php            # EDIT — eager-load items+patient+booking; filter cancelled; soft-delete handler (destroy)
│   ├── PaymentController.php                # existing (audit via Action)
│   └── InvoiceController.php                # existing (no change)
├── app/Policies/
│   └── TransactionPolicy.php                # existing (delete = transaction.manage, already)
├── lang/id/
│   ├── pos.php                              # EDIT — add keys: cancel, balance_due, items_required, booking_done_only, payment_recorded, cancelled_log, create_log
│   └── clinic.php                           # EDIT — payment_status.partially_paid + payment_status_label
└── routes/api.php                           # existing (transactions resource only/index/store/show + payments + cancel + invoice); add destroy route bila soft-delete

apps/web/                                    # Frontend TanStack Start (greenfield POS)
├── src/routes/$tenant/clinic/pos/
│   ├── index.tsx                            # NEW — halaman kasir POS (form transaksi + line-item dinamis + ringkasan subtotal)
│   ├── transactions/
│   │   └── index.tsx                        # NEW — riwayat transaksi (DataTable + badge payment_status 3-state + sisa bayar + link invoice)
│   └── transactions/$id/
│       └── index.tsx                        # NEW — detail transaksi (items, payments, sisa bayar, aksi bayar/batal, cetak invoice)
├── src/routes/$tenant/clinic/pos/
│   └── components/
│       ├── transaction-form.tsx             # NEW — form kasir (useFieldArray items, pilih pasien/booking, submit) ≤300 baris
│       └── payment-dialog.tsx               # NEW — dialog catat pembayaran (method, amount, paid_at) ≤5 field
└── src/components/
    ├── datatable/                           # REUSE (DataTable, toolbar, pagination, faceted filter, column-header, view-options) — no new
    └── forms/
        ├── form-select.tsx                  # REUSE
        ├── form-input.tsx                   # REUSE
        ├── form-date-picker.tsx             # REUSE
        ├── form-submit.tsx                  # REUSE
        ├── use-form.ts                      # REUSE
        └── form-repeatable-items.tsx        # NEW — reusable line-item array (useFieldArray wrapper: add/remove row, select item, qty, computed subtotal) — instruksi user: reusable form baru di components/forms/
```

**Structure Decision**: Web application (Option 2). Monorepo `apps/api` + `apps/web` sudah ada — tidak ada struktur baru. Backend revisi file eksisting + 2 migration baru. Frontend greenfield POS di `src/routes/$tenant/clinic/pos/`. Sesuai instruksi user: reuse `components/datatable/` + `components/forms/` eksisting; komponen form baru (`form-repeatable-items.tsx`) reusable disimpan di `components/forms/` karena line-item dinamis tidak tercover komponen eksisting dan dipakai ≥1 halaman (kasir POS) dengan potensi reuse edit transaksi/treatment.

## Complexity Tracking

> Tidak ada pelanggaran Constitution Check — tabel ini kosong.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
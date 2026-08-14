# Implementation Plan: Master Layanan Klinik

**Branch**: `005-service-master` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-service-master/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command.

## Summary

Revisi/penyempurnaan modul master layanan klinik yang sudah sebagian terimplementasi. Empat gap utama dipenuhi: (1) FK `bookings/treatment_records/transaction_items → services` diubah ke `restrictOnDelete` via migration (FR-015, R6); (2) `ServiceController::index` default hanya tampilkan layanan `active` agar arsip tidak muncul di pilihan booking/POS/medical-record baru (FR-014); (3) activity log naratif via `LogAuditAction` untuk create/update/archive (FR-017); (4) FE: perbaikan breadcrumb (self-link bug), modal create diperluas jadi create+edit, tambah row-actions "Ubah"+"Arsipkan" mirror `StaffActionsCell`, faceted filter status. Snapshot immutability (FR-016) diverifikasi via test, bukan kode baru. Form komponen eksisting (`FormInput/FormTextarea/FormSelect/FormSubmit/useForm`) tercover semua field — **tidak ada form komponen baru** di `components/forms/`. Hard-delete permanen tidak diekspos endpoint (YAGNI); DB restrict jadi penjaga FR-015.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13 API), TypeScript (TanStack Start, React 19).

**Primary Dependencies**:
- BE: spatie/laravel-activitylog ^5.1 (audit), spatie/laravel-permission ^8.3 (terpasang; service pakai Gate matrix statik `ClinicPermission` — konstitusi VI exception, role fixed). Eloquent ORM.
- FE: react-hook-form, zod, @tanstack/react-query, shadcn/ui (radix-nova), Tailwind v4, hugeicons.

**Storage**: PostgreSQL (multi-tenant single-DB, tenant_id pada `services`).

**Testing**: PHPUnit/Pest (feature + unit, sqlite :memory: per phpunit.xml). Delegasi penulisan test ke agent `zahiira`.

**Target Platform**: Web — Laravel API port 8000, TanStack Start port 3001.

**Project Type**: Monorepo web app — `apps/api` (backend) + `apps/web` (frontend).

**Performance Goals**: Daftar layanan tampil <1 detik untuk 200 layanan per klinik (SC-002). Paginasi server-side max per_page 100.

**Constraints**: Class PHP <=300 baris, method <=100 baris (konstitusi V). File komponen React <=300 baris. Isolasi tenant otomatis via `BelongsToTenant`+`TenantScope` (konstitusi III). Teks UI via i18n (konstitusi V).

**Scale/Scope**: Revisi 1 entitas eksisting + 3 FK + ~6 file FE. Tanpa entity/tabel baru.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|---------|
| I. Clean Code | LULUS | Reuse Action (`ArchiveServiceAction`), Service (`LogAuditAction`), form components eksisting. Tidak ada duplikasi. Method `ServiceController` tetap <100 baris. |
| II. TDD | LULUS | Test task ditulis lebih dulu oleh `zahiira` (R9): feature controller, FK restrict, snapshot immutability, validation. Red-Green-Refactor. |
| III. Multi-Tenant Isolation | LULUS | `Service` pakai `BelongsToTenant`+`TenantScope` (sudah ada). Tidak ada query lintas-tenant baru. Test tenant isolation. |
| IV. Simplicity (YAGNI) | LULUS | Tidak buat endpoint hard-delete (R2), tidak buat form komponen baru (R6), tidak buat Service/Action baru untuk create/update (log di Controller). Snapshot diverifikasi via test, bukan kode. |
| V. Bounded Size | LULUS | Semua file dalam batas. `service-actions-cell.tsx` mirror `StaffActionsCell` (~100 baris). Modal create+edit tetap <300 baris. |
| VI. Permission & Activity Log | LULUS | `LogAuditAction` (spatie wrapper) untuk audit naratif. Permission via Gate matrix statik `ClinicPermission` (exception konstitusi VI: role fixed, sudah dicatat spec 001 R2). |

**Post-Phase 1 re-check**: desain data-model & contracts tidak menambah pelanggaran. FK `restrictOnDelete` memperkuat integritas (mendukung III). Tidak ada Complexity Tracking entry.

## Project Structure

### Documentation (this feature)

```text
specs/005-service-master/
├── plan.md              # this file
├── research.md          # Phase 0 — 10 keputusan (R1–R10)
├── data-model.md        # Phase 1 — entity Service + relasi + snapshot
├── quickstart.md        # Phase 1 — 7 skenario validasi
├── contracts/
│   └── services-api.md  # Phase 1 — endpoint contracts
└── tasks.md             # Phase 2 (/speckit-tasks — NOT this command)
```

### Source Code (repository root)

```text
apps/api/
├── database/
│   ├── migrations/
│   │   └── 2026_08_14_*_restrict_service_foreign_keys.php   # NEW (R1)
│   └── factories/
│       └── ServiceFactory.php                               # NEW (R9, untuk test)
├── app/
│   ├── Actions/
│   │   └── ArchiveServiceAction.php                         # EDIT: inject LogAuditAction (R4)
│   └── Http/
│       └── Controllers/
│           └── ServiceController.php                        # EDIT: index default active (R3) + log store/update (R4)
└── lang/id/
    └── service.php                                          # EDIT: +edit/archive/archive_confirm (R7)

apps/web/src/
├── routes/$tenant/clinic/services/
│   ├── index.tsx                                            # EDIT: fix breadcrumb, +actions column, +faceted filter
│   └── components/
│       ├── service-form-dialog.tsx                          # NEW/RENAME: create+edit modal (R6)
│       └── service-actions-cell.tsx                         # NEW: row actions (R6)
└── components/
    └── forms/                                               # NO CHANGE — semua form komponen sudah ada & reusable
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` sesuai eksisting. File baru minimal: 1 migration, 1 factory (test), 2 komponen FE (1 rename + 1 baru). Edit: 3 file BE (action, controller, lang) + 1 file FE (index). Tidak ada folder/package baru.

## Complexity Tracking

> Tidak ada pelanggaran konstitusi yang perlu justifikasi. Tabel ini kosong.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
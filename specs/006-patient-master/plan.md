# Implementation Plan: Master Pasien Klinik

**Branch**: `006-patient-master` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-patient-master/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command.

## Summary

Revisi/penyempurnaan modul master pasien klinik yang sudah sebagian terimplementasi. Lima gap utama dipenuhi: (1) `Patient` ditambah soft delete (`SoftDeletes` trait + kolom `deleted_at` + index `(tenant_id, deleted_at)`) untuk aksi nonaktifkan (FR-025/026); (2) FK `bookings/medical_records/transactions → patients` diubah ke `restrictOnDelete` via migration (FR-027); (3) route `destroy` ditambahkan kembali sebagai aksi **nonaktifkan** (soft delete) via `DeactivatePatientAction` + `LogAuditAction` narasi "Menonaktifkan pasien {name}" (FR-029); hard-delete permanen tidak diekspos endpoint — DB restrict jadi penjaga FR-027; (4) activity log naratif untuk create/update/deactivate (FR-029); (5) FE: perbaikan breadcrumb (self-link bug di `index` & `history`), tambah field `notes` di form, tambah row-actions "Ubah"+"Nonaktifkan" mirror `StaffActionsCell`, duplicate-warning di halaman edit, nama pasien di breadcrumb riwayat. Komponen form eksisting (`FormInput/FormDatePicker/FormSelect/FormTextarea/FormSubmit/useForm`) tercover semua field pasien (7 field > 5 → halaman terpisah, bukan modal) — **tidak ada form komponen baru** di `components/forms/`. Riwayat pasien (FR-022) tetap dapat diakses walau pasien dinonaktifkan (route `history` resolve `withTrashed`).

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13 API), TypeScript (TanStack Start, React 19).

**Primary Dependencies**:
- BE: spatie/laravel-activitylog (audit via `LogAuditAction`), spatie/laravel-permission (terpasang; patient pakai Gate matrix statik `ClinicPermission` — konstitusi VI exception, role fixed). Eloquent ORM + `SoftDeletes`.
- FE: react-hook-form, zod, @tanstack/react-query, shadcn/ui (radix-nova), Tailwind v4, hugeicons.

**Storage**: PostgreSQL (multi-tenant single-DB, `tenant_id` pada `patients`).

**Testing**: PHPUnit/Pest (feature + unit, sqlite :memory: per phpunit.xml). Delegasi penulisan test ke agent `zahiira`.

**Target Platform**: Web — Laravel API port 8000, TanStack Start port 3001.

**Project Type**: Monorepo web app — `apps/api` (backend) + `apps/web` (frontend).

**Performance Goals**: Daftar pasien aktif tampil <1 detik untuk 500 pasien per klinik (SC-002). Paginasi server-side max per_page 100.

**Constraints**: Class PHP <=300 baris, method <=100 baris (konstitusi V). File komponen React <=300 baris. Isolasi tenant otomatis via `BelongsToTenant`+`TenantScope` (konstitusi III). Teks UI via i18n (konstitusi V).

**Scale/Scope**: Revisi 1 entitas eksisting + 3 FK + ~5 file FE. Tanpa entity/tabel baru.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|---------|
| I. Clean Code | LULUS | Reuse `LogAuditAction`, `DeactivatePatientAction` (mirror `RemoveUserAction`/`ArchiveServiceAction`), form components eksisting. Tidak ada duplikasi. Method `PatientController` tetap <100 baris. |
| II. TDD | LULUS | Test task ditulis lebih dulu oleh `zahiira` (R8): feature controller (CRUD+deactivate+duplicate+tenant isolation), FK restrict, soft-delete+history accessibility, validation. Red-Green-Refactor. |
| III. Multi-Tenant Isolation | LULUS | `Patient` pakai `BelongsToTenant`+`TenantScope` (sudah ada). Soft delete tidak menambah query lintas-tenant. Test tenant isolation. |
| IV. Simplicity (YAGNI) | LULUS | Tidak buat endpoint hard-delete (R3), tidak buat form komponen baru (R7), `DeactivatePatientAction` tipis (soft delete + log). Form pasien 7 field > 5 → halaman terpisah sesuai aturan form design (bukan modal over-engineering). |
| V. Bounded Size | LULUS | Semua file dalam batas. `patient-actions-cell.tsx` mirror `StaffActionsCell` (~110 baris). `patient-form.tsx` +7 field tetap <300 baris. |
| VI. Permission & Activity Log | LULUS | `LogAuditAction` (spatie wrapper) untuk audit naratif create/update/deactivate. Permission via Gate matrix statik `ClinicPermission` (exception konstitusi VI: role fixed, sudah dicatat spec 001 R2). |

**Post-Phase 1 re-check**: desain data-model & contracts tidak menambah pelanggaran. `restrictOnDelete` memperkuat integritas (mendukung III + riwayat utuh). Soft delete menjaga data historis (R6 spirit). Tidak ada Complexity Tracking entry.

## Project Structure

### Documentation (this feature)

```text
specs/006-patient-master/
├── plan.md              # this file
├── research.md          # Phase 0 — 8 keputusan (R1–R8)
├── data-model.md        # Phase 1 — entity Patient + relasi + soft delete
├── quickstart.md        # Phase 1 — 7 skenario validasi
├── contracts/
│   └── patients-api.md  # Phase 1 — endpoint contracts
└── tasks.md             # Phase 2 (/speckit-tasks — NOT this command)
```

### Source Code (repository root)

```text
apps/api/
├── database/
│   ├── migrations/
│   │   ├── 2026_07_06_120000_create_patients_table.php          # EDIT: +softDeletes() + index(tenant_id,deleted_at) (R1)
│   │   └── 2026_08_14_*_restrict_patient_foreign_keys.php       # NEW (R2): drop+recreate 3 FK → restrictOnDelete
│   └── factories/
│       └── PatientFactory.php                                   # NEW (R8, untuk test)
├── app/
│   ├── Actions/
│   │   └── DeactivatePatientAction.php                          # NEW (R3): soft delete + LogAuditAction
│   ├── Models/
│   │   └── Patient.php                                          # EDIT: +SoftDeletes, +deleted_at fillable/cast (R1)
│   └── Http/
│       ├── Controllers/
│       │   └── PatientController.php                            # EDIT: +destroy (R3), +LogAuditAction store/update (R4), history withTrashed (R5), +duplicate on update
│       └── Resources/
│           └── PatientResource.php                              # EDIT: +deleted_at (status nonaktif indicator)
└── lang/id/
    └── patient.php                                              # EDIT: +duplicate_warning/history_empty/deactivate/deactivate_confirm/deactivated (R6)

apps/web/src/
├── routes/$tenant/clinic/patients/
│   ├── index.tsx                                                # EDIT: fix breadcrumb, +actions column (R7)
│   ├── new.tsx                                                  # EDIT: duplicate key fix (R6)
│   ├── $id/edit.tsx                                             # EDIT: +duplicate warning handling (R7)
│   ├── $id/history.tsx                                          # EDIT: fix breadcrumb + patient name (R7)
│   └── components/
│       ├── patient-form.tsx                                     # EDIT: +notes field (R7)
│       └── patient-actions-cell.tsx                             # NEW: row actions (R7)
└── components/
    ├── datatable/                                               # NO CHANGE — sudah ada & reusable
    └── forms/                                                   # NO CHANGE — semua form komponen sudah ada & reusable
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` sesuai eksisting. File baru minimal: 1 migration FK, 1 Action, 1 factory (test), 1 komponen FE (`patient-actions-cell.tsx`). Edit: 2 migration/model BE, 1 controller, 1 resource, 1 lang, 5 file FE. Tidak ada folder/package/form-komponen baru.

## Complexity Tracking

> Tidak ada pelanggaran konstitusi yang perlu justifikasi. Tabel ini kosong.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
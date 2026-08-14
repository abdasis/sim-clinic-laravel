# Implementation Plan: Master Pasien Klinik

**Branch**: `006-patient-master` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-patient-master/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command.

## Summary

Revisi/penyempurnaan modul master pasien klinik yang sudah sebagian terimplementasi. Enam gap utama dipenuhi: (1) `Patient` ditambah soft delete (`SoftDeletes` trait + kolom `deleted_at` + index `(tenant_id, deleted_at)`) untuk aksi nonaktifkan (FR-025/026); (2) FK `bookings/medical_records/transactions → patients` diubah ke `restrictOnDelete` via migration (FR-027); (3) route `destroy` ditambahkan kembali sebagai aksi **nonaktifkan** (soft delete) via `DeactivatePatientAction` + `LogAuditAction` narasi "Menonaktifkan pasien {name}" dengan `withProperties` old/new (FR-029); hard-delete permanen tidak diekspos endpoint — DB restrict jadi penjaga FR-027; (4) **Layering Controller→Service→Action** (konstitusi v1.1.0 VI + CLAUDE.md baru): `PatientService` orkestrasi (no DB) memanggil `Create/Update/DeactivatePatientAction` (folder `app/Actions/Patient/`) masing-masing satu use case; Action log via `LogAuditAction` dengan `withProperties` (create→full attributes, update→old+new, deactivate→old+new) naratif (FR-029); Controller hanya authorize + resolve `PatientRequest` + panggil Service + response, **tidak menyentuh DB & tidak langsung ke Action**; validasi via FormRequest (no inline validation); read (index/show/history) di Controller (read exception); (5) FE: perbaikan breadcrumb (self-link bug di `index` & `history`), tambah field `notes` di form (`FormTextarea` reuse), tambah row-actions "Ubah"+"Nonaktifkan" mirror `StaffActionsCell` (`components/datatable/` reuse), duplicate-warning di halaman edit, nama pasien di breadcrumb riwayat; (6) permission: `ClinicPermission::MATRIX` + Gate `clinic.access` dengan `ponytail:` exception konstitusi VI (role fixed, bukan DB-driven) — `SyncTenantClinicRolesAction` sudah jembatan ke spatie Role/Permission. Komponen form eksisting (`FormInput/FormDatePicker/FormSelect/FormTextarea/FormSubmit/useForm`) tercover semua field pasien (7 field > 5 → halaman terpisah, bukan modal) — **tidak ada form komponen baru** di `components/forms/`. Riwayat pasien (FR-022) tetap dapat diakses walau pasien dinonaktifkan (route `history` resolve `withTrashed`).

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

**Constraints**: Class PHP <=300 baris, method <=100 baris (konstitusi V). File komponen React <=300 baris. Isolasi tenant otomatis via `BelongsToTenant`+`TenantScope` (konstitusi III). Teks UI via i18n (konstitusi V). **Layering Controller→Service→Action** (CLAUDE.md: Controller WAJIB via Service, dilarang langsung ke Action; Service DILARANG sentuh DB, WAJIB via Action; read exception: Controller boleh query read langsung). **DB write WAJIB via Action** (konstitusi v1.1.0 VI). **Action ubah-data WAJIB log via `activity()`/`LogAuditAction`** dengan `withProperties` (create→full, update→old+new). **Validasi WAJIB via FormRequest** (dilarang inline validation di Controller). **Exception ditangkap WAJIB `Log::error`**. Folder per entity `app/Actions/<Entity>/`. Permission: role dinamis WAJIB spatie; role statik fixed boleh enum+Gate matrix **dengan `ponytail:`** (konstitusi VI).

**Scale/Scope**: Revisi 1 entitas eksisting + 3 FK + ~5 file FE. Tanpa entity/tabel baru.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|---------|
| I. Clean Code | LULUS | Reuse `LogAuditAction` (satu pintu audit), `PatientService` orkestrasi tipis, tiga Action tipis masing-masing satu responsibility (`Create/Update/DeactivatePatientAction`, mirror `ArchiveServiceAction`/`RemoveUserAction`), form & datatable components eksisting (reuse). Controller kembali ke authorize+resolve FormRequest+panggil Service+response (<100 baris/method). Tidak ada duplikasi. |
| II. TDD | LULUS | Test task ditulis lebih dulu oleh `zahiira` (R8): feature controller (CRUD+deactivate+duplicate+tenant isolation+withProperties), **action unit tests** (`withProperties` create→full/update→old+new/deactivate→old+new + exception `Log::error`), **service unit test** (orkestrasi panggil Action + duplikat), FK restrict, soft-delete+history accessibility, validation. Red-Green-Refactor. |
| III. Multi-Tenant Isolation | LULUS | `Patient` pakai `BelongsToTenant`+`TenantScope` (sudah ada). Soft delete tidak menambah query lintas-tenant. Test tenant isolation. |
| IV. Simplicity (YAGNI) | LULUS | Tidak buat endpoint hard-delete (R3), tidak buat form/datatable komponen baru (R7 — semua field & tabel tercover komponen eksisting), tiga Action tipis (single write + log, tanpa `DB::transaction` multi-write). `PatientService` tipis (orkestrasi panggil Action + duplikat detection) — wajib ada demi layering CLAUDE.md, bukan over-engineering. Form pasien 7 field > 5 → halaman terpisah sesuai aturan form design. |
| V. Bounded Size | LULUS | Semua file dalam batas. `patient-actions-cell.tsx` mirror `StaffActionsCell` (~110 baris). `patient-form.tsx` +1 field `notes` tetap <300 baris. Tiga Action masing-masing <50 baris. `PatientService` <80 baris. Controller method tetap <100 baris. |
| VI. Permission & Activity Log | LULUS | **Layering Controller→Service→Action** + **DB write via Action** (`Create/Update/DeactivatePatientAction`) + **log via `LogAuditAction`** (spatie `activity()`) dengan **`withProperties` old/new** (create→full, update→old+new, deactivate→old+new) naratif (FR-029). Validasi via `PatientRequest` (no inline validation). Read exception: index/show/history di Controller. Exception ditangkap → `Log::error`. Permission via Gate matrix statik `ClinicPermission` + `SyncTenantClinicRolesAction` sinkron ke spatie — **`ponytail:` exception konstitusi VI** (role fixed; migrasi penuh ke spatie `hasRole`/`can` saat role jadi dinamis). |

**Post-Phase 1 re-check**: desain data-model & contracts tidak menambah pelanggaran; justru memperkuat kepatuhan konstitusi v1.1.0 + CLAUDE.md baru — `withProperties` old/new di contracts (VI), Controller→Service→Action + DB write via Action dijabarkan di data-model (VI), validasi via FormRequest, `ponytail:` permission exception dicatat (VI). `restrictOnDelete` memperkuat integritas (III + riwayat utuh). Soft delete menjaga data historis (R6 spirit). Tidak ada Complexity Tracking entry.

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
│   │   └── Patient/                                             # NEW folder per entity (R3, R4)
│   │       ├── CreatePatientAction.php                          # NEW (R4): Patient::create + LogAuditAction withProperties full
│   │       ├── UpdatePatientAction.php                          # NEW (R4): update + LogAuditAction withProperties old/new
│   │       └── DeactivatePatientAction.php                      # NEW (R3): soft delete + LogAuditAction withProperties old/new
│   ├── Services/
│   │   └── PatientService.php                                   # NEW (R4): orkestrasi create/update/deactivate → panggil Action + duplicate detection (no DB write)
│   ├── Models/
│   │   └── Patient.php                                          # EDIT: +SoftDeletes, +deleted_at fillable/cast (R1)
│   ├── Policies/
│   │   └── PatientPolicy.php                                    # EDIT: +delete(User) delegasi clinic.access ['patient','w'] (R3)
│   └── Http/
│       ├── Controllers/
│       │   └── PatientController.php                            # EDIT: +destroy (R3); store/update/destroy → panggil PatientService (R4, Controller→Service→Action, no direct DB/Action); index/show/history read langsung (read exception) + history/show withTrashed (R5)
│       ├── Requests/
│       │   └── PatientRequest.php                               # EDIT: pastikan semua validasi field (name/phone/birth_date/gender/whatsapp/address/notes) di sini, no inline validation di Controller
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

**Structure Decision**: Monorepo `apps/api` + `apps/web` sesuai eksisting. File baru minimal: 1 migration FK, 1 Service (`PatientService`), 3 Action (folder per entity `app/Actions/Patient/`), 1 factory (test), 1 komponen FE (`patient-actions-cell.tsx`). Edit: 1 migration + 1 model + 1 policy + 1 controller + 1 request + 1 resource + 1 lang BE, 5 file FE. Tidak ada package/form-komponen baru. Layering konsisten Controller→Service→Action; DB write hanya di Action (konstitusi v1.1.0 VI + CLAUDE.md).

## Complexity Tracking

> Tidak ada pelanggaran konstitusi yang perlu justifikasi. Tabel ini kosong.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
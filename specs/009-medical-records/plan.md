# Implementation Plan: Rekam Medis SOAP Klinik

**Branch**: `009-medical-records` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/009-medical-records/spec.md`

## Summary

Revisi/penyempurnaan modul rekam medis SOAP yang sudah sebagian terimplementasi. Modul existing punya model, migration, service, action (create), request, policy, resource, controller (store/addTreatment/addPhoto), dan 3 route. Sepuluh gap utama dipenuhi: (1) **soft delete** `deleted_at` + `SoftDeletes` trait + index `(tenant_id, deleted_at)` (FR-090); (2) **index `(tenant_id, patient_id, created_at)`** untuk query riwayat per pasien (FR-022); (3) **FK `booking_id`+`patient_id` `cascadeOnDelete`→`restrictOnDelete`** (FR-093, author_id sudah restrict); (4) **FK child `medical_record_id` (treatment_records + medical_photos) `cascadeOnDelete`→`restrictOnDelete`** — blokir hard-delete parent bila child ada (FR-092, **override workflow langkah 15/16 cascade** per user AC); (5) **endpoint index/show/update/destroy** + route `GET patients/{patient}/medical-records` riwayat per pasien (FR-022/096); (6) **`UpdateMedicalRecordAction`** edit SOAP + audit diff old/new (FR-094); (7) **`SoftDeleteMedicalRecordAction`** soft-delete + audit (FR-090); (8) audit narasi create "Mengisi rekam medis pasien {patient}" (FR-094, perbaiki narasi existing "Menulis rekam medis untuk {patient}"); (9) `MedicalRecordResource` expose `patient_name`/`deleted_at`/`updated_at`/`booking` summary; (10) FE form rekam medis SOAP + riwayat per pasien + breadcrumb, **reuse penuh `components/forms/` + `components/datatable/` + `ClinicBreadcrumb`**, 0 komponen baru. Immutability `patient_id` booking (anomali #2) **sudah diimplementasi** di booking side (commit `feat(booking): kunci pasien setelah rekam medis`) — spec 009 hanya verifikasi + catat invariant. Test oleh `zahiira`. Migration SQLite vs PostgreSQL guard driver (R8). Hard-delete tidak diekspos endpoint; DB restrict + soft-delete jadi penjaga integritas.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13 API), TypeScript (TanStack Start, React 19).

**Primary Dependencies**:
- BE: spatie/laravel-activitylog ^5.1 (audit via `LogAuditAction`/`activity()`), Eloquent ORM, PostgreSQL. Enum `BookingStatus` native PHP (dipakai cek booking done).
- FE: react-hook-form, zod, @tanstack/react-query + @tanstack/react-table, shadcn/ui (radix-nova), Tailwind v4, hugeicons. `components/forms/` (`FormInput`/`FormSelect`/`FormTextarea`/`FormSubmit`/`useForm`+`applyServerErrors`), `components/datatable/` (`DataTable`/`Toolbar`/`Pagination`/`ColumnHeader`/`FacetedFilter`/`ViewOptions`), `components/clinic-breadcrumb.tsx` (`ClinicBreadcrumb`) sudah ada — reuse.

**Storage**: PostgreSQL (multi-tenant single-DB, `tenant_id` pada `medical_records`/`treatment_records`/`medical_photos`). SQLite :memory: untuk test cepat (FK restrict diverifikasi via `phpunit.pgsql.xml`).

**Testing**: PHPUnit/Pest (feature + unit, sqlite :memory: per phpunit.xml; pgsql suite sebelum rilis). Delegasi penulisan test ke agent `zahiira`.

**Target Platform**: Web — Laravel API port 8000, TanStack Start port 3001.

**Project Type**: Monorepo web app — `apps/api` (backend) + `apps/web` (frontend).

**Performance Goals**: Riwayat rekam medis per pasien tampil <1 detik untuk pasien dengan hingga 100 kunjungan (SC-005) via index `(tenant_id, patient_id, created_at)`.

**Constraints**: Class PHP <=300 baris, method <=100 baris (konstitusi V). File komponen React <=300 baris. Isolasi tenant otomatis via `BelongsToTenant`+`TenantScope` (konstitusi III). Teks UI via i18n (konstitusi V). Layering Controller→Service→Action satu arah (CLAUDE.md). Service dilarang sentuh DB langsung.

**Scale/Scope**: Multi-tenant, belasan klinik, ribuan rekam medis/tenant. Rekam medis = modul inti klinis (US7) + aggregate root treatment/photo.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Catatan |
|-----------|--------|---------|
| I. Clean Code | PASS | Layering Controller→Service→Action diperkuat; Action satu use case (Create/Update/SoftDelete); Service orkestrasi tanpa sentuh DB. No dead code. |
| II. TDD | PASS | Test oleh `zahiira`: feature test endpoint (index/show/store/update/destroy/riwayat pasien) + unit test Action (Update diff, SoftDelete, booking done guard, unique booking, audit naratif). Red-Green-Refactor. |
| III. Multi-Tenant Isolation | PASS | `BelongsToTenant`+`TenantScope` otomatis; index selalu lead `tenant_id`; riwayat pasien scoped tenant+patient. Test tenant isolation. |
| IV. Simplicity (YAGNI) | PASS | Reuse FE penuh (forms/datatable/breadcrumb) — 0 komponen baru untuk medical record (booking_id dari route, riwayat nested patient). Tidak buat `FormCombobox` (milik 008, medical record tidak butuh). Tidak buat `SoapNoteFields` extract (1 form create/edit, inline 4 `FormTextarea`). `ponytail:` untuk restore/reconcile. |
| V. Bounded Size | PASS | Class <=300, method <=100. FE komponen <=300. Extract bila melebihi. |
| VI. Permission & Activity Log | PASS | `MedicalRecordPolicy` Gate `medical_record.view`/`medical_record.manage` (FR-044, role statik dokter/terapis/admin — exception konstitusi VI, `ponytail:`). `LogAuditAction` naratif tiap Action ubah-data (create/update/soft-delete). `withProperties` old/new untuk update, full untuk create. Exception di-log `Log::error`. |

No violations. Complexity Tracking kosong.

## Project Structure

### Documentation (this feature)

```text
specs/009-medical-records/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── medical-records-api.md
└── tasks.md             # Phase 2 (/speckit-tasks — NOT this command)
```

### Source Code (repository root)

```text
apps/api/
├── database/
│   └── migrations/
│       └── 2026_08_14_*_add_medical_records_softdelete_index_restrict_fk.php  # NEW: +deleted_at softDeletes, +index(tenant_id,deleted_at), +index(tenant_id,patient_id,created_at), FK booking_id/patient_id cascade→restrict (guard pgsql), FK child medical_record_id cascade→restrict (guard pgsql)
├── app/
│   ├── Models/
│   │   └── MedicalRecord.php                       # EDIT: +SoftDeletes, +deleted_at fillable+cast
│   ├── Actions/
│   │   └── MedicalRecord/
│   │       ├── CreateMedicalRecordAction.php       # EDIT: narasi audit "Mengisi rekam medis pasien {patient}" (FR-094)
│   │       ├── UpdateMedicalRecordAction.php       # NEW: edit SOAP + audit diff old/new (FR-094)
│   │       └── SoftDeleteMedicalRecordAction.php   # NEW: soft-delete + audit (FR-090)
│   ├── Services/
│   │   └── MedicalRecordService.php                # EDIT: +update(), +softDelete(), narasi create via action
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── MedicalRecordController.php         # EDIT: +index, +show, +update, +destroy, +patientRecords (rename patientTreatments→patientRecords, daftarkan route)
│   │   ├── Requests/
│   │   │   └── MedicalRecordRequest.php            # EDIT: +rules update (SOAP nullable, booking_id immutable saat update)
│   │   ├── Resources/
│   │   │   └── MedicalRecordResource.php           # EDIT: +patient_name, +deleted_at, +updated_at, +booking summary (status/tanggal)
│   │   └── Policies/
│   │       └── MedicalRecordPolicy.php             # EDIT: +delete method (soft-delete auth)
│   └── lang/id/
│       └── medical_record.php                      # EDIT: +updated, +deleted, +riwayat keys

apps/web/src/
└── routes/$tenant/clinic/
    ├── medical-records/
    │   ├── index.tsx                               # NEW: list rekam medis aktif (DataTable + search + pagination + breadcrumb)
    │   ├── $recordId.tsx                           # NEW: detail/edit rekam medis SOAP (form 4 FormTextarea + FormSubmit + useForm/zod + breadcrumb)
    │   └── new.tsx                                 # NEW: form isi rekam medis dari booking (booking_id dari route query ?booking=) + breadcrumb
    └── patients/
        └── $patientId/
            └── medical-records.tsx                 # NEW: riwayat rekam medis per pasien (DataTable kronologis + breadcrumb) — FR-022
```

**Structure Decision**: Monorepo `apps/api` + `apps/web` sesuai eksisting. File BE baru: 1 migration, 2 Action (Update, SoftDelete). Edit BE: 1 model, 1 service, 1 controller, 1 request, 1 resource, 1 policy, 1 lang, 1 action (narasi). File FE baru: 4 route page (`medical-records/index`, `medical-records/new`, `medical-records/$recordId`, `patients/$patientId/medical-records`). **Reuse `components/forms/`** (`FormTextarea`×4, `FormSubmit`, `useForm`+`applyServerErrors`) + **`components/datatable/`** (`DataTable`/`Toolbar`/`Pagination`/`ColumnHeader` as-is) + **`components/clinic-breadcrumb.tsx`** (`ClinicBreadcrumb`) as-is. **0 komponen baru di forms/datatable** — existing memenuhi kebutuhan medical record (4 textarea SOAP inline di satu form create/edit; booking_id dari route query, bukan combobox; riwayat via nested patient route). `FormCombobox` (rencana 008) tidak dibutuhkan di 009. Test oleh `zahiira`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| (kosong — no violations) | | |
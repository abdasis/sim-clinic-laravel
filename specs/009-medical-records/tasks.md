# Tasks: Rekam Medis SOAP Klinik

**Input**: Design documents from `/specs/009-medical-records/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/medical-records-api.md, quickstart.md

**Tests**: TDD WAJIB (konstitusi II). Test tasks ditulis lebih dulu (Red) oleh agent `zahiira` sebelum implementasi (Green). Backend authoring → `ammar`. Frontend authoring → `sierly`. Delegasi: FE → `sierly` langsung tanpa eksplorasi; BE → `ammar`; tests → `zahiira`.

**Organization**: Tasks grouped by user story (spec.md: US1 P1, US2 P2, US3 P3, US4 P4) for independent implementation & testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g. US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

- **Web app monorepo**: `apps/api/` (backend Laravel), `apps/web/` (frontend TanStack Start). Paths below repo-relative.
- Backend code: `apps/api/app/...`, migrations `apps/api/database/migrations/`, factories `apps/api/database/factories/`, tests `apps/api/tests/...`, lang `apps/api/lang/id/`.
- Frontend code: `apps/web/src/...`, routes `apps/web/src/routes/$tenant/clinic/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Migration schema revision (soft-delete, index patient+created_at, index deleted_at, FK restrict parent+child) + factories for test seeding.

- [ ] T001 Create migration to add `deleted_at` (softDeletes), index `(tenant_id, deleted_at)`, index `(tenant_id, patient_id, created_at)`, and change FK `medical_records.booking_id` + `medical_records.patient_id` from cascadeOnDelete → restrictOnDelete in apps/api/database/migrations/2026_08_14_add_medical_records_softdelete_index_restrict_fk.php (R1/R2/R3/R8, FR-090/093) — FK alter guard `if (Schema::getConnection()->getDriverName() === 'sqlite') { return; }` (SQLite skip; softDeletes+index jalan kedua driver)
- [ ] T002 Create migration to change FK `treatment_records.medical_record_id` + `medical_photos.medical_record_id` from cascadeOnDelete → restrictOnDelete in apps/api/database/migrations/2026_08_14_restrict_medical_record_child_foreign_keys.php (R2, FR-092, override workflow langkah 15/16 cascade) — guard `if (Schema::getConnection()->getDriverName() === 'sqlite') { return; }` (SQLite skip; `ForeignKeyRestrictTest` pgsql only)
- [ ] T003 [P] Create MedicalRecordFactory in apps/api/database/factories/MedicalRecordFactory.php — tenant_id, booking_id, patient_id, author_id, subjective/objective/assessment/plan nullable, deleted_at nullable — for test seeding
- [ ] T004 [P] Create TreatmentRecordFactory in apps/api/database/factories/TreatmentRecordFactory.php — tenant_id, medical_record_id, service_id nullable, service_name, notes nullable — for child test seeding
- [ ] T005 [P] Create MedicalPhotoFactory in apps/api/database/factories/MedicalPhotoFactory.php — tenant_id, medical_record_id, type (before/after), path — for child test seeding

**Checkpoint**: Migration + factories ready. Run `php artisan migrate` setelah implementasi. `php artisan test -c phpunit.pgsql.xml --filter=MedicalRecord` sebelum rilis (constraint restrict FK parent+child).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Model/Action/Service/Controller/Resource/Policy/route/i18n core revision — MUST complete before user stories. Shared oleh semua story.

**⚠️ CRITICAL**: Soft delete trait, index patient, FK restrict, endpoint index/show/update/destroy + riwayat pasien, audit naratif "Mengisi rekam medis pasien {patient}" — fondasi semua story.

- [ ] T006 Edit MedicalRecord model in apps/api/app/Models/MedicalRecord.php — `use SoftDeletes`; tambah `deleted_at` ke $fillable; tambah cast `deleted_at` => 'datetime'; verifikasi relasi booking/patient/author/treatmentRecords/medicalPhotos tetap (R1, FR-090)
- [ ] T007 Edit CreateMedicalRecordAction in apps/api/app/Actions/MedicalRecord/CreateMedicalRecordAction.php — ubah narasi audit dari "Menulis rekam medis untuk {patient}" → "Mengisi rekam medis pasien {patient}" (R5, FR-094); tetap set patient_id dari booking->patient_id; withProperties full SOAP attributes. Method <100 baris.
- [ ] T008 Create UpdateMedicalRecordAction in apps/api/app/Actions/MedicalRecord/UpdateMedicalRecordAction.php — `handle(MedicalRecord, array $data): MedicalRecord` update SOAP field (subjective/objective/assessment/plan, nullable) + LogAuditAction event `medical_record.updated` narasi "Memperbarui rekam medis pasien {patient}" properties old/new diff SOAP (R5, FR-094). Inject LogAuditAction (bukan Service). Method <100 baris.
- [ ] T009 Create SoftDeleteMedicalRecordAction in apps/api/app/Actions/MedicalRecord/SoftDeleteMedicalRecordAction.php — `handle(MedicalRecord): MedicalRecord` soft-delete ($record->delete()) + LogAuditAction event `medical_record.deleted` narasi "Menghapus rekam medis pasien {patient}" (R1, FR-090, R5). Inject LogAuditAction (bukan Service). Method <100 baris.
- [ ] T010 Edit MedicalRecordService in apps/api/app/Services/MedicalRecordService.php — tambah `update(MedicalRecord, array $data): MedicalRecord` via UpdateMedicalRecordAction; tambah `softDelete(MedicalRecord): MedicalRecord` via SoftDeleteMedicalRecordAction; tetap orkestrasi tanpa sentuh DB langsung (Service dilarang DB write). Method <100 baris.
- [ ] T011 Edit MedicalRecordRequest in apps/api/app/Http/Requests/MedicalRecordRequest.php — rules update: 4 field SOAP nullable|string; `booking_id` tidak di-accept saat update (immutable, dari route record); pisah rules create (booking_id required) vs update bila perlu (R4, FR-094)
- [ ] T012 Edit MedicalRecordResource in apps/api/app/Http/Resources/MedicalRecordResource.php — tambah `patient_name` ($this->patient?->name), `deleted_at`, `updated_at`, `booking` summary (id/status/start_at); tetap treatments + photos whenLoaded (R6, FR-096)
- [ ] T013 Edit MedicalRecordPolicy in apps/api/app/Policies/MedicalRecordPolicy.php — tambah `delete(User)` method return `$user->can('medical_record.manage')` (FR-090 soft-delete auth, admin/dokter/terapis); verifikasi viewAny/view/create/update tetap
- [ ] T014 Edit MedicalRecordController in apps/api/app/Http/Controllers/MedicalRecordController.php — tambah `index(Request)` (DataTable, read-only inject query langsung, FR-096), `show(MedicalRecord)` (authorize view, load relasi), `update(MedicalRecordRequest, MedicalRecord, MedicalRecordService)` (authorize update via Service, FR-094), `destroy(MedicalRecord, MedicalRecordService)` (authorize delete via Service soft-delete, FR-090), rename `patientTreatments`→`patientRecords(Patient)` + daftarkan route (FR-022). Method <100 baris.
- [ ] T015 Edit routes/api.php in apps/api/routes/api.php — tambah `GET medical-records` (index), `GET medical-records/{medicalRecord}` (show), `PATCH medical-records/{medicalRecord}` (update), `DELETE medical-records/{medicalRecord}` (destroy), `GET patients/{patient}/medical-records` (patientRecords, FR-022) ke clinic route group. Verifikasi route store/addTreatment/addPhoto tetap.
- [ ] T016 [P] Edit lang/id/medical_record.php — tambah key `updated` = "Rekam medis berhasil diperbarui.", `deleted` = "Rekam medis berhasil dihapus.", `created` verifikasi "Rekam medis berhasil diisi." (R5, FR-094/097); tambah key breadcrumb/label riwayat bila perlu

**Checkpoint**: Foundation ready — soft-delete trait, index patient, FK restrict, endpoint index/show/update/destroy + riwayat pasien, audit naratif "Mengisi rekam medis pasien {patient}". User story implementation dapat mulai.

---

## Phase 3: User Story 1 - Dokter Mengisi Rekam Medis SOAP dari Booking Selesai (Priority: P1) MVP

**Goal**: Dokter mengisi rekam medis SOAP (S/O/A/P) dari booking done; 1 per booking (R10); patient_id dari booking immutable; hanya dokter/terapis/admin; audit "Mengisi rekam medis pasien {patient}".

**Independent Test**: Buat satu booking done → isi rekam medis SOAP tanpa treatment/photo → tersimpan patient_id sesuai booking, author_id dokter, audit narasi "Mengisi rekam medis pasien {patient}". Tanpa modul riwayat/soft-delete.

### Tests for User Story 1 (TDD — write FIRST, must FAIL)

- [ ] T017 [P] [US1] Feature test: isi rekam medis dari booking done → 201, patient_id = pasien booking, author_id = dokter, SOAP tersimpan, audit_logs row `medical_record.created` narasi mengandung "Mengisi rekam medis pasien {patient}" (FR-040/094, R5) — in apps/api/tests/Feature/MedicalRecord/CreateMedicalRecordTest.php
- [ ] T018 [P] [US1] Feature test: isi rekam medis dari booking non-done (confirmed/pending) → 422 `medical_record.booking_not_done` (FR-033/040) — in apps/api/tests/Feature/MedicalRecord/CreateBookingNotDoneTest.php
- [ ] T019 [P] [US1] Feature test: isi rekam medis kedua untuk booking sama → 422 `medical_record.already_exists` (R10, FR-088) — in apps/api/tests/Feature/MedicalRecord/CreateDuplicateRecordTest.php
- [ ] T020 [P] [US1] Feature test: role kasir/member akses POST medical-records → 403 (FR-044, Policy) — in apps/api/tests/Feature/MedicalRecord/CreatePermissionTest.php
- [ ] T021 [P] [US1] Feature test: tenant isolation — rekam medis tenant A tidak terlihat tenant B, create booking tenant lain → 422/404 (konstitusi III) — in apps/api/tests/Feature/MedicalRecord/CreateTenantIsolationTest.php
- [ ] T022 [P] [US1] Feature test: SOAP parsial (hanya subjective) → 201 (nullable, draf boleh) — in apps/api/tests/Feature/MedicalRecord/CreateSoapPartialTest.php
- [ ] T023 [P] [US1] Unit test: CreateMedicalRecordAction menghasilkan LogAuditAction row naratif "Mengisi rekam medis pasien {patient}" properties full SOAP (FR-094, R5) — in apps/api/tests/Unit/MedicalRecord/CreateActionAuditTest.php

### Implementation for User Story 1

- [ ] T024 [US1] (Verifikasi) Konfirmasi T007 (narasi audit), T011 (request), T012 (resource), T014 (controller store), T015 (route store) benar — jalankan T017–T023 hingga Green. Tidak ada file baru bila foundational benar.
- [ ] T025 [P] [US1] FE: create medical-records/new.tsx in apps/web/src/routes/$tenant/clinic/medical-records/new.tsx — form isi rekam medis dari booking done (booking_id dari route search param `?booking=`); 4 `FormTextarea` (Subjektif/Objektif/Assessment/Plan) inline + `FormSubmit`; validasi `useForm`+zod (4 field nullable string, draf boleh kosong); `ClinicBreadcrumb` "Beranda Klinik > Rekam Medis > Isi"; submit → POST, redirect ke detail. Component <=300 baris. Reuse `components/forms/` (FormTextarea/FormSubmit/useForm/applyServerErrors) + `components/clinic-breadcrumb.tsx`.
- [ ] T026 [US1] FE: create medical-records/$recordId.tsx in apps/web/src/routes/$tenant/clinic/medical-records/$recordId.tsx — detail rekam medis (SOAP read + treatments + photos list) + tombol edit; `ClinicBreadcrumb` "Beranda Klinik > Rekam Medis > {Ringkasan}"; GET detail via useQuery. Component <=300 baris, extract bila perlu.

**Checkpoint**: US1 fully functional & testable independently. Dokter isi rekam medis SOAP dari booking done, 1 per booking, role guard, audit naratif, FE form SOAP + breadcrumb.

---

## Phase 4: User Story 2 - Riwayat Rekam Medis per Pasien (Priority: P2)

**Goal**: Riwayat rekam medis per pasien kronologis via `patient_id` denormalized tanpa join ke bookings, index `(tenant_id, patient_id, created_at)`; scoped tenant; list rekam medis aktif + search/sort/pagination.

**Independent Test**: Buat 3 rekam medis untuk satu pasien lewat 3 booking done → daftar riwayat pasien menampilkan ketiganya kronologis tanpa join; pasien lain tidak muncul; tenant lain tidak muncul.

### Tests for User Story 2 (TDD — write FIRST, must FAIL)

- [ ] T027 [P] [US2] Feature test: riwayat rekam medis per pasien — 3 rekam medis pasien A → GET patients/{A}/medical-records urut kronologis created_at (FR-022, R3) — in apps/api/tests/Feature/MedicalRecord/PatientHistoryTest.php
- [ ] T028 [P] [US2] Feature test: riwayat scoped patient — pasien A dan B → GET patients/{A} hanya record A, tidak ada B — in apps/api/tests/Feature/MedicalRecord/PatientHistoryScopedTest.php
- [ ] T029 [P] [US2] Feature test: riwayat scoped tenant — tenant B GET patients/{idA}/medical-records → 0/404 (TenantScope, konstitusi III) — in apps/api/tests/Feature/MedicalRecord/PatientHistoryTenantIsolationTest.php
- [ ] T030 [P] [US2] Feature test: list index rekam medis aktif — search patient_name, sort, pagination server-side; soft-deleted tidak muncul (FR-096, R1) — in apps/api/tests/Feature/MedicalRecord/IndexMedicalRecordTest.php
- [ ] T031 [P] [US2] Feature test: show rekam medis — GET medical-records/{id} → detail SOAP + treatments + photos; soft-deleted → 404 — in apps/api/tests/Feature/MedicalRecord/ShowMedicalRecordTest.php

### Implementation for User Story 2

- [ ] T032 [US2] (Verifikasi) Konfirmasi T001 (index patient), T012 (resource patient_name), T014 (controller index/show/patientRecords), T015 (route index/show/patientRecords) benar — jalankan T027–T031 hingga Green.
- [ ] T033 [P] [US2] FE: create medical-records/index.tsx in apps/web/src/routes/$tenant/clinic/medical-records/index.tsx — list rekam medis aktif via `DataTable` (search patient_name, pagination server-side, kolom tanggal/pasien/dokter/ringkasan SOAP); `ClinicBreadcrumb` "Beranda Klinik > Rekam Medis" (item terakhir non-link); GET index via useQuery. Component <=300 baris. Reuse `components/datatable/` (DataTable/Toolbar/Pagination/ColumnHeader) + `components/forms/` bila perlu filter.
- [ ] T034 [US2] FE: create patients/$patientId/medical-records.tsx in apps/web/src/routes/$tenant/clinic/patients/$patientId/medical-records.tsx — riwayat rekam medis per pasien kronologis via `DataTable`; `ClinicBreadcrumb` "Beranda Klinik > Pasien > {Pasien} > Rekam Medis" (item terakhir non-link); GET patients/{patient}/medical-records via useQuery. Component <=300 baris. Reuse `components/datatable/` + `components/clinic-breadcrumb.tsx`.

**Checkpoint**: US2 fully functional. Riwayat rekam medis per pasien kronologis scoped tenant+patient, list aktif + search/sort/pagination, FE DataTable + breadcrumb.

---

## Phase 5: User Story 3 - Soft-Delete Rekam Medis & Integritas Child (Priority: P3)

**Goal**: Soft-delete rekam medis (deleted_at) → treatment/photo tetap utuh; hard-delete diblokir restrict bila child ada; hapus booking/pasien/dokter direferensi diblokir restrict; update SOAP + audit diff.

**Independent Test**: Soft-delete rekam medis punya treatment → tidak muncul di list aktif, tetap ada di DB deleted_at terisi, treatment tetap utuh; hard-delete parent bila foto ada → diblokir restrict; hapus booking direferensi → diblokir restrict.

### Tests for User Story 3 (TDD — write FIRST, must FAIL)

- [ ] T035 [P] [US3] Feature test: soft-delete rekam medis → DELETE 200, deleted_at terisi, tidak muncul di GET index, GET show → 404 (FR-090, R1) — in apps/api/tests/Feature/MedicalRecord/SoftDeleteMedicalRecordTest.php
- [ ] T036 [P] [US3] Feature test: soft-delete parent → treatment record + photo tetap utuh di DB (FR-091, R2) — in apps/api/tests/Feature/MedicalRecord/SoftDeleteChildIntactTest.php
- [ ] T037 [P] [US3] Feature test: update SOAP → PATCH 200, updated_at berubah, field baru tersimpan (FR-094) — in apps/api/tests/Feature/MedicalRecord/UpdateMedicalRecordTest.php
- [ ] T038 [P] [US3] Feature test: update audit diff — PATCH → audit_logs row `medical_record.updated` properties old/new SOAP (FR-094, R5) — in apps/api/tests/Feature/MedicalRecord/UpdateAuditDiffTest.php
- [ ] T039 [P] [US3] Feature test: hard-delete rekam medis dengan treatment/foto → QueryException restrict (FR-092, R2) — in apps/api/tests/Feature/MedicalRecord/HardDeleteRestrictTest.php (pgsql only, `phpunit.pgsql.xml`)
- [ ] T040 [P] [US3] Feature test: hapus booking direferensi rekam medis → QueryException restrict (FR-093, R2) — in apps/api/tests/Feature/MedicalRecord/DeleteBookingRestrictTest.php (pgsql only)
- [ ] T041 [P] [US3] Feature test: hapus pasien direferensi rekam medis → QueryException restrict (FR-093, R2) — in apps/api/tests/Feature/MedicalRecord/DeletePatientRestrictTest.php (pgsql only)
- [ ] T042 [P] [US3] Unit test: UpdateMedicalRecordAction menghasilkan LogAuditAction row naratif `medical_record.updated` old/new diff (FR-094, R5) — in apps/api/tests/Unit/MedicalRecord/UpdateActionAuditTest.php
- [ ] T043 [P] [US3] Unit test: SoftDeleteMedicalRecordAction menghasilkan LogAuditAction row naratif `medical_record.deleted` (FR-090, R5) — in apps/api/tests/Unit/MedicalRecord/SoftDeleteActionAuditTest.php
- [ ] T044 [P] [US3] Feature test: immutability patient_id — PATCH medical-records/{id} body {patient_id:lain} → patient_id tidak berubah (R4, immutable) — in apps/api/tests/Feature/MedicalRecord/UpdatePatientImmutableTest.php

### Implementation for User Story 3

- [ ] T045 [US3] (Verifikasi) Konfirmasi T001/T002 (FK restrict parent+child), T006 (SoftDeletes), T008 (UpdateAction), T009 (SoftDeleteAction), T010 (Service update/softDelete), T013 (Policy delete), T014 (controller update/destroy) benar — jalankan T035–T044 hingga Green.
- [ ] T046 [US3] FE: edit medical-records/$recordId.tsx in apps/web/src/routes/$tenant/clinic/medical-records/$recordId.tsx — tambah mode edit (form 4 `FormTextarea` prefill SOAP + `FormSubmit`, PATCH via useForm); tombol hapus (soft-delete) dengan confirm dialog; `ClinicBreadcrumb` update. Component <=300 baris, extract form ke partials bila perlu.

**Checkpoint**: US3 fully functional. Soft-delete + child tetap utuh, hard-delete + hapus parent diblokir restrict, update SOAP + audit diff, FE edit + soft-delete.

---

## Phase 6: User Story 4 - Activity Log & Breadcrumb Form Rekam Medis (Priority: P4)

**Goal**: Activity log naratif tiap aksi ubah-data (create/update/soft-delete); breadcrumb jalur induk→aktif di form + riwayat + list; FE polish.

**Independent Test**: Isi rekam medis baru → audit log "Mengisi rekam medis pasien {patient}"; buka form → breadcrumb jalur induk; update → audit diff; soft-delete → audit "Menghapus...".

### Tests for User Story 4 (TDD — write FIRST, must FAIL)

- [ ] T047 [P] [US4] Feature test: audit create narasi "Mengisi rekam medis pasien {patient}" dengan causer + subject + properties full SOAP (FR-094, R5, SC-010) — in apps/api/tests/Feature/MedicalRecord/AuditCreateNarrativeTest.php (overlap T023 — verifikasi end-to-end narasi + causer + subject)
- [ ] T048 [P] [US4] Feature test: audit update narasi "Memperbarui rekam medis pasien {patient}" + audit soft-delete narasi "Menghapus rekam medis pasien {patient}" (FR-094, R5, SC-010) — in apps/api/tests/Feature/MedicalRecord/AuditUpdateDeleteNarrativeTest.php

### Implementation for User Story 4

- [ ] T049 [US4] (Verifikasi) Konfirmasi T007/T008/T009 (audit naratif semua action) benar — jalankan T047–T048 hingga Green. Narasi create/update/soft-delete sesuai spec.
- [ ] T050 [US4] FE: verifikasi breadcrumb semua halaman rekam medis — medical-records/new.tsx (T025), medical-records/$recordId.tsx (T026/T046), medical-records/index.tsx (T033), patients/$patientId/medical-records.tsx (T034) — `ClinicBreadcrumb` item terakhir non-link, item induk link ke route benar (FR-097, SC-012). Perbaiki bila ada yang salah.

**Checkpoint**: US4 fully functional. Audit naratif create/update/soft-delete sesuai spec, breadcrumb konsisten semua halaman rekam medis.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Verifikasi lintas story, integritas tenant, immutability booking side, dokumentasi.

- [ ] T051 [P] Verifikasi immutability patient_id booking side (anomali #2, R4) — PATCH bookings/{bookingId} body {patient_id:lain} bila medicalRecord exists → 422 (sudah ada di booking, verifikasi test existing + catat invariant di docs/erd/medical_records.md). Bila test belum ada, tambah di apps/api/tests/Feature/Booking/BookingPatientImmutableTest.php (delegasi zahiira bila butuh).
- [ ] T052 [P] Verifikasi tenant isolation rekam medis lintas semua endpoint (index/show/create/update/destroy/patientRecords) — TenantScope filter otomatis, tidak ada bocor (konstitusi III). Consolidate ke satu test bila perlu.
- [ ] T053 [P] Update docs/erd/medical_records.md — catat soft-delete, FK restrict parent+child (override workflow cascade), index `(tenant_id, patient_id, created_at)`, immutability patient_id (R4) — sinkron ERD revisi.
- [ ] T054 Run quickstart.md validation — jalankan 8 skenario quickstart.md (T001–T050 implementasi selesai) via `php artisan test` (sqlite) + `php artisan test -c phpunit.pgsql.xml --filter=MedicalRecord` (constraint restrict). User jalankan sendiri.
- [ ] T055 [P] Code cleanup — verifikasi class PHP <=300 baris, method <=100 baris, FE komponen <=300 baris (konstitusi V); hapus dead code; verifikasi tidak ada teks UI hardcode (i18n, konstitusi V).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can proceed sequentially in priority order (P1 → P2 → P3 → P4)
  - US2 (riwayat) tergantung US1 (create) ada data, tapi endpoint indep — testable sendiri pakai seed
  - US3 (soft-delete) tergantung US1 (record ada untuk di-soft-delete)
  - US4 (audit+breadcrumb) cross-cutting, tergantung US1+US3 (action create/update/soft-delete)
- **Polish (Phase 7)**: Depends on all user stories complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - independently testable via seed data (endpoint riwayat indep dari create flow)
- **User Story 3 (P3)**: Can start after Foundational (Phase 2) - needs record to exist (seed via T003 factory); update/soft-delete indep
- **User Story 4 (P4)**: Can start after Foundational (Phase 2) - cross-cutting audit+breadcrumb; verifikasi US1+US3 action naratif

### Within Each User Story

- Tests (TDD) MUST be written and FAIL before implementation
- Model/Action before Service before Controller before Route
- BE (ammar) before FE (sierly) per story — FE mulai saat BE story selesai
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel (T003, T004, T005)
- All Foundational tasks marked [P] can run in parallel within Phase 2 (T016)
- All tests for a user story marked [P] can run in parallel (zahiira)
- Different user stories can be worked on in parallel by different team members (ammar BE, sierly FE, zahiira tests)
- FE tasks [P] can run in parallel across stories bila BE ready (T025, T033, T034 beda file)

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together (zahiira):
Task: "Feature test create rekam medis dari booking done in apps/api/tests/Feature/MedicalRecord/CreateMedicalRecordTest.php"
Task: "Feature test booking non-done 422 in apps/api/tests/Feature/MedicalRecord/CreateBookingNotDoneTest.php"
Task: "Feature test duplicate record 422 in apps/api/tests/Feature/MedicalRecord/CreateDuplicateRecordTest.php"
Task: "Unit test CreateActionAudit in apps/api/tests/Unit/MedicalRecord/CreateActionAuditTest.php"

# Launch BE foundational (ammar) + FE (sierly) in parallel once BE story ready:
Task: "Verifikasi foundational US1 (ammar) — T024"
Task: "FE form new.tsx (sierly) — T025"
Task: "FE detail $recordId.tsx (sierly) — T026"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (migration + factories)
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (create SOAP dari booking done + FE form)
4. **STOP and VALIDATE**: Test User Story 1 independently (`php artisan test --filter=MedicalRecord`)
5. Deploy/demo if ready

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP!)
3. Add User Story 2 → Test independently → Deploy/Demo (riwayat per pasien)
4. Add User Story 3 → Test independently → Deploy/Demo (soft-delete + integritas)
5. Add User Story 4 → Test independently → Deploy/Demo (audit + breadcrumb polish)
6. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers (ammar BE, sierly FE, zahiira tests):

1. Team completes Setup + Foundational together (ammar BE foundational, zahiira factories)
2. Once Foundational is done:
   - `ammar`: BE User Story 1 (verifikasi foundational)
   - `zahiira`: tests User Story 1 (TDD Red first, parallel)
   - `sierly`: FE User Story 1 (T025, T026 — mulai saat BE store endpoint ready)
3. Stories complete and integrate independently; FE follows BE per story

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story independently completable and testable
- Verify tests fail before implementing (TDD Red-Green-Refactor, konstitusi II)
- Commit after each task or logical group (Conventional Commits, no AI attribution)
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
- BE existing sudah ada: model, migration (cascade), service, action (narasi salah), request, policy, resource, controller (store/addTreatment/addPhoto/patientTreatments) — kerja nyata = revisi + isi tests + FE dari nol
- Immutability patient_id (anomali #2) sudah ada di booking side — verifikasi + catat, tidak implementasi ulang
- FK child restrict (R2) override workflow langkah 15/16 cascade — ponytail jujur per user AC
- FE reuse penuh `components/forms/` + `components/datatable/` + `components/clinic-breadcrumb.tsx` — 0 komponen baru
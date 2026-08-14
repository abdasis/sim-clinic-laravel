## Ringkasan

Revisi modul rekam medis SOAP klinik: soft-delete rekam medis (deleted_at), integritas child (treatment/photo tetap utuh, FK restrict parent+child), riwayat rekam medis per pasien kronologis via `patient_id` denormalized (index `tenant_id, patient_id, created_at`), update SOAP + audit diff, audit naratif ("Mengisi/Memperbarui/Menghapus rekam medis pasien {patient}"), immutability `patient_id` dari booking, endpoint index/show/update/destroy + riwayat pasien, FE form isi/detail/list/riwayat/edit + breadcrumb.

Spec lengkap: `specs/009-medical-records/` (spec.md, plan.md, research.md, data-model.md, contracts/medical-records-api.md, quickstart.md, tasks.md)

BE existing sudah ada (model, migration cascade, service, action narasi salah, request, policy, resource, controller store/addTreatment/addPhoto/patientTreatments) — kerja nyata = revisi + FE dari nol.

## Phase 1 — Setup

- [ ] T001 Create migration to add `deleted_at` (softDeletes), index `(tenant_id, deleted_at)`, index `(tenant_id, patient_id, created_at)`, and change FK `medical_records.booking_id` + `medical_records.patient_id` from cascadeOnDelete → restrictOnDelete in `apps/api/database/migrations/2026_08_14_add_medical_records_softdelete_index_restrict_fk.php` — FK alter guard `if (Schema::getConnection()->getDriverName() === 'sqlite') { return; }` (SQLite skip; softDeletes+index jalan kedua driver)
- [ ] T002 Create migration to change FK `treatment_records.medical_record_id` + `medical_photos.medical_record_id` from cascadeOnDelete → restrictOnDelete in `apps/api/database/migrations/2026_08_14_restrict_medical_record_child_foreign_keys.php` — guard `if (Schema::getConnection()->getDriverName() === 'sqlite') { return; }` (SQLite skip; restrict FK pgsql only)
- [ ] T003 [P] Create `MedicalRecordFactory` in `apps/api/database/factories/MedicalRecordFactory.php` — tenant_id, booking_id, patient_id, author_id, subjective/objective/assessment/plan nullable, deleted_at nullable
- [ ] T004 [P] Create `TreatmentRecordFactory` in `apps/api/database/factories/TreatmentRecordFactory.php` — tenant_id, medical_record_id, service_id nullable, service_name, notes nullable
- [ ] T005 [P] Create `MedicalPhotoFactory` in `apps/api/database/factories/MedicalPhotoFactory.php` — tenant_id, medical_record_id, type (before/after), path

**Checkpoint**: Migration + factories ready. Run `php artisan migrate` setelah implementasi. `php artisan test -c phpunit.pgsql.xml --filter=MedicalRecord` sebelum rilis (constraint restrict FK parent+child).

## Phase 2 — Foundational (blocking)

**⚠️ CRITICAL**: Soft delete trait, index patient, FK restrict, endpoint index/show/update/destroy + riwayat pasien, audit naratif "Mengisi rekam medis pasien {patient}" — fondasi semua story.

- [ ] T006 Edit `MedicalRecord` model in `apps/api/app/Models/MedicalRecord.php` — `use SoftDeletes`; tambah `deleted_at` ke `$fillable`; tambah cast `deleted_at` => 'datetime'; verifikasi relasi booking/patient/author/treatmentRecords/medicalPhotos tetap
- [ ] T007 Edit `CreateMedicalRecordAction` in `apps/api/app/Actions/MedicalRecord/CreateMedicalRecordAction.php` — ubah narasi audit dari "Menulis rekam medis untuk {patient}" → "Mengisi rekam medis pasien {patient}"; tetap set patient_id dari booking->patient_id; withProperties full SOAP attributes. Method <100 baris.
- [ ] T008 Create `UpdateMedicalRecordAction` in `apps/api/app/Actions/MedicalRecord/UpdateMedicalRecordAction.php` — `handle(MedicalRecord, array $data): MedicalRecord` update SOAP field (subjective/objective/assessment/plan, nullable) + LogAuditAction event `medical_record.updated` narasi "Memperbarui rekam medis pasien {patient}" properties old/new diff SOAP. Inject LogAuditAction (bukan Service). Method <100 baris.
- [ ] T009 Create `SoftDeleteMedicalRecordAction` in `apps/api/app/Actions/MedicalRecord/SoftDeleteMedicalRecordAction.php` — `handle(MedicalRecord): MedicalRecord` soft-delete (`$record->delete()`) + LogAuditAction event `medical_record.deleted` narasi "Menghapus rekam medis pasien {patient}". Inject LogAuditAction (bukan Service). Method <100 baris.
- [ ] T010 Edit `MedicalRecordService` in `apps/api/app/Services/MedicalRecordService.php` — tambah `update(MedicalRecord, array $data): MedicalRecord` via UpdateMedicalRecordAction; tambah `softDelete(MedicalRecord): MedicalRecord` via SoftDeleteMedicalRecordAction; tetap orkestrasi tanpa sentuh DB langsung (Service dilarang DB write). Method <100 baris.
- [ ] T011 Edit `MedicalRecordRequest` in `apps/api/app/Http/Requests/MedicalRecordRequest.php` — rules update: 4 field SOAP nullable|string; `booking_id` tidak di-accept saat update (immutable, dari route record); pisah rules create (booking_id required) vs update bila perlu
- [ ] T012 Edit `MedicalRecordResource` in `apps/api/app/Http/Resources/MedicalRecordResource.php` — tambah `patient_name` (`$this->patient?->name`), `deleted_at`, `updated_at`, `booking` summary (id/status/start_at); tetap treatments + photos whenLoaded
- [ ] T013 Edit `MedicalRecordPolicy` in `apps/api/app/Policies/MedicalRecordPolicy.php` — tambah `delete(User)` method return `$user->can('medical_record.manage')` (soft-delete auth, admin/dokter/terapis); verifikasi viewAny/view/create/update tetap
- [ ] T014 Edit `MedicalRecordController` in `apps/api/app/Http/Controllers/MedicalRecordController.php` — tambah `index(Request)` (DataTable, read-only inject query langsung), `show(MedicalRecord)` (authorize view, load relasi), `update(MedicalRecordRequest, MedicalRecord, MedicalRecordService)` (authorize update via Service), `destroy(MedicalRecord, MedicalRecordService)` (authorize delete via Service soft-delete), rename `patientTreatments`→`patientRecords(Patient)`. Method <100 baris.
- [ ] T015 Edit `apps/api/routes/api.php` — tambah `GET medical-records` (index), `GET medical-records/{medicalRecord}` (show), `PATCH medical-records/{medicalRecord}` (update), `DELETE medical-records/{medicalRecord}` (destroy), `GET patients/{patient}/medical-records` (patientRecords) ke clinic route group. Verifikasi route store/addTreatment/addPhoto tetap.
- [ ] T016 [P] Edit `apps/api/lang/id/medical_record.php` — tambah key `updated` = "Rekam medis berhasil diperbarui.", `deleted` = "Rekam medis berhasil dihapus.", `created` verifikasi "Rekam medis berhasil diisi."; tambah key breadcrumb/label riwayat bila perlu

**Checkpoint**: Foundation ready — soft-delete trait, index patient, FK restrict, endpoint index/show/update/destroy + riwayat pasien, audit naratif "Mengisi rekam medis pasien {patient}".

## Phase 3 — User Story 1: Dokter Mengisi Rekam Medis SOAP dari Booking Selesai (P1, MVP)

**Goal**: Dokter mengisi rekam medis SOAP (S/O/A/P) dari booking done; 1 per booking; patient_id dari booking immutable; hanya dokter/terapis/admin; audit "Mengisi rekam medis pasien {patient}".

### Implementation

- [ ] T024 [US1] Verifikasi foundational (T007 narasi audit, T011 request, T012 resource, T014 controller store, T015 route store) benar. Tidak ada file baru bila foundational benar.
- [ ] T025 [P] [US1] FE: create `apps/web/src/routes/$tenant/clinic/medical-records/new.tsx` — form isi rekam medis dari booking done (booking_id dari route search param `?booking=`); 4 `FormTextarea` (Subjektif/Objektif/Assessment/Plan) inline + `FormSubmit`; validasi `useForm`+zod (4 field nullable string, draf boleh kosong); `ClinicBreadcrumb` "Beranda Klinik > Rekam Medis > Isi"; submit → POST, redirect ke detail. Component ≤300 baris. Reuse `components/forms/` (FormTextarea/FormSubmit/useForm/applyServerErrors) + `components/clinic-breadcrumb.tsx`.
- [ ] T026 [US1] FE: create `apps/web/src/routes/$tenant/clinic/medical-records/$recordId.tsx` — detail rekam medis (SOAP read + treatments + photos list) + tombol edit; `ClinicBreadcrumb` "Beranda Klinik > Rekam Medis > {Ringkasan}"; GET detail via useQuery. Component ≤300 baris, extract bila perlu.

**Checkpoint**: US1 functional — dokter isi rekam medis SOAP dari booking done, 1 per booking, role guard, audit naratif, FE form SOAP + breadcrumb.

## Phase 4 — User Story 2: Riwayat Rekam Medis per Pasien (P2)

**Goal**: Riwayat rekam medis per pasien kronologis via `patient_id` denormalized tanpa join ke bookings, index `(tenant_id, patient_id, created_at)`; scoped tenant; list rekam medis aktif + search/sort/pagination.

### Implementation

- [ ] T032 [US2] Verifikasi foundational (T001 index patient, T012 resource patient_name, T014 controller index/show/patientRecords, T015 route index/show/patientRecords) benar.
- [ ] T033 [P] [US2] FE: create `apps/web/src/routes/$tenant/clinic/medical-records/index.tsx` — list rekam medis aktif via `DataTable` (search patient_name, pagination server-side, kolom tanggal/pasien/dokter/ringkasan SOAP); `ClinicBreadcrumb` "Beranda Klinik > Rekam Medis" (item terakhir non-link); GET index via useQuery. Component ≤300 baris. Reuse `components/datatable/` (DataTable/Toolbar/Pagination/ColumnHeader) + `components/forms/` bila perlu filter.
- [ ] T034 [US2] FE: create `apps/web/src/routes/$tenant/clinic/patients/$patientId/medical-records.tsx` — riwayat rekam medis per pasien kronologis via `DataTable`; `ClinicBreadcrumb` "Beranda Klinik > Pasien > {Pasien} > Rekam Medis" (item terakhir non-link); GET patients/{patient}/medical-records via useQuery. Component ≤300 baris. Reuse `components/datatable/` + `components/clinic-breadcrumb.tsx`.

**Checkpoint**: US2 functional — riwayat rekam medis per pasien kronologis scoped tenant+patient, list aktif + search/sort/pagination, FE DataTable + breadcrumb.

## Phase 5 — User Story 3: Soft-Delete Rekam Medis & Integritas Child (P3)

**Goal**: Soft-delete rekam medis (deleted_at) → treatment/photo tetap utuh; hard-delete diblokir restrict bila child ada; hapus booking/pasien/dokter direferensi diblokir restrict; update SOAP + audit diff.

### Implementation

- [ ] T045 [US3] Verifikasi foundational (T001/T002 FK restrict parent+child, T006 SoftDeletes, T008 UpdateAction, T009 SoftDeleteAction, T010 Service update/softDelete, T013 Policy delete, T014 controller update/destroy) benar.
- [ ] T046 [US3] FE: edit `apps/web/src/routes/$tenant/clinic/medical-records/$recordId.tsx` — tambah mode edit (form 4 `FormTextArea` prefill SOAP + `FormSubmit`, PATCH via useForm); tombol hapus (soft-delete) dengan confirm dialog; `ClinicBreadcrumb` update. Component ≤300 baris, extract form ke partials bila perlu.

**Checkpoint**: US3 functional — soft-delete + child tetap utuh, hard-delete + hapus parent diblokir restrict, update SOAP + audit diff, FE edit + soft-delete.

## Phase 6 — User Story 4: Activity Log & Breadcrumb Form Rekam Medis (P4)

**Goal**: Activity log naratif tiap aksi ubah-data (create/update/soft-delete); breadcrumb jalur induk→aktif di form + riwayat + list; FE polish.

### Implementation

- [ ] T049 [US4] Verifikasi foundational (T007/T008/T009 audit naratif semua action) benar. Narasi create/update/soft-delete sesuai spec.
- [ ] T050 [US4] FE: verifikasi breadcrumb semua halaman rekam medis — `medical-records/new.tsx` (T025), `medical-records/$recordId.tsx` (T026/T046), `medical-records/index.tsx` (T033), `patients/$patientId/medical-records.tsx` (T034) — `ClinicBreadcrumb` item terakhir non-link, item induk link ke route benar. Perbaiki bila ada yang salah.

**Checkpoint**: US4 functional — audit naratif create/update/soft-delete sesuai spec, breadcrumb konsisten semua halaman rekam medis.

## Phase 7 — Polish

- [ ] T051 [P] Verifikasi immutability patient_id booking side (R4) — PATCH bookings/{bookingId} body {patient_id:lain} bila medicalRecord exists → 422 (verifikasi + catat invariant di `docs/erd/medical_records.md`). Bila test belum ada, tambah di `apps/api/tests/Feature/Booking/BookingPatientImmutableTest.php`.
- [ ] T052 [P] Verifikasi tenant isolation rekam medis lintas semua endpoint (index/show/create/update/destroy/patientRecords) — TenantScope filter otomatis, tidak ada bocor (konstitusi III).
- [ ] T053 [P] Update `docs/erd/medical_records.md` — catat soft-delete, FK restrict parent+child (override workflow cascade), index `(tenant_id, patient_id, created_at)`, immutability patient_id (R4) — sinkron ERD revisi.
- [ ] T054 Run `quickstart.md` validation — jalankan 8 skenario quickstart.md via `php artisan test` (sqlite) + `php artisan test -c phpunit.pgsql.xml --filter=MedicalRecord` (constraint restrict). Jalankan sendiri.
- [ ] T055 [P] Code cleanup — verifikasi class PHP ≤300 baris, method ≤100 baris, FE komponen ≤300 baris (konstitusi V); hapus dead code; verifikasi tidak ada teks UI hardcode (i18n, konstitusi V).

## Dependencies

- **Phase 2** blocks semua user story (soft-delete + FK restrict + endpoint + audit fondasi)
- **US1**: after Phase 2 — create SOAP dari booking done
- **US2**: after Phase 2 — riwayat per pasien (endpoint indep, testable via seed)
- **US3**: after Phase 2 — soft-delete + integritas (butuh record ada via factory)
- **US4**: after US1 + US3 — audit + breadcrumb cross-cutting
- **Polish**: after all stories

## MVP scope

Phase 1 + Phase 2 + US1 saja = dokter isi rekam medis SOAP dari booking done.

## Catatan

- BE existing sudah ada — kerja nyata = revisi (narasi audit, soft-delete, FK restrict, endpoint baru) + FE dari nol
- Immutability `patient_id` (R4) sudah ada di booking side — verifikasi + catat, tidak implementasi ulang
- FK child restrict (R2) override workflow langkah 15/16 cascade — ponytail jujur per user AC
- FE reuse penuh `components/forms/` + `components/datatable/` + `components/clinic-breadcrumb.tsx` — 0 komponen baru
- PHP class ≤300 baris, method ≤100; React component ≤300 — extract partials
- Audit log naratif + properties old/new tiap Action (konstitusi VI)
- TenantScope isolation WAJIB terjaga (konstitusi III)
- UI text via i18n (`lang/id/medical_record.php`), identifier English
- Breadcrumb WAJIB tiap halaman dalam
- Command build/format/dev JANGAN auto-run — jalankan sendiri (T054)
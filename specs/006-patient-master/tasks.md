# Tasks: Master Pasien Klinik

**Input**: Design documents from `/specs/006-patient-master/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/patients-api.md, quickstart.md

**Tests**: Termasuk — fitur pasien adalah revisi entitas inti (fondasi booking/transaksi/rekam medis) dan konstitusi II (TDD) NON-NEGOTIABLE. Test ditulis lebih dulu oleh agent `zahiira` (R8), Red-Green-Refactor.

**Organization**: Tasks grouped by user story (spec.md: US1 P1, US2 P2, US3 P2, US4 P3) untuk implementasi & testing independen.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g. US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `apps/api/` (Laravel) — `app/`, `database/`, `lang/`, `routes/`
- **Frontend**: `apps/web/src/` (TanStack Start) — `routes/`, `components/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Struktur dasar revisi modul pasien. Repo sudah ada; setup minimal = factory test + folder Action per entity.

- [ ] T001 [P] Buat folder per entity `apps/api/app/Actions/Patient/` (namespace `App\Actions\Patient`) sesuai CLAUDE.md
- [ ] T002 [P] Buat `apps/api/database/factories/PatientFactory.php` dengan state default + state `trashed` (soft-deleted) untuk kebutuhan test (R8). Pakai `BelongsToTenant` — attach tenant via relasi.

**Checkpoint**: Folder Action + factory test siap.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Perubahan skema & model yang MUTLAK sebelum semua user story (soft delete + FK restrict = fondasi US1–US4).

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T003 Edit migration `apps/api/database/migrations/2026_07_06_120000_create_patients_table.php`: tambah `$table->softDeletes();` + `$table->index(['tenant_id', 'deleted_at']);` (R1)
- [ ] T004 Edit model `apps/api/app/Models/Patient.php`: `use SoftDeletes;` + tambah `deleted_at` ke `$fillable` + cast `deleted_at` => 'datetime' (R1)
- [ ] T005 [P] Buat migration baru `apps/api/database/migrations/2026_08_14_000000_restrict_patient_foreign_keys.php`: drop + recreate FK `bookings.patient_id`, `medical_records.patient_id`, `transactions.patient_id` → `restrictOnDelete` (R2)
- [ ] T006 [P] Edit resource `apps/api/app/Http/Resources/PatientResource.php`: tambah field `deleted_at` (ISO-8601, null = aktif) (R1)
- [ ] T007 [P] Edit policy `apps/api/app/Policies/PatientPolicy.php`: tambah method `delete(User $user): bool` delegasi `$user->can('clinic.access', ['patient', 'w'])` untuk route `destroy` (R3)
- [ ] T008 Edit route `apps/api/routes/api.php`: hapus `->except(['destroy'])` pada `apiResource('patients')` agar route `destroy` (nonaktifkan) tersedia (R3)

**Checkpoint**: Foundation ready — soft delete + FK restrict + resource + policy + route destroy. User story implementation dapat dimulai.

---

## Phase 3: User Story 1 — Kelola Data Pasien (Priority: P1) 🎯 MVP

**Goal**: Admin/dokter/kasir membuat, melihat, memperbarui data pasien (7 field termasuk `notes`); deteksi duplikat phone = peringatan bukan block (FR-020/021/023/024/029/030/031).

**Independent Test**: Buat satu pasien baru → muncul di daftar → perbarui telepon/alamat → buat pasien kedua dengan telepon sama → peringatan duplikat muncul, data tetap tersimpan. Semua tanpa modul booking/transaksi/rekam medis.

### Tests for User Story 1 (TDD — tulis lebih dulu, konfirmasi RED) ⚠️

> Delegasi penulisan test ke agent `zahiira` (R8). Tulis lebih dulu, konfirmasi gagal sebelum implementasi.

- [ ] T009 [P] [US1] Feature test CRUD pasien `apps/api/tests/Feature/Patient/PatientCrudTest.php`: admin store/show/update; 201/200 + `deleted_at:null`; validasi name/phone kosong + birth_date masa depan + gender invalid → 422 (FR-020)
- [ ] T010 [P] [US1] Feature test duplicate phone `apps/api/tests/Feature/Patient/PatientDuplicatePhoneTest.php`: store + update dengan phone ganda di tenant sama → 201/200 + `meta.duplicate_warning=true` + `duplicate_patient_id`; tidak memblokir (FR-021/023). Cross-tenant phone sama tidak memicu peringatan (FR-030)
- [ ] T011 [P] [US1] Feature test tenant isolation `apps/api/tests/Feature/Patient/PatientTenantIsolationTest.php`: pasien tenant A tidak terlihat tenant B (index/show/update 404) (FR-030)
- [ ] T012 [P] [US1] Feature test permission `apps/api/tests/Feature/Patient/PatientPermissionTest.php`: therapist view only (GET 200, POST/PUT/DELETE 403); cashier rw (POST 201); doctor rw (POST 201) per matriks `ClinicPermission::MATRIX`
- [ ] T013 [P] [US1] Unit test `CreatePatientAction` `apps/api/tests/Unit/Actions/Patient/CreatePatientActionTest.php`: handle() menyimpan pasien + audit log row `patient.created` narasi mengandung nama + `properties` berisi `tenant_id` + **full attributes** (konstitusi VI)
- [ ] T014 [P] [US1] Unit test `UpdatePatientAction` `apps/api/tests/Unit/Actions/Patient/UpdatePatientActionTest.php`: handle() memperbarui + audit log `patient.updated` + `properties` berisi **`old` + `new`** diff (konstitusi VI)
- [ ] T014a [P] [US1] Unit test `PatientService` `apps/api/tests/Unit/Services/PatientServiceTest.php`: `create`/`update` memanggil Action + deteksi duplikat phone (return duplicate patient id); Service tidak menyentuh DB langsung (mock/observe Action dipanggil); `deactivate` memanggil `DeactivatePatientAction`
- [ ] T015 [P] [US1] Feature test activity log `apps/api/tests/Feature/Patient/PatientAuditLogTest.php`: store/update menghasilkan row `audit_logs` + narasi "Membuat/Memperbarui pasien {name}" + `withProperties` sesuai (FR-029, konstitusi VI)

### Implementation for User Story 1

- [ ] T016 [P] [US1] Buat Action `apps/api/app/Actions/Patient/CreatePatientAction.php`: `handle(array $attributes): Patient` → `Patient::create($attributes)` + `LogAuditAction('patient.created', $patient, auth()->user(), ['tenant_id'=>…, 'attributes'=>$patient->fresh()->getAttributes()], 'Membuat pasien {name}')` (R4, konstitusi VI withProperties full). Tidak inject Service.
- [ ] T017 [P] [US1] Buat Action `apps/api/app/Actions/Patient/UpdatePatientAction.php`: `handle(Patient $patient, array $attributes): Patient` → capture `$old` sebelum update → `$patient->update($attributes)` + `LogAuditAction('patient.updated', $patient->fresh(), auth()->user(), ['tenant_id'=>…, 'old'=>$old, 'new'=>$patient->fresh()->only(array_keys($attributes))], 'Memperbarui pasien {name}')` (R4, konstitusi VI old/new). Tidak inject Service.
- [ ] T017a [P] [US1] Buat Service `apps/api/app/Services/PatientService.php`: orkestrasi (no DB write). `create(array $attributes): array` → panggil `CreatePatientAction` + deteksi duplikat phone (`Patient::where('phone',…)->where('id','!=',…)->first()`) → return `[$patient, $duplicate]`. `update(Patient $patient, array $attributes): array` → panggil `UpdatePatientAction` + deteksi duplikat → return `[$patient, $duplicate]`. `deactivate(Patient $patient): Patient` → panggil `DeactivatePatientAction`. (R4, CLAUDE.md Controller→Service→Action, Service no DB)
- [ ] T018 [US1] Edit controller `apps/api/app/Http/Controllers/PatientController.php` `store`: `authorize` → resolve `PatientRequest` → `app(PatientService::class)->create($request->validated())` → response 201 dengan `meta.duplicate_warning`/`duplicate_patient_id` dari Service. Controller tidak sentuh DB & tidak langsung ke Action (R4, CLAUDE.md). Validasi via FormRequest (no inline validation)
- [ ] T019 [US1] Edit controller `apps/api/app/Http/Controllers/PatientController.php` `update`: `authorize` → `app(PatientService::class)->update($patient, $request->validated())` → response 200 + meta duplikat. Controller tidak sentuh DB/langsung ke Action (R4). Memenuhi FR-021 (duplikat di update juga)
- [ ] T020 [P] [US1] Edit form `apps/web/src/routes/$tenant/clinic/patients/components/patient-form.tsx`: tambah `notes` ke `patientSchema` (`z.string().optional()`) + `patientDefaults` (`notes: ""`) + render `<FormTextarea control={control} name="notes" label={t("patient.notes")} />` (reuse `FormTextarea`, R7). 7 field → halaman terpisah (bukan modal)
- [ ] T021 [P] [US1] Edit halaman `apps/web/src/routes/$tenant/clinic/patients/new.tsx`: sinkronkan key reference `duplicate_warning` (R6); AlertDialog peringatan duplikat tetap ada
- [ ] T022 [P] [US1] Edit halaman `apps/web/src/routes/$tenant/clinic/patients/$id/edit.tsx`: tambah duplicate warning handling di mutation `onSuccess` (cek `meta.duplicate_warning` → AlertDialog), paritas dengan `new.tsx` (R7)

**Checkpoint**: User Story 1 fully functional & testable independently — CRUD + duplikat + audit log + tenant isolation.

---

## Phase 4: User Story 2 — Nonaktifkan Pasien & Riwayat Tetap Utuh (Priority: P2)

**Goal**: Admin nonaktifkan pasien via soft delete; pasien nonaktif tidak muncul di list aktif; riwayat tetap utuh; hard-delete direferensi diblokir restrict (FR-025/026/027/028/029).

**Independent Test**: Nonaktifkan satu pasien aktif yang punya booking → tidak muncul di daftar aktif → halaman riwayatnya tetap lengkap. Coba hard-delete pasien dengan referensi → diblokir DB.

### Tests for User Story 2 (TDD) ⚠️

- [ ] T023 [P] [US2] Feature test deactivate `apps/api/tests/Feature/Patient/PatientDeactivateTest.php`: `DELETE patients/{id}` → 200 + `deleted_at` terisi + meta "Pasien berhasil dinonaktifkan."; pasien tidak muncul di `index` (FR-025/026); audit log `patient.deactivated` narasi "Menonaktifkan pasien {name}" + `properties` `old`/`new` (FR-029, konstitusi VI)
- [ ] T024 [P] [US2] Integration test FK restrict `apps/api/tests/Feature/Patient/PatientForceDeleteRestrictTest.php`: `Patient::find($id)->forceDelete()` dengan booking/transaksi/rekam medis → `QueryException`; tanpa referensi → sukses. Soft delete (`delete()`) tidak memicu restrict (FR-027)
- [ ] T025 [P] [US2] Integration test riwayat utuh `apps/api/tests/Feature/Patient/PatientHistoryIntactTest.php`: nonaktifkan pasien dgn booking/rekam medis/transaksi → relasi tetap merujuk pasien, data utuh (FR-028)
- [ ] T026 [P] [US2] Unit test `DeactivatePatientAction` `apps/api/tests/Unit/Actions/Patient/DeactivatePatientActionTest.php`: handle() set `deleted_at` + audit log `patient.deactivated` + `properties` `old`(`deleted_at:null`)/`new`(`deleted_at:{ts}`) (konstitusi VI)

### Implementation for User Story 2

- [ ] T027 [P] [US2] Buat Action `apps/api/app/Actions/Patient/DeactivatePatientAction.php`: `handle(Patient $patient): Patient` → `$patient->delete()` (soft delete) + `LogAuditAction('patient.deactivated', $patient, auth()->user(), ['tenant_id'=>…, 'old'=>['deleted_at'=>null], 'new'=>['deleted_at'=>$patient->deleted_at]], 'Menonaktifkan pasien {name}')` (R3, konstitusi VI old/new). Single-write tanpa `DB::transaction` (mirror `ArchiveServiceAction`). Bila menangkap exception → `Log::error` sebelum re-throw. Tidak inject Service.
- [ ] T027a [US2] Tambah method `deactivate(Patient $patient): Patient` ke `apps/api/app/Services/PatientService.php` → panggil `DeactivatePatientAction` (R3/R4, Service no DB). Bisa digabung dengan T017a (same file — sequential)
- [ ] T028 [US2] Edit controller `apps/api/app/Http/Controllers/PatientController.php` `destroy`: `authorize('delete', $patient)` → `app(PatientService::class)->deactivate($patient)` → response 200 dengan `meta.message` `__('patient.deactivated')`. Controller→Service→Action, tidak sentuh DB/langsung ke Action (R3/R4)
- [ ] T029 [P] [US2] Edit lang `apps/api/lang/id/patient.php`: tambah key `deactivate` ("Nonaktifkan"), `deactivate_confirm` ("Nonaktifkan pasien ini? Pasien nonaktif tidak muncul di daftar aktif, tetapi riwayat kunjungan & rekam medis tetap utuh."), `deactivated` ("Pasien berhasil dinonaktifkan.") (R6)
- [ ] T030 [P] [US2] Buat komponen `apps/web/src/routes/$tenant/clinic/patients/components/patient-actions-cell.tsx` (mirror `apps/web/src/routes/$tenant/clinic/staff/components/staff-actions-cell.tsx`): DropdownMenu "Ubah" (navigate ke `/$tenant/clinic/patients/$id/edit`) + "Nonaktifkan" (AlertDialog confirm → `apiDelete` `/{tenant}/clinic/patients/{id}`); tooltip + state lengkap per CLAUDE.md authoring discipline (R7)
- [ ] T031 [US2] Edit halaman `apps/web/src/routes/$tenant/clinic/patients/index.tsx`: tambah kolom `actions` → render `PatientActionsCell`; invalidate query `["patients", tenant]` setelah nonaktifkan (R7)

**Checkpoint**: User Story 2 fully functional — nonaktifkan + riwayat utuh + restrict + FE row action. Independently testable.

---

## Phase 5: User Story 3 — Riwayat Kunjungan Pasien (Priority: P2)

**Goal**: Admin/dokter lihat riwayat kunjungan pasien (booking + treatment) terurut kronologis; tetap dapat diakses walau pasien dinonaktifkan (FR-022).

**Independent Test**: Buat pasien dgn minimal satu booking → buka halaman riwayat → booking tampil terurut. Nonaktifkan pasien → riwayat tetap dapat dibuka.

### Tests for User Story 3 (TDD) ⚠️

- [ ] T032 [P] [US3] Feature test history `apps/api/tests/Feature/Patient/PatientHistoryTest.php`: `GET patients/{id}/history` → 200, agregasi booking + treatment terurut kronologis, masing-masing ada date/service_name/status/assignee_name/type (FR-022)
- [ ] T033 [P] [US3] Feature test history nonaktif `apps/api/tests/Feature/Patient/PatientHistoryTrashedTest.php`: pasien nonaktif → `GET patients/{id}/history` tetap 200 lengkap (R5 withTrashed, FR-022); `GET patients/{id}` (show) tetap 200 untuk pasien nonaktif (R5)

### Implementation for User Story 3

- [ ] T034 [US3] Edit controller `apps/api/app/Http/Controllers/PatientController.php` `history` & `show`: **read exception** — query read langsung di Controller (tanpa Service), resolve `withTrashed` — gunakan route param `id` + `Patient::withTrashed()->findOrFail($id)` (TenantScope tetap aktif) agar pasien nonaktif tetap dapat diakses (R5). Hapus implicit binding `Patient $patient` untuk kedua method ini. `index` tetap read langsung (DataTable)
- [ ] T035 [P] [US3] Edit lang `apps/api/lang/id/patient.php`: tambah key `history_empty` ("Belum ada riwayat kunjungan.") (R6)
- [ ] T036 [US3] Edit halaman `apps/web/src/routes/$tenant/clinic/patients/$id/history.tsx`: fix breadcrumb (pattern `clinic` link → `patient.title` link → nama pasien link ke detail → `history` last item); tampilkan nama pasien di breadcrumb via `useQuery` `show` terpisah (agar punya nama walau history kosong) (R7, US4 overlap — koordinasi dgn T039)

**Checkpoint**: User Story 3 fully functional — riwayat lengkap + dapat diakses walau nonaktif.

---

## Phase 6: User Story 4 — Breadcrumb Navigasi Master Pasien (Priority: P3)

**Goal**: Breadcrumb halaman master pasien & riwayat menunjukkan jalur induk→halaman aktif (FR-032).

**Independent Test**: Buka daftar pasien → breadcrumb "Beranda Klinik > Pasien" (Pasien = last, Beranda = link). Buka riwayat pasien → "Beranda Klinik > Pasien > {nama} > Riwayat".

### Implementation for User Story 4

- [ ] T037 [US4] Edit halaman `apps/web/src/routes/$tenant/clinic/patients/index.tsx`: fix breadcrumb — item pertama `{ label: tenant, to: "/$tenant/clinic", params: { tenant } }` (pattern ProductsPage, bukan self-link `/$tenant/clinic/patients`), item kedua `{ label: t("clinic.clinic") }` (no link), item ketiga `{ label: t("patient.title") }` (last, no link) (R7)
- [ ] T038 [US4] Edit halaman `apps/web/src/routes/$tenant/clinic/patients/$id/history.tsx`: fix breadcrumb pattern `clinic` link → `patient.title` link ke `/$tenant/clinic/patients` → nama pasien link ke detail `/$tenant/clinic/patients/$id` → `history` last (R7). Bisa digabung dengan T036 (same file)
- [ ] T039 [P] [US4] Edit halaman `apps/web/src/routes/$tenant/clinic/patients/$id/edit.tsx`: tambah breadcrumb `clinic` → `patient.title` → nama pasien → `edit` (last) bila belum ada (konsistensi konvensi breadcrumb CLAUDE.md)

**Checkpoint**: User Story 4 fully functional — breadcrumb benar di semua halaman pasien.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Perbaikan lintas user story.

- [ ] T040 [P] Jalankan `cd apps/api && php artisan test` — seluruh test patient lulus (konstitusi II)
- [ ] T041 [P] Jalankan `cd apps/api && vendor/bin/pint` — format BE sesuai konvensi
- [ ] T042 [P] Jalankan `cd apps/web && npx tsc --noEmit --incremental` — typecheck FE
- [ ] T043 [P] Jalankan `cd apps/web && bun run generate-routes` — regen route tree bila ada file route baru (tidak ada route file baru di fitur ini, tapi pastikan sinkron)
- [ ] T044 Jalankan validasi `specs/006-patient-master/quickstart.md` — 7 skenario end-to-end
- [ ] T045 [P] Verifikasi `PatientController` method tetap <100 baris & class <300 baris (konstitusi V); extract bila melampaui
- [ ] T046 [P] Verifikasi 3 Action masing-masing <50 baris & single responsibility (konstitusi I/V); tidak inject Service (CLAUDE.md). `PatientService` <80 baris, no DB write, orkestrasi only
- [ ] T046a [P] Verifikasi semua validasi field pasien ada di `apps/api/app/Http/Requests/PatientRequest.php` — tidak ada inline validation di `PatientController` (CLAUDE.md)
- [ ] T046b [P] Verifikasi layering Controller→Service→Action: Controller tidak inject/call Action langsung; Service tidak `Patient::create`/`->update`/`->delete`/`DB::`; read (index/show/history) di Controller (read exception)
- [ ] T047 Hapus duplikasi key lang bila ada (mis. `duplicate_body` vs `duplicate_warning`) — pilih satu nama `duplicate_warning` (R6)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — T001, T002 parallel
- **Foundational (Phase 2)**: T003→T004 (migration→model); T005–T008 mostly parallel; BLOCKS all user stories
- **User Stories (Phase 3–6)**: All depend on Phase 2 completion
  - US1 (Phase 3) MVP — no dependency on other stories
  - US2 (Phase 4) — soft delete foundation dari Phase 2; FE row action independen
  - US3 (Phase 5) — butuh `withTrashed` (R5); overlap FE file `history.tsx` dgn US4 (T036/T038 same file — jangan parallel)
  - US4 (Phase 6) — breadcrumb fix; `history.tsx` overlap dgn US3
- **Polish (Phase 7)**: Depends on all user stories complete

### User Story Dependencies

- **US1 (P1)**: Start after Foundational — foundation for patient data; no story dependency
- **US2 (P2)**: Start after Foundational — nonaktifkan butuh soft delete (Phase 2). Independently testable
- **US3 (P2)**: Start after Foundational — riwayat butuh `withTrashed` (R5, US3 impl). FE `history.tsx` shared dgn US4
- **US4 (P3)**: Start after Foundational — breadcrumb fix. `history.tsx` shared dgn US3 (T036+T038 jangan parallel — same file)

### Within Each User Story

- Tests (TDD) ditulis lebih dulu oleh `zahiira`, konfirmasi RED sebelum impl (konstitusi II)
- Action (BE) sebelum Controller wiring
- BE sebelum FE (FE konsumsi kontrak API)
- Core implementation sebelum integration

### Parallel Opportunities

- T001, T002 (Setup) — parallel
- T005, T006, T007 (Foundational) — parallel (different files)
- T009–T015 (US1 tests) — parallel (different test files)
- T016, T017 (US1 Actions) — parallel (different files); T017a (Service) setelah T016/T017 (Service panggil Action) — sequential dgn Action; T020, T021, T022 (US1 FE) — parallel
- T023–T026 (US2 tests) — parallel; T027 (Action) + T029 (lang) parallel; T030 (FE cell) parallel
- T032, T033 (US3 tests) — parallel; T035 (lang) parallel
- ⚠️ T017a & T027a — SAME file `PatientService.php`, sequential (one agent)
- ⚠️ T036 (US3) & T038 (US4) — SAME file `history.tsx`, JANGAN parallel
- ⚠️ T018, T019, T028, T034 — same file `PatientController.php`, sequential (one agent BE)

---

## Parallel Example: User Story 1

```bash
# Launch all US1 tests together (zahiira):
Task: "Feature test CRUD pasien apps/api/tests/Feature/Patient/PatientCrudTest.php"
Task: "Feature test duplicate phone apps/api/tests/Feature/Patient/PatientDuplicatePhoneTest.php"
Task: "Feature test tenant isolation apps/api/tests/Feature/Patient/PatientTenantIsolationTest.php"
Task: "Unit test CreatePatientAction apps/api/tests/Unit/Actions/Patient/CreatePatientActionTest.php"
Task: "Unit test UpdatePatientAction apps/api/tests/Unit/Actions/Patient/UpdatePatientActionTest.php"

# Launch US1 Actions together (ammar):
Task: "CreatePatientAction apps/api/app/Actions/Patient/CreatePatientAction.php"
Task: "UpdatePatientAction apps/api/app/Actions/Patient/UpdatePatientAction.php"

# Launch US1 FE together (sierly):
Task: "patient-form.tsx +notes field"
Task: "new.tsx duplicate_warning sync"
Task: "edit.tsx duplicate warning handling"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001–T002)
2. Complete Phase 2: Foundational (T003–T008) — CRITICAL blocks all stories
3. Complete Phase 3: User Story 1 (T009–T022) — TDD via `zahiira`, BE via `ammar`, FE via `sierly`
4. **STOP and VALIDATE**: Test US1 independently (CRUD + duplikat + audit log)
5. Deploy/demo jika siap

### Incremental Delivery

1. Setup + Foundational → Foundation ready (soft delete + FK restrict)
2. Add US1 → Test independently → MVP (CRUD + duplikat + audit)
3. Add US2 → Test independently → nonaktifkan + riwayat utuh + restrict
4. Add US3 → Test independently → riwayat kunjungan + withTrashed
5. Add US4 → Test independently → breadcrumb konsisten
6. Polish → quickstart validation

### Delegation Strategy (per CLAUDE.md)

- **BE Laravel** (`ammar`): Service, Actions, Controller, migration, model, policy, request, resource, lang. Pakai skill `/laravel-best-practices`. Brief WAJIB sebut: **Controller→Service→Action** (Controller WAJIB via Service, dilarang langsung ke Action; Service no DB write, WAJIB via Action; read exception: index/show/history di Controller), validasi WAJIB via `PatientRequest` (no inline validation), `LogAuditAction` withProperties old/new, `ponytail:` permission, folder `app/Actions/Patient/`
- **Tests** (`zahiira`): TDD Red-Green-Refactor. Brief WAJIB sebut: withProperties assertion, exception `Log::error` assertion, `PatientFactory`
- **FE** (`sierly`): form `notes`, row actions cell, duplicate warning edit, breadcrumb fix. Pakai skill `/ui-ux-pro`. Brief WAJIB sebut: reuse `components/forms/` + `components/datatable/`, tidak buat komponen form baru, breadcrumb pattern ProductsPage, Linear visual style + tooltip/shortcut per CLAUDE.md authoring discipline
- **Push BE** → `haikal` review via skill `/code-review` level low (only when user asks to push)

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story independently completable & testable
- Verify tests fail before implementing (konstitusi II TDD)
- Commit after each task or logical group (NO AI attribution, NO emoji)
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts (T036/T038 `history.tsx`; T018/T019/T028/T034 `PatientController.php`), cross-story dependencies that break independence
- Konstitusi v1.1.0 (VI) + CLAUDE.md compliance: Controller→Service→Action layering, DB write via Action, Service no DB, validasi via FormRequest, `withProperties` old/new, exception `Log::error`, `ponytail:` permission exception — sudah tercermin di task BE
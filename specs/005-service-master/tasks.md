# Tasks: Master Layanan Klinik (005-service-master)

**Input**: Design documents from `/specs/005-service-master/` — plan.md, spec.md, research.md, data-model.md, contracts/services-api.md, quickstart.md

**Prerequisites**: plan.md (required), spec.md (required)

**Tests**: INCLUDED — Constitution Principle II (TDD) non-negotiable. Test tasks written FIRST (Red) per story, delegated to agent `zahiira`. Implementation tasks: BE → `ammar`, FE → `sierly`.

**Organization**: Tasks grouped by user story (US1 P1, US2 P2, US3 P3) for independent implementation/testing. Feature is a REVISION of existing Service module — no new entity/table.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story (US1, US2, US3)
- Exact file paths in descriptions

## Path Conventions

- BE: `apps/api/` (Laravel) — migrations `database/migrations/`, factories `database/factories/`, app `app/`, lang `lang/id/`, tests `tests/`
- FE: `apps/web/` (TanStack Start) — routes `src/routes/$tenant/clinic/services/`, components `src/components/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Migration + factory prerequisites. No project init (existing Laravel app).

- [ ] T001 [P] Create migration `apps/api/database/migrations/2026_08_14_000000_restrict_service_foreign_keys.php` — drop & recreate FK `bookings.service_id`, `treatment_records.service_id`, `transaction_items.service_id` with `restrictOnDelete` (R1). Verify existing FK names via schema before drop.
- [ ] T002 [P] Create `apps/api/database/factories/ServiceFactory.php` with `BelongsToTenant`-aware defaults (name, description, price>=0, status active), state `archived()` (R9). Used by all test tasks.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tegakkan FK restrict + factory sebelum story test jalan.

- [ ] T003 Run `php artisan migrate` (user runs manually) — confirm migration T001 applies; FK sekarang restrict. Blocker untuk US2 restrict test.

**Checkpoint**: Foundation ready — migration FK restrict aktif, factory tersedia.

---

## Phase 3: User Story 1 - Kelola Daftar Layanan (Priority: P1) — MVP

**Goal**: Admin dapat membuat, melihat, memperbarui layanan; daftar default hanya aktif; activity log create/update.

**Independent Test**: Buat layanan baru → muncul di daftar (arsip tidak) → ubah harga → tersimpan; activity log tercatat naratif. Tanpa melibatkan modul booking.

### Tests for User Story 1 (TDD — zahiira, Red first)

- [ ] T004 [P] [US1] Feature test: admin can list/create/update service in `apps/api/tests/Feature/ServiceControllerTest.php` — index default hanya `active`; store 201; update 200.
- [ ] T005 [P] [US1] Feature test: permission matrix in `apps/api/tests/Feature/ServiceControllerTest.php` — doctor/therapist view 200 + write 403; cashier all 403.
- [ ] T006 [P] [US1] Feature test: validation price>=0 + name required → 422 in `apps/api/tests/Feature/ServiceControllerTest.php`.
- [ ] T007 [P] [US1] Feature test: activity log create/update naratif mengandung nama layanan in `apps/api/tests/Feature/ServiceControllerTest.php`.
- [ ] T008 [P] [US1] Feature test: tenant isolation — layanan tenant A tidak terlihat tenant B in `apps/api/tests/Feature/ServiceControllerTest.php`.

### Implementation for User Story 1

- [ ] T009 [US1] Edit `apps/api/app/Http/Controllers/ServiceController.php` `index()` — default `where status=active` bila `filter[status]` tidak dikirim (R3); tetap hormati filter eksplisit (`archived`/`all`).
- [ ] T010 [US1] Edit `apps/api/app/Http/Controllers/ServiceController.php` `store()` & `update()` — panggil `LogAuditAction` event `service.created`/`service.updated`, narasi "Membuat/Memperbarui layanan {name}" (R4).
- [ ] T011 [P] [US1] Edit `apps/api/lang/id/service.php` — tambah key `edit`, `archive`, `archive_confirm` (R7).
- [ ] T012 [P] [US1] Create `apps/web/src/routes/$tenant/clinic/services/components/service-form-dialog.tsx` — modal create+edit reuse `FormInput`/`FormTextarea`/`FormSelect`/`FormSubmit`/`useForm`; terima `service?` untuk prefill edit + PUT; trigger create via header button (R6).
- [ ] T013 [US1] Create `apps/web/src/routes/$tenant/clinic/services/components/service-actions-cell.tsx` — DropdownMenu mirror `StaffActionsCell` dengan "Ubah" (buka form-dialog edit) saja pada fase ini (R6).
- [ ] T014 [US1] Edit `apps/web/src/routes/$tenant/clinic/services/index.tsx` — tambah kolom aksi (pakai `ServiceActionsCell`), ganti `ServiceFormModal` trigger header ke `ServiceFormDialog` create mode.

**Checkpoint**: US1 fungsional — CRUD + edit via modal + default active filter + activity log. Testable independen.

---

## Phase 4: User Story 2 - Arsip Layanan (Priority: P2)

**Goal**: Arsip via status=archived (bukan hapus); arsip tidak muncul di pilihan booking baru; hard-delete direferensi diblokir restrict; snapshot tetap utuh; activity log "Mengarsipkan layanan {name}".

**Independent Test**: Arsipkan layanan aktif → tidak muncul di dropdown booking baru → activity log tercatat; hard-delete layanan dengan booking → diblokir DB; ubah master → snapshot transaksi/treatment tidak berubah.

### Tests for User Story 2 (TDD — zahiira, Red first)

- [ ] T015 [P] [US2] Feature test: admin archive via `DELETE /services/{id}` → 200 status archived + activity log "Mengarsipkan layanan {name}" in `apps/api/tests/Feature/ServiceControllerTest.php`.
- [ ] T016 [P] [US2] Integration test: hard-delete `Service::delete()` dengan booking ada → `QueryException` (FK restrict); tanpa referensi → sukses in `apps/api/tests/Feature/ServiceArchiveTest.php`.
- [ ] T017 [P] [US2] Unit test: snapshot immutability — ubah service name/price + arsip → `transaction_items.name/unit_price` & `treatment_records.service_name` tidak berubah in `apps/api/tests/Feature/ServiceSnapshotTest.php`.
- [ ] T018 [P] [US2] Feature test: archived service tidak muncul di `GET /services` default (FR-014), muncul saat `filter[status]=archived` in `apps/api/tests/Feature/ServiceControllerTest.php`.

### Implementation for User Story 2

- [ ] T019 [US2] Edit `apps/api/app/Actions/ArchiveServiceAction.php` — inject `LogAuditAction`, panggil event `service.archived`, narasi "Mengarsipkan layanan {name}" (R4). Signature `handle()` tetap.
- [ ] T020 [US2] Extend `apps/web/src/routes/$tenant/clinic/services/components/service-actions-cell.tsx` — tambah menu item "Arsipkan" + `AlertDialog` confirm → `apiDelete /services/{id}` mirror pola `StaffActionsCell` deactivate (R6).

**Checkpoint**: US1 + US2 fungsional — arsip + restrict + snapshot immutable + hide dari booking. Testable independen.

---

## Phase 5: User Story 3 - Breadcrumb Navigasi (Priority: P3)

**Goal**: Breadcrumb jalur induk→halaman aktif benar (fix self-link bug); faceted filter status untuk lihat arsip.

**Independent Test**: Buka halaman layanan → breadcrumb "Beranda Klinik > Layanan" dengan "Layanan" item terakhir (non-link), "Beranda Klinik" link ke `/$tenant/clinic`; filter status bekerja.

### Tests for User Story 3 (TDD — zahiira, Red first)

- [ ] T021 [P] [US3] Feature test: `GET /services?filter[status]=archived` & `filter[status]=all` mengembalikan hasil sesuai filter in `apps/api/tests/Feature/ServiceControllerTest.php` (verifikasi R3 filter eksplisit).

### Implementation for User Story 3

- [ ] T022 [US3] Edit `apps/web/src/routes/$tenant/clinic/services/index.tsx` — fix breadcrumb: item pertama `to: "/$tenant/clinic"` label `t("clinic.clinic")`, item terakhir `t("service.title")` (pattern `ProductsPage`, R6).
- [ ] T023 [US3] Edit `apps/web/src/routes/$tenant/clinic/services/index.tsx` — tambah `DataTableFacetedFilter` status di toolbar (opsi Aktif/Diarsipkan) pakai `apps/web/src/components/datatable/datatable-faceted-filter.tsx` (R6).

**Checkpoint**: Semua story fungsional — breadcrumb benar, filter status bekerja.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T024 [P] Verify `apps/api` — jalankan `php artisan test` (user manual); semua test service lulus.
- [ ] T025 [P] Verify FE — `npx tsc --noEmit --incremental` di `apps/web` (user manual); tidak ada type error.
- [ ] T026 [P] Verify FE — `bun run generate-routes` di `apps/web` (user manual) bila ada route file baru/direname; regen route tree.
- [ ] T027 Run quickstart.md validation — 7 skenario di `specs/005-service-master/quickstart.md` diverifikasi manual end-to-end.
- [ ] T028 [P] Review BE via skill `/code-review` level low (agent `haikal`) bila user minta push — tidak otomatis tiap session.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001 & T002 parallel. No deps.
- **Foundational (Phase 2)**: T003 depends on T001 (migration apply). Blocks US2 restrict test (T016).
- **US1 (Phase 3)**: Tests T004–T008 Red first (parallel, gunakan T002 factory). Impl T009–T014; T009→T010 (same file controller); T012→T013→T014 (FE chain).
- **US2 (Phase 4)**: Tests T015–T018 Red first (parallel). T016 needs T003 (FK restrict active). Impl T019 (BE) + T020 (FE extend cell dari US1).
- **US3 (Phase 5)**: T021 Red. Impl T022→T023 (same file index.tsx, sequential).
- **Polish (Phase 6)**: After all stories.

### User Story Dependencies

- **US1 (P1)**: Starts after Setup. No story deps. MVP.
- **US2 (P2)**: Starts after Setup + T003. Extends US1's `service-actions-cell.tsx` (T020 depends on T013) — sequential after US1.
- **US3 (P3)**: Starts after Setup. Edits `index.tsx` (also edited by US1 T014) — sequential after US1 to avoid file conflict. Logically independent (breadcrumb) but same file.

### Within Each User Story (TDD)

- Tests FIRST (Red) — confirm fail
- Implementation (Green)
- Refactor with tests green

### Parallel Opportunities

- T001 ‖ T002 (Setup, different files)
- T004 ‖ T005 ‖ T006 ‖ T007 ‖ T008 (US1 tests, same test file but zahiira writes sequentially; logically independent assertions)
- T011 (lang) ‖ T012 (FE dialog) during US1 impl (different stacks/files)
- T015 ‖ T016 ‖ T017 ‖ T018 (US2 tests)
- T019 (BE action) ‖ T020 (FE cell) during US2 impl (different stacks)
- T024 ‖ T025 ‖ T026 (Polish, different stacks)

---

## Parallel Example: User Story 1

```bash
# Tests (Red) — zahiira, setelah T002 factory ada:
Task: "Feature test admin CRUD in apps/api/tests/Feature/ServiceControllerTest.php"
Task: "Feature test permission matrix (same file, sequential)"
Task: "Feature test validation (same file, sequential)"
Task: "Feature test activity log (same file, sequential)"
Task: "Feature test tenant isolation (same file, sequential)"

# Implementation — ammar (BE) & sierly (FE) paralel lintas stack:
Task: "ammar: ServiceController index default-active + store/update log (apps/api/app/Http/Controllers/ServiceController.php)"
Task: "ammar: lang/id/service.php +edit/archive/archive_confirm"
Task: "sierly: service-form-dialog.tsx (create+edit modal)"
# Lalu beruntun: actions-cell → index.tsx
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup (migration T001 + factory T002) — parallel
2. Phase 2: T003 apply migration (user runs)
3. Phase 3: US1 — tests Red (zahiira) → impl Green (ammar BE + sierly FE)
4. **STOP & VALIDATE**: `php artisan test` (service tests) + manual quickstart skenario 1, 5, 6
5. MVP layanan CRUD + edit + default active filter siap demo

### Incremental Delivery

1. Setup + Foundational → FK restrict aktif, factory siap
2. + US1 → CRUD + edit + activity log (MVP) → validasi
3. + US2 → arsip + restrict test + snapshot immutable → validasi
4. + US3 → breadcrumb fix + faceted filter → validasi
5. Polish → full quickstart + review (bila push)

### Delegation (per CLAUDE.md)

- **zahiira**: semua test task (T004–T008, T015–T018, T021) — Pest/PHPUnit feature+unit, factory T002.
- **ammar**: BE impl (T001, T009, T010, T011, T019) — migration, controller, lang, action. Pakai skill `/laravel-best-practices` + `/clean-code-principles`.
- **sierly**: FE impl (T012, T013, T014, T020, T022, T023) — dialog, actions cell, index. Pakai skill `/ui-ux-pro` + authoring discipline CLAUDE.md (Linear-style, tooltip+shortcut, breadcrumb).
- **haikal**: T028 review (hanya bila user minta push) via `/code-review` level low.

---

## Notes

- [P] = different files, no deps. Same-file tasks sequential.
- TDD: tests Red before impl Green, per constitution II.
- Commit after each task/logical group (user triggers; no auto-commit).
- Tidak ada form komponen baru di `apps/web/src/components/forms/` — semua field tercover komponen eksisting (R6, YAGNI).
- `php artisan serve` / `bun run dev` / build — user jalankan sendiri, jangan auto-run.
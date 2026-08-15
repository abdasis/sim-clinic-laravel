# Tasks: Booking & Jadwal Klinik

**Input**: Design documents from `/specs/008-booking-schedule/`

**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/bookings-api.md, quickstart.md

**Tests**: TDD WAJIB (konstitusi II non-negotiable). Tests ditulis oleh agent `zahiira` (PHPUnit class-based, `RefreshDatabase`, style `tests/Feature/Patient/*`), lebih dulu/bersamaan per task — RED sebelum GREEN. Backend authoring oleh `ammar` (skill `/laravel-best-practices` + `/clean-code-principles`); frontend oleh `sierly`.

**Organization**: Tasks grouped by user story. Fitur ini **revisi implementasi eksisting** — backend & FE booking sudah ada; tasks fokus pada gap (FK restrict, immutability patient_id, resource flag, FE form edit + disable pasien, breadcrumb fix).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story (US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `apps/api/` — `app/`, `database/migrations/`, `tests/`
- **Frontend**: `apps/web/` — `src/routes/$tenant/clinic/bookings/`, `src/components/`

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Factory & test fixture yang dipakai lintas story (R7).

- [ ] T001 [P] Buat `BookingFactory` di `apps/api/database/factories/BookingFactory.php` — pakai `BelongsToTenant`, create via relasi tenant/patient/service/assignee (mirror `PatientFactory`). Tambah state `done()` (status=done) dan `cancelled()`.
- [ ] T002 [P] Buat `MedicalRecordFactory` di `apps/api/database/factories/MedicalRecordFactory.php` bila belum ada — untuk test immutability patient_id (relasi booking_id, patient_id denormalized dari booking).

**Checkpoint**: Factory siap dipakai semua test story.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migration FK restrict (R1) — blocking karena menyentuh skema yang dipakai semua story. Activity log wrapper & permission matrix sudah ada (R10, no change).

- [ ] T003 Buat migration `apps/api/database/migrations/2026_08_14_*_change_bookings_patient_service_foreign_keys_to_restrict.php` — drop + recreate FK `patient_id` & `service_id` dengan `restrictOnDelete`; skip pada driver SQLite (preseden migration 031000 `assignee`), PostgreSQL tetap RESTRICT. `tenant_id` tetap cascadeOnDelete.
- [ ] T004 [P] [zahiira] Test migration/behavior: skema booking FK restrict pada PostgreSQL (tandai `@group postgres` / skip SQLite `ponytail:` — lihat R7). Verifikasi `Booking` model relasi tetap jalan di SQLite test.

**Checkpoint**: Foundation ready — FK restrict pasang, user story implementation dapat mulai.

---

## Phase 3: User Story 1 - Buat & Kelola Booking (Priority: P1) 🎯 MVP

**Goal**: Staf klinik buat booking, lihat daftar, ubah detail, ubah status sepanjang transisi valid; ubah pasien pada booking yang punya rekam medis ditolak 422.

**Independent Test**: Buat satu booking (pasien + layanan + dokter + waktu) → muncul di daftar & jadwal → ubah status pending→confirmed→done; booking dengan rekam medis tidak bisa ubah pasien (422); done tidak bisa cancelled. Tanpa melibatkan modul rekam medis/transaksi POS.

### Tests for User Story 1 (TDD — zahiira, RED first)

- [ ] T005 [P] [US1] Feature test `apps/api/tests/Feature/Booking/BookingCrudTest.php` — admin/staf CRUD booking (store 201, index, show, update); validasi start_at after:now, end_at after:start_at, assignee role doctor/therapist (422 bila bukan); tenant isolation (booking tenant A tidak terlihat tenant B).
- [ ] T006 [P] [US1] Feature test `apps/api/tests/Feature/Booking/BookingStatusTransitionTest.php` — transisi valid (pending→confirmed→done, pending/confirmed→cancelled) sukses; transisi ilegal (done→cancelled, cancelled→apapun) → 422 `clinic.invalid_transition`; `status_changed_at` terisi; activity log naratif "Status booking {pasien} diubah dari '{lama}' ke '{baru}'." dengan properties old/new.
- [ ] T007 [P] [US1] Feature test `apps/api/tests/Feature/Booking/BookingPatientImmutabilityTest.php` — ubah `patient_id` pada booking yang punya rekam medis → 422 pada field `patient_id` (`booking.patient_immutable`); booking tanpa rekam medis → ubah pasien sukses; ubah field lain (jadwal, notes) pada booking dengan rekam medis → sukses (tidak men sentuh patient_id).
- [ ] T008 [P] [US1] Unit test `apps/api/tests/Unit/Booking/UpdateBookingActionImmutabilityTest.php` — panggil `UpdateBookingAction::handle` langsung dengan `patient_id` beda + rekam medis ada → abort 422 (defense in depth, R6); tanpa rekam medis → sukses.
- [ ] T009 [P] [US1] Feature test `apps/api/tests/Feature/Booking/BookingAuditLogTest.php` — create/update/changeStatus/delete tercatat di `audit_logs` (model `Activity`); narasi create "Membuat booking {layanan} untuk {pasien} pada {tanggal}."; update properties old/new diff; status_change narasi lama→baru.

### Implementation for User Story 1 (ammar)

- [ ] T010 [US1] Edit `apps/api/app/Http/Requests/BookingRequest.php` — di `withValidator` tambah immutability: bila method PUT/PATCH, ambil booking via `$this->route('booking')`; bila input `patient_id` berbeda dari `$booking->patient_id` AND `$booking->medicalRecord()->exists()` → error 422 pada `patient_id` key `booking.patient_immutable`. Pertahankan cek role assignee eksisting.
- [ ] T011 [US1] Edit `apps/api/app/Actions/Booking/UpdateBookingAction.php` — defense in depth: sebelum `update`, bila `isset($attributes['patient_id'])` && `!= $booking->patient_id` && `$booking->medicalRecord()->exists()` → `abort(422, __('booking.patient_immutable'))`. Pertahankan audit log diff old/new eksisting.
- [ ] T012 [US1] Edit `apps/api/app/Http/Resources/BookingResource.php` — expose `has_medical_record` (boolean) via eager-load relasi `medicalRecord` (`$this->whenLoaded('medicalRecord', fn () => $this->medicalRecord !== null, false)`).
- [ ] T013 [US1] Edit `apps/api/app/Http/Controllers/BookingController.php` — pada `show` & `update`, eager-load `medicalRecord` ( agar `has_medical_record` terisi di resource); `schedule` & `index` tidak perlu (tetap ringan). Validasi hasil `php -l` + `php artisan test --filter=Booking`.

**Checkpoint**: User Story 1 fully functional — CRUD + status transition + immutability 422 + audit log, testable independently via `php artisan test --filter=Booking`.

---

## Phase 4: User Story 2 - Jadwal & Deteksi Bentrokan (Priority: P2)

**Goal**: Tampilan jadwal harian/mingguan; deteksi bentrokan assignee (non-blocking, flag `overlap_warnings`); FE indikator bentrokan minimal.

**Independent Test**: Buat dua booking assignee sama rentang tumpang tindih → peringatan bentrokan muncul tanpa memblokir; keduanya tampil di jadwal; booking cancelled tidak dihitung bentrokan.

### Tests for User Story 2 (TDD — zahiira, RED first)

- [ ] T014 [P] [US2] Feature test `apps/api/tests/Feature/Booking/BookingOverlapTest.php` — buat 2 booking bentrokan assignee sama → store 201 + `meta.overlap_warnings` berisi 1 item (FR-035, non-blocking); booking `cancelled` tidak dihitung bentrokan; assignee berbeda rentang sama → tidak ada warning; update booking → overlap mengecualikan diri sendiri (`id != booking.id`).
- [ ] T015 [P] [US2] Feature test `apps/api/tests/Feature/Booking/BookingScheduleTest.php` — `GET /bookings/schedule?from=&to=` mengembalikan booking rentang itu status ≠ cancelled; urut `start_at` lalu `assignee_id`; response shape schedule (data: id/patient_name/service_name/assignee_id/assignee_name/start_at/end_at/status); tenant isolation.

### Implementation for User Story 2 (ammar + sierly)

- [ ] T016 [US2] Verifikasi `apps/api/app/Services/BookingOverlapService.php` sudah benar (FR-035: `assignee_id` sama, `start_at < other.end_at AND end_at > other.start_at`, status ≠ cancelled, `id != self`). Tidak ada perubahan kode jika sudah compliant — konfirmasi via test T014 lulus.
- [ ] T017 [US2] [sierly] Edit `apps/web/src/routes/$tenant/clinic/bookings/index.tsx` — tambah indikator bentrokan minimal di list row (badge/ikon "Bentrokan" bila booking punya overlap). ponytail: indikator persisten di ScheduleCell ditunda bila ScheduleGrid >300 baris (R5) — toast warning saat create/edit sudah cukup FR-035. Pastikan `bun run generate-routes` tidak perlu (tidak ada route file baru).

**Checkpoint**: User Story 2 functional — overlap detection non-blocking teruji + indikator FE minimal. Independen dari US1 (overlap service sudah ada; FE indikator opsional).

---

## Phase 5: User Story 3 - Breadcrumb Navigasi + FE Form Edit (Priority: P3)

**Goal**: Breadcrumb jalur benar; FE form booking create+edit reuse; disable field pasien bila rekam medis ada (UX mencegah 422).

**Independent Test**: Buka halaman booking → breadcrumb "Beranda Klinik > Booking" benar; klik "Ubah" booking → modal edit prefill; booking dengan rekam medis → field pasien disabled.

### Tests for User Story 3 (FE — sierly, manual/visual validation per quickstart §6)

> FE tidak punya test otomatis di stack ini (Vitest unit untuk komponen form opsional). Validasi via quickstart skenario 6.

- [ ] T018 [P] [US3] [sierly] Refactor `apps/web/src/routes/$tenant/clinic/bookings/components/booking-form-modal.tsx` → `booking-form-dialog.tsx` — support create + edit (props `booking?: BookingRow`, `open`, `onOpenChange`); prefill defaultValues dari booking saat edit; `POST /bookings` saat create / `PUT /bookings/{id}` saat edit; tambah field `notes` (`FormTextarea`); tangani `meta.overlap_warnings` (toast warning) kedua mode. Reuse `FormSelect`/`FormDatePicker withTime`/`FormTextarea`/`FormSubmit`/`useForm` — **tidak buat komponen form baru** di `components/forms/`.
- [ ] T019 [US3] [sierly] Saat edit & `booking.has_medical_record === true` → field `patient_id` (`FormSelect`) **disabled** + note kecil i18n `booking.patient_locked_note`. Backend tetap penjaga (FR-037); FE hanya UX. Ambil flag `has_medical_record` dari `BookingResource` (T012).
- [ ] T020 [US3] [sierly] Edit `apps/web/src/routes/$tenant/clinic/bookings/index.tsx` — perbaiki breadcrumb: item pertama `{ label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } }` (bukan self-link `/services`); item terakhir `t("booking.title")` non-link (pattern `products/index.tsx`). Integrasikan trigger edit (aksi "Ubah" per-row/menu → buka `booking-form-dialog` mode edit). Update import dari `booking-form-modal` → `booking-form-dialog`.
- [ ] T021 [P] [US3] [ammar] Tambah i18n key di `apps/api/lang/id/booking.php`: `edit` → "Ubah Booking", `patient_immutable` → "Pasien tidak dapat diubah karena rekam medis sudah ada.", `patient_locked_note` → "Pasien terkunci karena rekam medis sudah ada." (R8). FE mirror via `useTrans` otomatis.

**Checkpoint**: User Story 3 functional — breadcrumb benar, form edit reuse, field pasien disabled bila rekam medis ada.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Validasi lintas story + finishing.

- [ ] T022 [P] [zahiira] Test permission matrix `apps/api/tests/Feature/Booking/BookingPermissionTest.php` — admin/doctor/therapist `rw` (GET/POST/PATCH status 200); cashier 403 semua (R10 — cashier tidak punya modul booking). Verifikasi via `php artisan test --filter=BookingPermission`.
- [ ] T023 Run `cd apps/api && php artisan test --filter=Booking` — semua test booking hijau (crud, status transition, immutability, overlap, schedule, audit, permission).
- [ ] T024 [P] [ammar] Run `cd apps/api && vendor/bin/pint` pada file yang diubah (BookingRequest, UpdateBookingAction, BookingResource, BookingController, migration, lang/id/booking.php).
- [ ] T025 [sierly] Run `cd apps/web && npx tsc --noEmit --incremental` — FE typecheck lulus setelah refactor form-dialog + breadcrumb fix.
- [ ] T026 Jalankan validasi quickstart `specs/008-booking-schedule/quickstart.md` skenario 1–8 (manual, user jalankan `php artisan serve` + `bun run dev` sendiri).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001, T002 paralel — factory untuk test.
- **Foundational (Phase 2)**: T003 (migration) blocking; T004 (test migration) paralel.
- **User Stories (Phase 3–5)**: semua depend on Foundational.
  - US1 (Phase 3) — inti MVP; T005–T009 test paralel, T010–T013 implementasi (T010→T011 urutan logika sama, T012→T013).
  - US2 (Phase 4) — overlap service sudah ada (T016 verifikasi); test T014/T015 paralel; FE T017.
  - US3 (Phase 5) — FE form edit butuh T012 (resource flag) dari US1; T018→T019→T020; T021 i18n paralel.
- **Polish (Phase 6)**: depend on semua story complete.

### User Story Dependencies

- **US1 (P1)**: Mulai setelah Foundational. Tidak depend story lain. T010/T011 (immutability) butuh T001/T002 (factory) + booking dengan rekam medis fixture.
- **US2 (P2)**: Mulai setelah Foundational. Independen — overlap service eksisting; FE indikator opsional. Bisa paralel dengan US1.
- **US3 (P3)**: FE edit (T019) depend T012 (`has_medical_record` resource flag) dari US1. Breadcrumb fix (T020) independen. Mulai setelah US1 T012 selesai.

### Within Each User Story

- Tests (zahiira) RED sebelum implementasi (ammar) — konstitusi II.
- Backend: FormRequest/Action → Resource → Controller wiring.
- Frontend (sierly): setelah backend resource flag siap (US1 T012).

### Parallel Opportunities

- Phase 1: T001 || T002 (factory berbeda).
- Phase 2: T004 paralel dengan T003 (test file berbeda).
- US1: T005 || T006 || T007 || T008 || T009 (5 test file berbeda, paralel zahiira). Lalu T010 || T012 (file berbeda), T11 setelah T010.
- US2: T014 || T015 paralel; T016 || T017 (BE verifikasi vs FE).
- US3: T018 || T021 (FE refactor vs i18n BE); T019 setelah T018; T020 setelah T018.
- Polish: T022 || T024 || T025 paralel.

---

## Parallel Example: User Story 1

```bash
# zahiira: semua test US1 bersamaan (RED first):
Task: "T005 BookingCrudTest — apps/api/tests/Feature/Booking/BookingCrudTest.php"
Task: "T006 BookingStatusTransitionTest — apps/api/tests/Feature/Booking/BookingStatusTransitionTest.php"
Task: "T007 BookingPatientImmutabilityTest — apps/api/tests/Feature/Booking/BookingPatientImmutabilityTest.php"
Task: "T008 UpdateBookingActionImmutabilityTest — apps/api/tests/Unit/Booking/UpdateBookingActionImmutabilityTest.php"
Task: "T009 BookingAuditLogTest — apps/api/tests/Feature/Booking/BookingAuditLogTest.php"

# ammar: implementasi setelah test RED:
Task: "T010 immutability di BookingRequest — apps/api/app/Http/Requests/BookingRequest.php"
Task: "T012 has_medical_record di BookingResource — apps/api/app/Http/Resources/BookingResource.php"  # paralel T010
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Factory (T001, T002).
2. Phase 2: Migration FK restrict (T003, T004).
3. Phase 3: US1 — test RED (T005–T009) → implement (T010–T013).
4. **STOP & VALIDATE**: `php artisan test --filter=Booking` hijau; quickstart §1–§3, §8 lulus.
5. MVP tercapai: CRUD + status transition + immutability + audit log.

### Incremental Delivery

1. Factory + Migration → foundation.
2. US1 → CRUD + immutability + audit (MVP).
3. US2 → overlap test + FE indikator.
4. US3 → FE form edit + breadcrumb fix.
5. Polish → permission test + lint + typecheck + quickstart full.

### Delegation Strategy (per CLAUDE.md)

- **BE authoring**: `ammar` (skill `/laravel-best-practices` + `/clean-code-principles`).
- **BE tests**: `zahiira` (TDD, PHPUnit class-based, `RefreshDatabase`, style `tests/Feature/Patient/*`).
- **FE**: `sierly` (reuse `components/forms/` + `components/datatable/`, tidak buat komponen form baru).
- Independent tasks paralel: BE (ammar) + FE (sierly) bersamaan bila tidak ada dependensi file.

---

## Notes

- [P] = different files, no dependencies.
- [Story] label maps task ke user story.
- TDD WAJIB: test RED sebelum implementasi (konstitusi II).
- Commit setelah task/kelompok logis. NO AI attribution. Conventional Commits.
- Verifikasi cheap: `php -l`, `php artisan test`, `npx tsc --noEmit --incremental`, `vendor/bin/pint`.
- Jangan auto-run `php artisan serve` / `bun run dev` — user jalankan sendiri saat validasi manual.
- SQLite test :memory: — migration FK restrict skip SQLite (R1/R7); DB-level restrict verifikasi pada PostgreSQL (`@group postgres` / `ponytail:`).
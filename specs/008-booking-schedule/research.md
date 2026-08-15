# Research — Booking & Jadwal Klinik (008-booking-schedule)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

Status implementasi saat ini: fitur booking **sudah ada sebagian besar** — backend lengkap (BookingController, BookingService, Actions Create/Update/Delete/ChangeBookingStatus, BookingRequest, UpdateBookingStatusRequest, BookingScheduleRequest, BookingPolicy, BookingResource, Booking model, BookingStatus enum dengan `canTransitionTo`, BookingOverlapService, 2 migration, routes) dan frontend dasar (jadwal harian/mingguan via ScheduleGrid, form create modal, aksi status, breadcrumb). Spec ini adalah **revisi/penyempurnaan** terhadap AC yang belum terpenuhi, bukan greenfield.

## Temuan audit vs AC

| AC / FR | Status saat ini | Gap |
|---------|-----------------|-----|
| FR-030 simpan booking (pasien/layanan/assignee/waktu) | OK — `CreateBookingAction`, `BookingRequest` rules | tidak ada |
| FR-031 transisi status enforced (done tidak → cancelled) | OK — `BookingStatus::canTransitionTo` + `ChangeBookingStatusAction` abort 422 | tidak ada |
| FR-032 tampilan jadwal harian/mingguan | OK — `BookingController::schedule` + `ScheduleGrid` + `bookings/index.tsx` | tidak ada |
| FR-033 booking done = dasar rekam medis | OK — `MedicalRecordService::create` cek `status === Done` | tidak ada |
| FR-034 `status_changed_at` dicatat | OK — `ChangeBookingStatusAction` set `status_changed_at` | tidak ada |
| FR-035 deteksi bentrokan non-blocking, flag `overlap_warnings` | OK — `BookingOverlapService::detect` + controller return `meta.overlap_warnings` + FE toast warning | tidak ada (indikator persisten di jadval opsional, lihat R6) |
| FR-036 validasi waktu/role assignee | OK — `BookingRequest` `after:now`, `after:start_at`, `withValidator` cek `ClinicRole::Doctor/Therapist` | tidak ada |
| FR-037 immutability patient_id bila rekam medis ada → 422 | **GAP** — `BookingRequest` tidak tolak ubah `patient_id` bila `medicalRecord` exists; `UpdateBookingAction::handle` blind update | perlu guard di FormRequest (+ Action defense in depth) |
| FR-038 FK patient_id/assignee_id/service_id restrictOnDelete | **SEPARI** — `assignee_id` sudah restrict (migration 031000); `patient_id` & `service_id` masih `cascadeOnDelete` di migration awal | perlu migration baru ubah 2 FK |
| FR-039 activity log naratif status lama→baru | OK — `ChangeBookingStatusAction` log "Status booking {pasien} diubah dari {lama} ke {baru}" + old/new properties | `UpdateBookingAction` log generik "Memperbarui booking {pasien}" — perlu diff old/new untuk field ubah (pasien/jadwal/layanan) |
| FR-040 booking non-soft-delete (cancelled cukup) | OK — tidak ada `deleted_at` di migration/model | tidak ada |
| FE: kalender/jadwal | OK — `ScheduleGrid` | tidak ada |
| FE: form booking | **SEPARI** — create modal ada (`booking-form-modal.tsx`); **edit belum ada**; field `notes` belum dipakai | refactor → dialog create+edit (mirror `product-form-modal`) |
| FE: disable ubah pasien bila rekam medis ada | **GAP** — `BookingResource` tidak expose flag `has_medical_record`; form tidak disable field pasien | tambah flag resource + disable field pasien saat edit |
| FE: breadcrumb | **BUG** — `bookings/index.tsx` breadcrumb item pertama `to: "/$tenant/clinic/services"` (menunjuk layanan, bukan home klinik). Pattern benar `products/index.tsx` (`to: "/$tenant/clinic"`) | perbaiki ke pattern products |

## R1 — FK restrictOnDelete: migration patient_id + service_id

**Konteks**: Migration awal `2026_07_06_130000_create_bookings_table` membuat 3 FK `cascadeOnDelete`: `patient_id`, `service_id`, `assignee_id`. Migration `2026_08_14_031000` sudah ubah `assignee_id` → restrict (dengan skip SQLite). AC FR-038 menyebut ketiganya `restrictOnDelete`. Dua FK tersisa belum diubah.

**Decision**: Migration baru `2026_08_14_*_change_bookings_patient_service_foreign_keys_to_restrict` — drop + recreate `patient_id` & `service_id` FK dengan `restrictOnDelete`. Sama persis pola migration 031000 (assignee): skip pada driver SQLite (rebuild tabel tidak praktis di test :memory:), PostgreSQL produksi tetap RESTRICT. `tenant_id` tetap `cascadeOnDelete` (pengecualian: hapus tenant = hapus semua datanya).

**Rationale**: `restrictOnDelete` memaksa integritas referensial di DB — hard-delete pasien/layanan yang masih direferensi booking diblokir DB terlepas dari path app. Pasien di-nonaktifkan (soft, FR spec 006), layanan di-arsip (spec 005), user di-nonaktifkan (soft) — bukan hard-delete. Booking tetap utuh sebagai riwayat audit (R6/FR-040). Cocok dengan `docs/erd/bookings.md` "Delete Rule".

**Alternatives ditolak**:
- App-only guard (Policy cek relasi): dapat dilewati seed/job/bug, tidak ada DB guarantee.
- Biarkan `cascadeOnDelete`: melanggar FR-038 + R6 — hard-delete pasien/layanan menghapus booking historis.
- Hanya restrict `patient_id` (Anomali #2 fokus pasien): inkonsisten, FR-038 eksplisit sebut service_id juga.

**Catatan SQLite**: migration 031000 (assignee) sudah preseden — skip SQLite, dokumentasikan `ponytail:`. Test FK restrict dijalankan pada konfigurasi PostgreSQL bila perlu, atau diuji via asumsi skema (lihat R7 testing). Pragmatic MVP: guard app + restrict DB (PostgreSQL) + test behavior via Policy/path app.

## R2 — Immutability patient_id saat rekam medis ada (FR-037, Anomali #2)

**Konteks**: `medical_records.patient_id` adalah denormalized transitif dari `bookings.patient_id` (untuk query riwayat pasien FR-022 tanpa join). Bila `bookings.patient_id` diubah setelah rekam medis dibuat → `medical_records.patient_id` drift → riwayat pasien terbelah. `BookingRequest` saat ini tidak cek ini; `UpdateBookingAction::handle` langsung `$booking->update($attributes)`.

**Decision**: Tegakkan di **`BookingRequest`** (lapis validasi, satu titik untuk store+update):
- Pada method update (route `PUT/PATCH /bookings/{booking}`), bila request mengandung `patient_id` yang berbeda dari `$booking->patient_id` DAN `$booking->medicalRecord()->exists()` → tambah error 422 pada field `patient_id`: "Pasien tidak dapat diubah karena rekam medis sudah ada." (`booking.patient_immutable`).
- `BookingRequest` perlu tahu apakah ini update + booking mana. Dua opsi: (a) inject route param via `route()` helper di FormRequest (`$this->route('booking')`), (b) delegate ke `UpdateBookingAction`. Pilih (a) — FormRequest adalah trust boundary input, penolakan 422 di sini sesuai AC ("di FormRequest/Policy → 422"). Sebagai **defense in depth**, `UpdateBookingAction::handle` juga guard: bila `patient_id` di attributes berbeda dari existing dan `medicalRecord` exists → `abort(422, __('booking.patient_immutable'))`. Ini mencegah pemanggilan Action dari path lain (job, seeder, test) yang skip FormRequest.

**Rationale**: Anomali #2 normalisasi (`docs/normalization/README.md` rekomendasi "immutability `bookings.patient_id` bila record ada — Policy/FormRequest"). Tegakkan di trust boundary (FormRequest) + defense in depth (Action) = robust walau dipanggil dari path non-HTTP. Tidak butuh kolom baru, tidak butuh propagate (booking jarang dipindah pasien pasca-done di MVP).

**Alternatives ditolak**:
- Propagate `patient_id` ke `medical_records` dalam DB transaction saat ubah: melanggar semantik rekam medis (rekam medis ditulis untuk pasien A, lalu dikaitkan ke pasien B = catatan klinis salah pasien — berbahaya secara medis/legal). Immutability lebih aman.
- Policy-only (otorisasi): Policy cek izin peran, bukan invariant data — bukan tempat yang tepat untuk immutability field. FormRequest lebih cocok.
- Hanya Action guard: FormRequest lewat 200 dulu lalu Action abort — respons 422 tetap, tapi error tidak ter-map ke field `patient_id` di FE (UX buruk). FormRequest memberi error per-field (`applyServerErrors` FE map ke field).

**Detail FormRequest**: `BookingRequest::withValidator` sudah ada (cek role assignee). Tambah logic immutability di sana. Deteksi update: `$this->isMethod('PUT') || $this->isMethod('PATCH')`; booking via `$this->route('booking')` (route-model binding). Store tidak perlu cek (booking baru belum punya rekam medis).

**Catatan**: `BookingRequest::authorize()` saat ini return `true` — otorisasi diandalkan ke Policy di Controller (`$this->authorize('update', $booking)`). Ini acceptable (Policy adalah sumber otorisasi), tidak perlu ubah authorize().

## R3 — BookingResource: expose `has_medical_record` flag

**Konteks**: FE butuh tahu apakah booking sudah punya rekam medis untuk (a) disable field pasien saat edit (UX mencegah 422), (b) opsional sembunyikan/tandai. `BookingResource` saat ini tidak expose flag ini. Relasi `medicalRecord()` ada di model.

**Decision**: Tambah `has_medical_record` (boolean) di `BookingResource::toArray` via `$this->whenLoaded('medicalRecord', fn () => $this->medicalRecord !== null)` — tapi `whenLoaded` butuh relasi di-load. Lebih sederhana: `->loadCount('medicalRecord')` di controller atau `medical_record_count > 0`. Pilih flag boolean sederhana: controller `index`/`show`/`update` sudah `load('patient','service','assignee')` — tambah `withCount('medicalRecord')` atau load relasi. Gunakan `'has_medical_record' => $this->medical_record_count > 0` (butuh `withCount`) ATAU eager-load `medicalRecord` dan cek null.

Pilih: eager-load `medicalRecord` di controller update/show + expose `'has_medical_record' => $this->whenLoaded('medicalRecord', fn () => $this->medicalRecord !== null, false)`. Sederhana, tidak tambah query terpisah. Untuk `schedule` endpoint (list jadwal), flag tidak perlu (jadwal tidak edit pasien) — tetap ringan.

**Rationale**: FE disable field pasien = UX pencegah 422 (AC FE). Backend tetap penjaga sebenarnya (FR-037), FE hanya mencegah user mencoba. Flag boolean ringan, satu field.

**Alternatives ditolak**:
- Endpoint terpisah `GET /bookings/{id}/can-edit-patient`: YAGNI, flag di resource cukup.
- Selalu `withCount` di semua query: tidak perlu, hanya show/update yang edit.

## R4 — FE: refactor form modal → dialog create+edit

**Konteks**: `booking-form-modal.tsx` create-only, open-state internal (`useState`). Tidak ada edit. Pattern reuse ada di `product-form-modal.tsx` (create+edit, props `open`/`onOpenChange`/`product?`, prefill defaultValues, POST saat create / PUT saat edit). Form fields booking: patient_id, service_id, assignee_id, start_at, end_at, notes = 6 field. Aturan modal: ≤5 field tanpa logika kompleks. 6 field (5 inti + notes nullable sederhana) → masih modal acceptable (notes textarea sederhana, bukan logika).

**Decision**:
- Rename/refactor `booking-form-modal.tsx` → `booking-form-dialog.tsx`: support mode **create + edit** (terima `booking?: BookingRow`, prefill defaultValues dari booking saat edit, `POST /bookings` saat create / `PUT /bookings/{id}` saat edit). Trigger dibuat terpisah (button "Tambah" di header untuk create; aksi "Ubah" per-row/menu untuk edit).
- Saat **edit** dan `booking.has_medical_record === true` → field `patient_id` (`FormSelect`) **disabled** (readOnly + disabled), tampilkan note kecil "Pasien tidak dapat diubah karena rekam medis sudah ada." Backend tetap penjaga (FR-037); FE hanya UX.
- Field `notes` (`FormTextarea`) ditambahkan (saat ini tidak ada di form meski `BookingRequest` mendukung).
- `FormSelect`/`FormDatePicker withTime`/`FormTextarea`/`FormSubmit`/`useForm` **sudah ada dan reusable** — tidak ada komponen form baru di `components/forms/`. Sesuai instruksi user.
- Response `meta.overlap_warnings` ditangani (toast warning) baik saat create maupun edit.

**Rationale**: Dialog reuse create+edit = DRY (mirror pattern `product-form-modal`). Disable field pasien = UX mencegah 422 (AC FE eksplisit). Tidak ada form komponen baru = YAGNI (semua field tercover komponen eksisting). Breadcrumb fix = bug fix.

**Alternatives ditolak**:
- Halaman edit terpisah (`bookings/$id/edit`): 6 field sederhana → modal cukup (aturan form design CLAUDE.md). Halaman terpisah = over-engineering.
- Buat `FormDateTimeRange`/`FormPatientSelect` baru di `components/forms/`: `FormDatePicker withTime` + `FormSelect` sudah handle. Tidak ada reuse value tambahan. YAGNI.
- Biarkan create-only, edit via halaman lain: inkonsisten dengan pattern master lain (product/service pakai modal edit).

## R5 — FE: perbaikan breadcrumb self-link + overlap indicator

**Konteks**: `bookings/index.tsx` breadcrumb item pertama `to: "/$tenant/clinic/services"` (menunjuk halaman layanan — salah). Pattern benar `products/index.tsx`: `{ label: tenant, to: "/$tenant/clinic", params: { tenant } }`, `{ label: t("clinic.clinic") }`, `{ label: t("product.title") }`. Juga: overlap saat ini hanya toast saat create/edit — tidak ada indikator persisten di jadwal/daftar. Edge case spec menyebut "badge/ikon peringatan yang dapat di-hover untuk detail."

**Decision**:
- Perbaiki breadcrumb `bookings/index.tsx`: item pertama `to: "/$tenant/clinic"`, label `t("clinic.clinic")` (bukan `tenant` mentah) — ikut pattern `products/index.tsx`. Item terakhir `t("booking.title")` (non-link).
- Untuk form edit (dialog), breadcrumb tambahan tidak perlu (modal, bukan route) — US3#2 acceptance "Ubah Booking" berlaku bila edit jadi halaman; karena edit = modal, breadcrumb route tetap "Beranda Klinik > Booking". Catat di quickstart: US3#2 disesuaikan ke modal (tidak ada route `/edit`).
- Overlap indicator: MVP minimal — tetap andalkan toast warning saat create/edit (sudah ada). Indikator persisten di jadwal (badge bentrokan per-cell) = nice-to-have; **tunda (ponytail)** bila kompleksitas `ScheduleGrid` melebihi 300 baris. Prioritas: fix breadcrumb + edit dialog dulu. Tambah overlap indicator di `ScheduleCell` hanya bila ruang (badge kecil) tanpa ledakan baris.

**Rationale**: Breadcrumb fix = bug sesuai konstitusi V (breadcrumb wajib, jalur benar). Overlap indicator persisten = polish; toast sudah memenuhi FR-035 inti (peringatan non-blocking). YAGNI: jangan over-build `ScheduleGrid`.

**Alternatives ditolak**:
- Indikator overlap kompleks (hover card detail, pre-compute semua bentrokan per cell): YAGNI untuk MVP, toast cukup.
- Breadcrumb `tenant` mentah tanpa link home: melanggar konvensi (item lain = link parent nyata).

## R6 — UpdateBookingAction: audit log diff + immutability defense

**Konteks**: `UpdateBookingAction::handle` saat ini log "Memperbarui booking {pasien}." generik, dengan `['old' => $old, 'new' => $new]` (sudah diff). Narasi generik acceptable, tapi AC FR-039 lebih spesifik untuk **status** (sudah ditangani `ChangeBookingStatusAction` narasi lama→baru). Untuk update field biasa (pasien/jadwal/layanan), narasi generik + diff old/new di properties sudah memenuhi FR-039 (withProperties old/new). Tidak perlu narasi per-field.

**Decision**:
- `UpdateBookingAction::handle` tambah **defense in depth** immutability: bila `isset($attributes['patient_id'])` && `$attributes['patient_id'] != $booking->patient_id` && `$booking->medicalRecord()->exists()` → `abort(422, __('booking.patient_immutable'))` sebelum update. (FormRequest sudah jadi baris pertama; Action = baris kedua.)
- Audit log: pertahankan pola saat ini (`old`/`new` diff) — sudah compliant FR-039. Narasi "Memperbarui booking {pasien}." cukup; detail perubahan ada di `properties.old/new`.

**Rationale**: Defense in depth = robust (Action dipanggil bisa tanpa FormRequest). Audit log saat ini sudah compliant — jangan over-engineer narasi per-field (KISS).

**Alternatives ditolak**:
- Narasi "Memperbarui booking {pasien} — {field} berubah dari {lama} ke {baru}" per field: kompleks, multi-field ubah sekaligus jadi narasi panjang. Diff di properties cukup untuk forensik.
- Hanya FormRequest guard (tanpa Action): Action bisa dipanggil dari job/seeder/test skip FormRequest → invariant bobol.

## R7 — Testing strategy (delegasi zahiira)

Test ditulis agent `zahiira` (Pest/PHPUnit feature+unit), sesuai konstitusi II (TDD), setelah BE authoring (ammar):

1. **BookingController feature tests**:
   - admin/staf bisa CRUD booking + ubah status (store/update/updateStatus/schedule/show).
   - transisi status ilegal (`done`→`cancelled`, `cancelled`→apapun) → 422 (FR-031).
   - immutability patient_id: ubah `patient_id` pada booking yang punya rekam medis → 422 pada field `patient_id` (FR-037). Booking tanpa rekam medis → ubah pasien sukses.
   - overlap detection: buat 2 booking bentrokan assignee sama → response `meta.overlap_warnings` berisi 1 item, store tetap 201 (FR-035). Booking cancelled tidak dihitung bentrokan.
   - tenant isolation: booking tenant A tidak terlihat tenant B (TenantScope).
   - validasi: start_at di masa lalu → 422; end_at sebelum start_at → 422; assignee non-doctor/therapist → 422 (FR-036).
   - activity log: create/update/changeStatus tercatat naratif; status change mengandung "dari {lama} ke {baru}" (FR-039).
2. **Immutability unit test**: `UpdateBookingAction` dipanggil langsung dengan `patient_id` beda + rekam medis ada → abort 422 (defense in depth, R6).
3. **Factory**: `BookingFactory` (bila belum ada) — pakai `BelongsToTenant`, create via relasi tenant/patient/service/assignee. `MedicalRecordFactory` untuk immutability test.
4. **Catatan SQLite**: migration FK restrict skip SQLite — test FK restrict sebenarnya butuh PostgreSQL. Pragmatic: test **behavior immutability & transisi** (app-level) di SQLite (lulus); test **DB-level restrict** ditandai `@group postgres` / skip bila driver SQLite (`ponytail:` — verifikasi manual pada PostgreSQL). Utamakan test invariant app.

## R8 — i18n keys tambahan

**Konteks**: `lang/id/booking.php` ada: title, patient, service, assignee, start_at, end_at, status, notes, add, schedule, view_day, view_week, created, updated, deleted, status_updated, invalid_assignee. `clinic.php` ada: booking_status.*, overlap_warning, invalid_transition.

**Decision**: Tambah key di `booking.php`:
- `edit` → "Ubah Booking"
- `patient_immutable` → "Pasien tidak dapat diubah karena rekam medis sudah ada."
- `patient_locked_note` → "Pasien terkunci karena rekam medis sudah ada." (UX note di form)
- `cancel` (bila belum) → reuse `general.cancel` (sudah ada) — tidak perlu duplikasi.

FE mirror keys via `useTrans` (sudah fetch `/translations`).

**Rationale**: Semua teks UI via i18n (konstitusi V). Reuse general keys (DRY).

## R9 — Tidak butuh package baru

Semua kebutuhan tercover paket eksisting: spatie/laravel-activitylog (audit), spatie/laravel-permission (role dinamis — `booking.view`/`booking.manage` via `SyncTenantClinicRolesAction::MATRIX`), react-hook-form + zod, tanstack-query, shadcn primitives, date-fns. FE komponen form/datatable/schedule semua ada. **Context7 tidak perlu dipanggil** — tidak ada library/SDK baru yang butuh dokumentasi terkini. Tidak ada komponen form baru sesuai instruksi user (reuse `components/forms/` eksisting).

## R10 — Permission matrix: tidak ada perubahan

`SyncTenantClinicRolesAction::MATRIX` sudah punya `booking => 'rw'` (admin), `'rw'` (doctor — doctor bisa manage booking), `'rw'` (therapist), dan cashier? Verifikasi: MATRIX baris admin/doctor/therapist punya booking rw; cashier — perlu konfirmasi (R-matriks). `BookingPolicy` delegasi ke `booking.view`/`booking.manage` via Gate. FE sidebar visibility `bookings` roles `["admin","doctor","therapist"]`. **Tidak ada perubahan matriks** — cocok AC (staf klinik kelola booking). Konstitusi VI: role dinamis via spatie ✅ (sudah migrasi, bukan Gate matrix statik).

## Ringkasan keputusan

| ID | Decision |
|----|----------|
| R1 | Migration baru ubah FK `patient_id` + `service_id` → `restrictOnDelete` (skip SQLite, preseden 031000) |
| R2 | Immutability `patient_id` saat rekam medis ada → 422 di `BookingRequest` (trust boundary) + `UpdateBookingAction` (defense in depth) |
| R3 | `BookingResource` expose `has_medical_record` flag (eager-load relasi di show/update) |
| R4 | FE refactor `booking-form-modal` → `booking-form-dialog` create+edit (mirror `product-form-modal`); disable field pasien bila rekam medis ada; tambah field notes; reuse komponen form eksisting, tidak ada form baru |
| R5 | Fix breadcrumb `bookings/index.tsx` self-link → `/clinic` (pattern products); overlap indicator persisten = tunda (ponytail), toast warning cukup untuk FR-035 |
| R6 | `UpdateBookingAction` defense in depth immutability + pertahankan audit log diff old/new (sudah compliant) |
| R7 | zahiira tulis: controller feature (transisi, immutability, overlap, tenant isolation, validasi, audit), immutability unit, factory |
| R8 | Tambah i18n key: booking.edit, patient_immutable, patient_locked_note |
| R9 | Tidak butuh package/library baru, Context7 skip, tidak ada komponen form baru |
| R10 | Permission matrix `SyncTenantClinicRolesAction::MATRIX` tidak berubah |
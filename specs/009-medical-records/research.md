# Research — Rekam Medis SOAP Klinik (009-medical-records)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Sumber**: `docs/erd/medical_records.md`, `docs/erd/bookings.md`, `docs/erd/treatment_records.md`, `docs/erd/medical_photos.md`, `docs/normalization/README.md`, `docs/normalization/workflow.md`, kode existing `apps/api`.

## R1 — Soft delete vs hard delete rekam medis

**Decision**: Soft delete (`deleted_at` + `SoftDeletes` trait). Tidak ada endpoint hard-delete/restore di MVP.

**Rationale**: Rekam medis adalah catatan klinis legal — tidak boleh hilang permanen. Soft-delete memungkinkan koreksi (record salah/retur) tanpa kehilangan jejak audit. `SoftDeletes` global scope otomatis exclude dari list aktif (FR-090/SC-006). DB restrict FK pada child (`medical_record_id`) memblokir hard-delete permanen bila treatment/photo ada (FR-092/SC-008) — tidak butuh app guard.

**Alternatives considered**:
- Hard-delete saja: ditolak — catatan legal wajib bertahan audit (konstitusi VI, FR-090).
- Restore endpoint: ditolak MVP (YAGNI) — `ponytail: restore add saat butuh`.
- Status enum (`active/archived`): ditolak — `SoftDeletes` trait + global scope lebih idiomatik Laravel, kurang boilerplate.

## R2 — FK delete rule: cascade vs restrict

**Decision**: `booking_id`+`patient_id`+`author_id` → `restrictOnDelete` (author_id sudah restrict via migration existing). Child `treatment_records.medical_record_id` + `medical_photos.medical_record_id` → `restrictOnDelete` (**override workflow langkah 15/16 yang menyebut cascade**).

**Rationale**: Spec AC eksplisit: "hard-delete diblokir restrict" + "treatment/photo tetap saat parent soft-delete". Soft-delete parent tidak trigger FK DB (child tetap utuh otomatis). FK restrict hanya mencegah hard-delete parent bila child ada — pertahanan integritas data terakhir. Parent (booking/pasien/dokter) restrict mencegah putus referensi klinis (FR-093/SC-009). Override workflow karena user AC menentukan restrict; workflow cascade adalah default yang ditimpa keputusan spesifik fitur.

**Alternatives considered**:
- Child cascadeOnDelete (workflow default): ditolak — bila seseorang hard-delete parent, child ikut hilang, melanggar "treatment/photo tetap". Cascade hanya aman jika parent tidak pernah hard-delete; restrict lebih kuat jamin integritas.
- App-level guard tanpa FK restrict: ditolak — bug/seed/tinker bypass app; FK restrict pertahanan DB (konstitusi III integritas).

**Catatan SQLite**: alter FK delete rule tidak didukung SQLite. Migration guard `if (Schema::getConnection()->getDriverName() === 'sqlite') { return; }` — `ForeignKeyRestrictTest` hanya jalan via `phpunit.pgsql.xml` (R8 strategi, konvensi CLAUDE.md).

## R3 — Index `(tenant_id, patient_id, created_at)` untuk FR-022

**Decision**: Tambah composite index `(tenant_id, patient_id, created_at)`.

**Rationale**: Riwayat rekam medis per pasien (FR-022) query `WHERE tenant_id=? AND patient_id=? ORDER BY created_at` — composite index lead `tenant_id` (isolasi multi-tenant, konstitusi III) + `patient_id` (filter pasien) + `created_at` (sort kronologis). Hindari full scan untuk pasien dengan puluhan kunjungan (SC-005 <1 detik). `tenant_id` lead wajib untuk index yang berguna di multi-tenant (TenantScope filter `tenant_id`).

**Alternatives considered**:
- Index `(patient_id, created_at)` tanpa `tenant_id`: ditolak — TenantScope filter `tenant_id` duluan; index tanpa lead `tenant_id` tidak optimal untuk query scoped-tenant.
- Index `(tenant_id, patient_id)`: kurang optimal — sort `created_at` butuh filesort.
- Full table scan + sort: ditolak — tidak skalabel untuk pasien ramai.

## R4 — Immutability `patient_id` (anomali #2)

**Decision**: Invariant ditegakkan di booking side (sudah ada). Spec 009 verifikasi + catat, tidak implementasi ulang.

**Rationale**: Commit `feat(booking): kunci pasien setelah rekam medis dan lengkapi form ubah` sudah tolak ubah `patient_id` booking bila `medicalRecord` exists → 422. `patient_id` rekam medis diisi dari `booking->patient_id` saat create (`CreateMedicalRecordAction` existing) dan tidak pernah di-update (tidak ada field `patient_id` di `MedicalRecordRequest` update). Drift tercegah dua sisi: booking immutable + record tidak expose update patient_id.

**Alternatives considered**:
- Propagate `patient_id` ke `medical_records` saat booking diubah: ditolak — booking immutable setelah record ada, tidak ada skenario ubah.
- Re-implement guard di medical record action: ditolak — double guard redundan (YAGNI); booking side sudah cukup.

## R5 — Audit narasi create: "Mengisi rekam medis pasien {patient}"

**Decision**: Ubah narasi `CreateMedicalRecordAction` dari "Menulis rekam medis untuk {patient}" → "Mengisi rekam medis pasien {patient}" (FR-094).

**Rationale**: Spec FR-094 + AC eksplisit "Mengisi rekam medis pasien {patient}". Konsistensi narasi konstitusi VI (informatif + naratif semi-formal friendly). `withProperties` full attributes SOAP.

**Alternatives considered**: Pertahankan narasi existing: ditolak — tidak sesuai spec AC.

## R6 — Endpoint shape: index/show/update/destroy + riwayat pasien

**Decision**: Tambah `index`, `show`, `update`, `destroy` (soft-delete) + route `GET patients/{patient}/medical-records` (riwayat per pasien). Existing `store`/`addTreatment`/`addPhoto` tetap. `patientTreatments` controller method di-rename → `patientRecords` + daftarkan route.

**Rationale**: Spec FR-096 (list + search/sort/pagination server-side) butuh `index`. FR-094 update butuh `update`. FR-090 soft-delete butuh `destroy`. FR-022 riwayat per pasien butuh dedicated route (query `patient_id` index R3). Controller existing hanya `store`+child add — perlu read/update/delete lengkap untuk lifecycle rekam medis. `index`/`show` read-only boleh inject read query langsung (exception CLAUDE.md read). `update`/`destroy` via Service→Action.

**Alternatives considered**:
- Riwayat per pasien via `index?filter[patient_id]=`: ditolak — route eksplisit `patients/{patient}/medical-records` lebih RESTful + jelas breadcrumb, sesuai pola nested resource.
- Update via `PUT` vs `PATCH`: `PATCH` (partial SOAP update, field nullable).

## R7 — FE: reuse penuh, 0 komponen baru

**Decision**: Reuse `components/forms/` (`FormTextarea`×4 SOAP, `FormSubmit`, `useForm`+`applyServerErrors`, `zod`) + `components/datatable/` (`DataTable`+`Toolbar`+`Pagination`+`ColumnHeader`) + `components/clinic-breadcrumb.tsx` (`ClinicBreadcrumb`). Tidak buat komponen baru di `forms/`/`datatable/`/`ui/`.

**Rationale**: Medical record form = 4 `FormTextarea` (S/O/A/P) inline di satu form create/edit — tidak butuh `SoapNoteFields` extract (1 konsumen, inline cukup, <300 baris). Booking_id didapat dari route query (`?booking=`) saat isi dari booking done — tidak butuh combobox pasien (pasien turun dari booking). Riwayat per pasien = `DataTable` standar dengan kolom tanggal/dokter/ringkasan SOAP. Breadcrumb = `ClinicBreadcrumb({items})` existing. Ponytail: tidak ada gap komponen yang justifikasi buat baru.

**Alternatives considered**:
- `FormCombobox` untuk pilih pasien: tidak dibutuhkan — rekam medis selalu dari booking (pasien turun dari booking). `FormCombobox` tetap milik 008 (POS pilih pasien).
- `SoapSection`/`SoapNoteFields` extract: 1 konsumen, inline 4 textarea <50 baris — YAGNI.
- `StatusBadge` untuk status rekam medis: rekam medis tidak punya status enum (hanya active/soft-deleted) — tidak butuh badge.

## R8 — Migration SQLite vs PostgreSQL guard

**Decision**: Satu migration multi-statement dengan guard driver per bagian: soft delete + index jalan di SQLite; FK restrict alter guard `pgsql` (skip SQLite).

**Rationale**: SQLite tidak mendukung drop+recreate foreign key alter. Constraint restrict diverifikasi via `phpunit.pgsql.xml` (`ForeignKeyRestrictTest`). Konvensi CLAUDE.md: "Migration foreign key RESTRICT dilewati di SQLite ... ForeignKeyRestrictTest hanya berjalan lewat phpunit.pgsql.xml." `softDeletes()` + `$table->index()` jalan di kedua driver.

**Alternatives considered**:
- Pisah 2 migration (sqlite-safe + pgsql-only): lebih banyak file, tidak perlu — 1 migration dengan guard lebih ringkas.
- Skip index di SQLite: tidak perlu — index jalan di SQLite.
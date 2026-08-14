# Research — Master Pasien Klinik (006-patient-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

Status implementasi saat ini: fitur pasien **sudah ada sebagian** (PatientController dengan index/store/show/update/history, Patient model, PatientRequest, PatientPolicy, PatientResource, migration `patients`, route `apiResource(...)->except(['destroy'])`, FE `index.tsx`/`new.tsx`/`edit.tsx`/`history.tsx`/`treatments.tsx` + `patient-form.tsx`). Spec ini adalah **revisi/penyempurnaan** terhadap AC yang belum terpenuhi, bukan greenfield.

## Temuan audit vs AC

| AC / FR | Status saat ini | Gap |
|---------|-----------------|-----|
| FR-020 data pasien (7 atribut) | **GAP** — `patient-form.tsx` tidak punya field `notes` (schema/model/request mendukung, FE tidak expose) | tambah field `notes` di form |
| FR-021/023 duplikat phone warning | OK di `store` (returns `duplicate_warning` + `duplicate_patient_id`); FE `new.tsx` tampilkan AlertDialog. **GAP**: `update` tidak deteksi duplikat; FE `edit.tsx` tidak handle warning | tambah deteksi di `update` + handle di `edit.tsx` |
| FR-022 riwayat kunjungan | OK — `history()` agregasi booking+treatment terurut. **GAP**: route `history` pakai implicit binding `Patient $patient` → pasien ter-soft-delete 404 (R5) | `withTrashed` di route/model binding (R5) |
| FR-024 update kontak | OK — `update` ada | tidak ada |
| FR-025 nonaktifkan (soft delete) | **GAP** — model tidak pakai `SoftDeletes`, migration tidak punya `deleted_at`; route `destroy` dikecualikan | +SoftDeletes + kolom + index + route destroy = soft delete (R1, R3) |
| FR-026 nonaktif tidak muncul di list aktif | **GAP** — `SoftDeletes` global scope otomatis hide `deleted_at`, tapi belum dipasang | terselesaikan bersama R1 |
| FR-027 hard-delete direferensi diblokir restrict | **GAP** — FK `bookings.patient_id`=`cascadeOnDelete`, `medical_records.patient_id`=`cascadeOnDelete`, `transactions.patient_id`=`nullOnDelete`. Tidak ada yang restrict | migration drop+recreate 3 FK → `restrictOnDelete` (R2) |
| FR-028 riwayat tetap utuh saat nonaktif | **GAP** — soft delete belum ada; cascade salah bisa hapus booking/rekam medis | soft delete (R1) + restrict (R2) jamin riwayat utuh |
| FR-029 activity log "Menonaktifkan pasien {name}" | **GAP** — `PatientController::store/update` tidak log; tidak ada aksi nonaktifkan | `DeactivatePatientAction` + `LogAuditAction` di store/update/deactivate (R3, R4) |
| FR-030 tenant isolation | OK — `BelongsToTenant`+`TenantScope` | tidak ada |
| FR-031 search/sort/paginate | OK — `InteractsWithDataTable` | tidak ada |
| FE breadcrumb | **BUG** — `index.tsx` & `history.tsx` breadcrumb item pertama `to: "/$tenant/clinic/patients"` (menunjuk diri sendiri), bukan `/clinic`. Pattern benar ada di `ProductsPage` (`to: "/$tenant/clinic"`) | perbaiki ke pattern ProductsPage (R7) |
| FE edit/deactivate action | **GAP** — tidak ada actions column; `destroy` route tidak ada | tambah `patient-actions-cell.tsx` mirror `StaffActionsCell` (R7) |

## R1 — Soft delete: model + migration

**Konteks**: `Patient` saat ini tidak soft delete. ERD `docs/erd/patients.md` mensyaratkan `deleted_at` nullable + index `(tenant_id, deleted_at)`. AC: nonaktifkan = soft delete; pasien nonaktif tidak muncul di list aktif (FR-025/026).

**Decision**:
- Model `Patient`: tambah `use SoftDeletes;`. Laravel `SoftDeletes` global scope otomatis exclude `whereNotNull('deleted_at')` dari query default → FR-026 tercapai tanpa kode query eksplisit.
- Migration `2026_07_06_120000_create_patients_table.php` (edit file eksisting, bukan migration baru — repo belum production, sesuai pola spec 004 yang edit migration users): tambah `$table->softDeletes();` (kolom `deleted_at` nullable timestamp) + `$table->index(['tenant_id', 'deleted_at']);`. Index ini mendukung query list aktif per tenant (`whereNull('deleted_at')`).
- `PatientResource`: tambah `deleted_at` (ISO-8601) agar FE bisa tampilkan penanda nonaktif bila perlu.

**Rationale**: `SoftDeletes` adalah fitur native Eloquent — pertahanan DB-level + global scope otomatis (konstitusi IV rung 3: native platform feature). Index `(tenant_id, deleted_at)` sesuai ERD untuk performa list aktif per tenant. Edit migration eksisting (bukan tambah migration alter) konsisten dengan repo pre-production dan pola spec 004.

**Alternatives ditolak**:
- Kolom `status` enum (active/inactive) seperti services: pasien pakai soft delete per ERD (berbeda dari layanan yang pakai status archived). Soft delete lebih sesuai semantik "nonaktifkan, data klinis tetap".
- Migration baru `alter` tambah `deleted_at`: redundan di repo pre-production; spec 004 sudah set preseden edit migration eksisting.

## R2 — FK restrictOnDelete: pendekatan migration

**Konteks**: 3 FK menunjuk `patients`:
- `bookings.patient_id` → `cascadeOnDelete` (salah — booking & rekam medis historis hilang bila pasien di-hard-delete)
- `medical_records.patient_id` → `cascadeOnDelete` (salah — rekam medis historis hilang)
- `transactions.patient_id` (nullable) → `nullOnDelete` (salah — relasi transaksi putus)

AC spec: ketiganya `restrictOnDelete`.

**Decision**: Migration baru `2026_08_14_*_restrict_patient_foreign_keys` drop + recreate ketiga FK dengan `restrictOnDelete`. Laravel Blueprint: `dropForeign` lalu `foreign('patient_id')->references('id')->on('patients')->restrictOnDelete()`.

**Rationale**: `restrictOnDelete` memaksa integritas referensial di DB — hard-delete pasien yang masih direferensi diblokir DB terlepas dari path app. Pertahanan terakhir setelah Policy/app. Cocok dengan FR-028 (riwayat historis utuh). Catatan nullable FK `transactions.patient_id`: `restrictOnDelete` bekerja pada nilai non-null; baris `patient_id IS NULL` tidak terpengaruh. Aman.

**Alternatives ditolak**:
- App-only guard (Policy cek relasi): dapat dilewati seed/job/bug, tidak ada DB guarantee.
- Biarkan cascade/null: melanggar AC FR-027 + FR-028 (cascade hapus booking/rekam medis; null putus relasi transaksi).

## R3 — Route destroy = nonaktifkan (soft delete); hard-delete tidak diekspos

**Konteks**: route saat ini `->except(['destroy'])` — tidak ada aksi hapus/nonaktifkan. AC: aksi nonaktifkan via soft delete; hard-delete direferensi diblokir restrict.

**Decision**:
- Tambah route `destroy` kembali (hapus `except(['destroy'])` atau daftar eksplisit `Route::delete('patients/{patient}', [PatientController::class, 'destroy'])`). `PatientController::destroy` panggil `DeactivatePatientAction` (soft delete + log).
- `DeactivatePatientAction::handle(Patient $patient)`: `$patient->delete();` (trigger SoftDeletes set `deleted_at`) + `LogAuditAction` event `patient.deactivated`, narasi "Menonaktifkan pasien {name}". Mirror `RemoveUserAction` (DB transaction bila perlu — soft delete tunggal tidak butuh transaction, tapi konsisten). Tanpa proteksi admin-terakhir (pasien tidak punya analog itu).
- Hard-delete permanen **tidak diekspos via API** (YAGNI — tidak ada UI/kebutuhan hapus permanen; nonaktifkan sudah cukup). `restrictOnDelete` di DB tetap dipasang sebagai pertahanan integritas bila ada operasi internal (artisan/manual) yang mencoba hard-delete — pemblokiran terjadi di level DB, bukan endpoint.

**Rationale**: FR-025 eksplisit "nonaktifkan (soft delete), bukan hapus permanen". Tidak ada FR minta endpoint hard-delete. Restrict tetap memberi jaminan FR-027 ("hard-delete pasien direferensi → diblokir") untuk path apa pun yang mencoba, tanpa endpoint baru. Konsisten dengan keputusan spec 005 R2.

**Alternatives ditolak**:
- Endpoint `POST /patients/{patient}/deactivate` (mirror staff): staff pakai `deactivate` karena `destroy` di-exception untuk proteksi admin-terakhir. Pasien tidak punya proteksi serupa; `destroy` (DELETE) adalah idiom REST untuk nonaktifkan via soft delete — lebih sederhana, satu route. Tapi bila konsistensi dengan staff lebih diutamakan, `deactivate` juga valid. Pilihan: `destroy` = soft delete (idomatis, minimal route).
- Endpoint `DELETE /patients/{id}/force` dengan try/catch `QueryException` → 409: YAGNI, tidak ada konsumen.
- Ubah `destroy` jadi hard-delete: melanggar FR-025.

**Verifikasi FR-027**: test assert `Patient::delete()` (force delete, `forceDelete()`) pada pasien dengan booking → melempar `QueryException` (restrict). Soft delete (`delete()`) tidak memicu restrict (FK tetap, `deleted_at` terisi) → dibuktikan via test terpisah.

## R4 — Activity log wiring (FR-029)

**Konteks**: `LogAuditAction` sudah ada (spatie/laravel-activitylog wrapper, signature `(action, subject, causer, context, description, tenant)`). Saat ini `PatientController::store/update` tidak log; tidak ada aksi deactivate.

**Decision**:
- `PatientController::store` → `LogAuditAction` event `patient.created`, narasi "Membuat pasien {name}".
- `PatientController::update` → `LogAuditAction` event `patient.updated`, narasi "Memperbarui pasien {name}" (MVP: narasi generik; bila field signifikan berubah bisa diperkaya nanti — YAGNI sekarang).
- `DeactivatePatientAction::handle` → `LogAuditAction` event `patient.deactivated`, narasi "Menonaktifkan pasien {name}".
- Inject `LogAuditAction` via constructor di Controller (sudah ada pattern inject di Controller lain) / di Action.

**Rationale**: Konstitusi VI mewajibkan setiap aksi ubah-data log naratif. `LogAuditAction` satu pintu (DRY). Action boleh inject activity log (CLAUDE.md "Action may inject: event dispatcher, activity log").

**Alternatives ditolak**:
- spatie auto-log via `LogsActivity` trait: narasi robotik "updated Patient" — melanggar konstitusi VI (naratif). Pakai `LogAuditAction` eksplisit.
- Buat Action untuk create/update hanya untuk log: YAGNI — log di Controller acceptable (no business logic, hanya catat). `DeactivatePatientAction` layak Action karena soft delete + log adalah unit kerja nonaktifkan yang reusable.

**Catatan narasi**: Gunakan `$patient->name`. Format: "Menonaktifkan pasien {name}", "Membuat pasien {name}", "Memperbarui pasien {name}".

## R5 — Riwayat pasien tetap dapat diakses saat nonaktif (FR-022/028)

**Konteks**: route `history` saat ini `Route::get('patients/{patient}/history', ...)` pakai implicit model binding `Patient $patient`. Setelah `SoftDeletes` dipasang, global scope exclude pasien nonaktif → binding 404 untuk pasien ter-soft-delete. AC: riwayat tetap dapat diakses walau pasien dinonaktifkan.

**Decision**: route binding untuk `history` (dan `show`/`treatments` bila perlu akses pasien nonaktif) resolve `withTrashed`. Pendekatan minimal: di `PatientController::history` gunakan route binding custom ATAU ubah signature terima route param + query `Patient::withTrashed()->findOrFail($id)`. Pilihan paling bersih: definisikan route dengan explicit binding `->withTrashed()` di `RouteServiceProvider`/bootstrap, atau di method ambil `id` lalu `Patient::withTrashed()->findOrFail()`. Karena hanya `history`/`show` yang butuh akses pasien nonaktif, query `withTrashed` di method tersebut (TENANT scope tetap aktif — `TenantScope` tidak konflik dengan `withTrashed`).

**Rationale**: Soft delete global scope default hide nonaktif (FR-026 untuk list), tapi riwayat butuh akses penuh (FR-022). `withTrashed` adalah fitur native Eloquent untuk override scope pada query spesifik — satu titik, tidak melemahkan isolasi tenant. Tenant scope tetap aktif.

**Alternatives ditolak**:
- Hapus soft delete global scope global: melanggar FR-026 (list aktif bocor).
- Endpoint riwayat terpisah `/patients-trashed/{id}/history`: duplikasi, melanggar DRY.
- Buat pasien nonaktif tetap muncul di list dengan badge: melanggar AC "tidak muncul di list aktif".

## R6 — i18n keys tambahan

**Konteks**: `lang/id/patient.php` ada: title, name, birth_date, gender, phone, whatsapp, address, notes, add, history, created, updated, edit, treatments, duplicate_title, gender_*, history_type. **Missing**: `duplicate_warning` (FE `new.tsx` reference `t("patient.duplicate_warning")` tapi lang pakai `duplicate_body` — mismatch), `history_empty` (FE reference, lang tidak punya), `deactivate`, `deactivate_confirm`, `deactivated`. `general.php` punya: save, cancel, edit, actions, ok, search.

**Decision**: Tambah/rapikan key di `patient.php`:
- `duplicate_warning` → "Nomor telepon ini sudah terdaftar untuk pasien lain. Data tetap disimpan." (sinkronkan reference FE; `duplicate_body` bisa di-drop atau dijadikan alias — pilih satu nama, pakai `duplicate_warning`).
- `history_empty` → "Belum ada riwayat kunjungan."
- `deactivate` → "Nonaktifkan"
- `deactivate_confirm` → "Nonaktifkan pasien ini? Pasien nonaktif tidak muncul di daftar aktif, tetapi riwayat kunjungan & rekam medis tetap utuh."
- `deactivated` → "Pasien berhasil dinonaktifkan."

Tidak perlu key baru di `general.php` (`edit`, `cancel`, `actions`, `save`, `ok` sudah ada).

**Rationale**: Semua teks UI via i18n (konstitusi V). Reuse general keys (DRY). Sinkronisasi reference FE/BE menghindari missing-key.

## R7 — FE: row actions, field notes, duplicate warning di edit, breadcrumb fix

**Konteks**:
- `index.tsx`: hanya 3 kolom (name, phone, gender), tidak ada actions column. Breadcrumb buggy (item pertama self-link `to: "/$tenant/clinic/patients"`). Tidak ada aksi nonaktifkan/edit per-row (edit via halaman terpisah `/$id/edit` ada tapi tidak ada link dari row).
- `patient-form.tsx`: schema punya `notes`? tidak — form FE tidak expose `notes` (BE `PatientRequest` mendukung `notes` nullable|string).
- `new.tsx`: handle duplicate warning (AlertDialog). `edit.tsx`: **tidak handle** duplicate warning (update tidak deteksi duplikat juga — R4 di BE + handle di FE).
- `history.tsx`: breadcrumb buggy (item pertama self-link). Breadcrumb tidak tampilkan nama pasien.
- Form pasien = 7 field (name, birth_date, gender, phone, whatsapp, address, notes) > 5 → **halaman terpisah** (sesuai aturan form design CLAUDE.md: >5 field = halaman, bukan modal). Halaman `new.tsx`/`edit.tsx` sudah ada — pertahankan, tidak pindah ke modal.
- Komponen form `FormInput/FormDatePicker/FormSelect/FormTextarea/FormSubmit/useForm` **sudah ada dan reusable** — `patient-form.tsx` sudah pakai semua. **Tidak ada form komponen baru** di `components/forms/`. User input eksplisit minta reuse komponen `datatable/` & `forms/` eksisting; buat form baru hanya bila belum tersedia — semua tercover.

**Decision**:
- `patient-form.tsx`: tambah field `notes` (`FormTextarea`) + tambah `notes` ke `patientSchema` (`z.string().optional()`) + `patientDefaults` (`notes: ""`).
- `index.tsx`:
  - Tambah kolom `actions` → render `PatientActionsCell`.
  - Fix breadcrumb: item pertama `{ label: tenant, to: "/$tenant/clinic", params: { tenant } }` (pattern ProductsPage), item kedua `{ label: t("clinic.clinic") }` (no link), item ketiga `{ label: t("patient.title") }` (last, no link).
  - Row "Ubah" → `Link` ke `/$tenant/clinic/patients/$id/edit`. Row "Nonaktifkan" → AlertDialog confirm → `DELETE /patients/{id}`.
- `patient-actions-cell.tsx` (NEW, mirror `StaffActionsCell`): DropdownMenu "Ubah" (navigate edit) + "Nonaktifkan" (AlertDialog confirm → `apiDelete`). Karena "Ubah" adalah navigasi (bukan mutation), tidak perlu Dialog edit inline — pakai halaman edit eksisting. Lebih sederhana dari staff (staff pakai Dialog role inline karena hanya 1 field cepat).
- `edit.tsx`: tambah duplicate warning handling (sama dengan `new.tsx`): mutation `onSuccess` cek `meta.duplicate_warning` → tampilkan AlertDialog. Update BE juga deteksi duplikat (R4).
- `history.tsx`: fix breadcrumb (pattern sama: `clinic` → `patient.title` link → nama pasien link ke detail → `history` last). Breadcrumb tampilkan nama pasien (fetch detail pasien via `useQuery` `show`, atau dari data history pertama — ambil dari query `show` terpisah agar punya nama walau history kosong).
- `new.tsx`: fix key reference `duplicate_warning` (sinkron R6).

**Rationale**: Halaman terpisah untuk 7 field = sesuai aturan form design (bukan over-engineering). Row-actions mirror pattern eksisting = konsistensi. Tidak ada form komponen baru (semua field tercover) = YAGNI + reuse eksisting (user input). Breadcrumb fix = bug fix (konstitusi V). Duplicate warning di edit = paritas dengan create.

**Alternatives ditolak**:
- Pindah form ke modal: 7 field > 5 → melanggar aturan form design CLAUDE.md.
- Buat `FormNotes`/`FormPhone` baru di `components/forms/`: `FormTextarea`/`FormInput` sudah handle. Tidak ada reuse value. YAGNI.
- Dialog edit inline di actions-cell (seperti staff role): pasien 7 field → halaman edit lebih sesuai; Dialog inline hanya untuk 1-2 field cepat.

## R8 — Testing strategy (delegasi zahiira)

Test yang ditulis oleh agent `zahiira` (Pest/PHPUnit feature+unit), sesuai konstitusi II (TDD):

1. **PatientController feature tests**:
   - admin dapat CRUD + nonaktifkan (store/update/destroy-softdelete).
   - doctor view + create (doctor `patient` rw per matriks? matriks: doctor `patient rw` — ya, doctor bisa CRUD pasien); therapist view only (`patient r`); cashier `patient rw` (matriks cashier `patient rw`). Verifikasi per matriks aktual.
   - index hanya pasien aktif (soft-delete tidak muncul).
   - duplicate phone: store/update dengan phone ganda → 201/200 + `meta.duplicate_warning=true`, tetap tersimpan.
   - tenant isolation: pasien tenant A tidak terlihat tenant B.
   - activity log tercatat untuk create/update/deactivate (assert `audit_logs` row + narasi mengandung nama).
   - history accessible untuk pasien nonaktif (`withTrashed`).
2. **FK restrict test** (unit/integration): `Patient::forceDelete()` dengan booking/transaksi/rekam medis ada → `QueryException`; tanpa referensi → sukses. Soft delete (`delete()`) tidak memicu restrict.
3. **Soft-delete test**: nonaktifkan pasien → `deleted_at` terisi, tidak muncul di `index`, riwayat (`history`) tetap lengkap, relasi booking/rekam medis/transaksi tetap utuh.
4. **Validation test**: name/phone kosong → 422; birth_date masa depan → 422; gender invalid → 422.

Factory: `PatientFactory` (bila belum ada) pakai `BelongsToTenant` — create via relasi tenant.

## Ringkasan keputusan

| ID | Decision |
|----|----------|
| R1 | `Patient` +`SoftDeletes`; migration +`softDeletes()` + index `(tenant_id, deleted_at)`; Resource +`deleted_at` |
| R2 | Migration baru drop+recreate 3 FK (`bookings/medical_records/transactions.patient_id`) → `restrictOnDelete` |
| R3 | Route `destroy` = nonaktifkan via `DeactivatePatientAction` (soft delete + log); hard-delete tidak diekspos endpoint; restrict DB jadi penjaga FR-027 |
| R4 | `LogAuditAction` di store/update/deactivate, narasi "Membuat/Memperbarui/Menonaktifkan pasien {name}" |
| R5 | `history`/`show` resolve `withTrashed` (TenantScope tetap aktif) → riwayat tetap dapat diakses walau nonaktif |
| R6 | Tambah/sinkron i18n key patient: duplicate_warning, history_empty, deactivate, deactivate_confirm, deactivated |
| R7 | FE: +field notes, +actions column (`patient-actions-cell.tsx` mirror staff), duplicate warning di edit, fix breadcrumb index/history. Form 7 field = halaman terpisah. Tidak ada form komponen baru |
| R8 | zahiira tulis: controller feature (CRUD+deactivate+duplicate+tenant isolation+history-nonaktif), FK restrict, soft-delete, validation tests. `PatientFactory` bila belum ada |

## Tidak butuh package baru

Semua kebutuhan tercover paket eksisting: spatie/laravel-activitylog (audit), spatie/laravel-permission (sudah terpasang; patient pakai Gate matrix statik `ClinicPermission` — acceptable per konstitusi VI exception karena role fixed). FE: react-hook-form, zod, tanstack-query, shadcn primitives, hugeicons — semua ada. **Context7 tidak perlu dipanggil** — tidak ada library/SDK baru yang butuh dokumentasi terkini.
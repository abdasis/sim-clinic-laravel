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
| FR-029 activity log "Menonaktifkan pasien {name}" | **GAP** — `PatientController::store/update` melakukan DB write langsung tanpa Service/Action & tanpa log; tidak ada aksi nonaktifkan; konstitusi v1.1.0 (VI) + CLAUDE.md baru mewajibkan Controller→Service→Action, DB write via Action, log via `activity()` + `withProperties` old/new | `PatientService` (orkestrasi) + `Create/Update/DeactivatePatientAction` (folder `app/Actions/Patient/`) + `LogAuditAction` dengan `withProperties` (create→full, update→old+new, deactivate→old+new) (R3, R4) |
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
- Tambah route `destroy` kembali (hapus `except(['destroy'])` atau daftar eksplisit `Route::delete('patients/{patient}', [PatientController::class, 'destroy'])`). `PatientController::destroy` panggil `PatientService::deactivate()` → `DeactivatePatientAction` (soft delete + log). Controller **tidak menyentuh DB & tidak langsung ke Action** — hanya `authorize` + panggil Service (CLAUDE.md: "Controller WAJIB memanggil Service, dilarang langsung ke Action").
- `DeactivatePatientAction` (`app/Actions/Patient/DeactivatePatientAction.php`, namespace `App\Actions\Patient` — folder per entity sesuai CLAUDE.md): `handle(Patient $patient)` → `$patient->delete()` (trigger SoftDeletes set `deleted_at`) + `LogAuditAction` event `patient.deactivated`, narasi "Menonaktifkan pasien {name}", `withProperties(['tenant_id' => …, 'old' => ['deleted_at' => null], 'new' => ['deleted_at' => now()]])` (konstitusi VI: update/deactivate sertakan old/new). Soft delete tunggal → tanpa `DB::transaction` (mirror `ArchiveServiceAction` yang tipis, bukan `RemoveUserAction` yang butuh transaction multi-write). Tanpa proteksi admin-terakhir (pasien tidak punya analog itu).
- Exception: bila soft delete melempar (mis. DB error), Action WAJIB `Log::error('Menonaktifkan pasien gagal', ['exception' => $e, 'patient_id' => …])` sebelum re-throw (konstitusi VI: "Setiap exception yang ditangkap WAJIB di-log"). MVP: soft delete single write jarang gagal — tidak ada try/catch yang menelan; bila Action membungkus dengan try/catch, wajib log. Tidak menelan exception diam-diam.
- Hard-delete permanen **tidak diekspos via API** (YAGNI — tidak ada UI/kebutuhan hapus permanen; nonaktifkan sudah cukup). `restrictOnDelete` di DB tetap dipasang sebagai pertahanan integritas bila ada operasi internal (artisan/manual) yang mencoba hard-delete — pemblokiran terjadi di level DB, bukan endpoint.

**Rationale**: FR-025 eksplisit "nonaktifkan (soft delete), bukan hapus permanen". Tidak ada FR minta endpoint hard-delete. Restrict tetap memberi jaminan FR-027 ("hard-delete pasien direferensi → diblokir") untuk path apa pun yang mencoba, tanpa endpoint baru. Action = unit kerja nonaktifkan reusable (soft delete + log) sesuai layering Controller→Action; DB write tidak di Controller.

**Alternatives ditolak**:
- Controller `$patient->delete()` langsung tanpa Action: melanggar konstitusi v1.1.0 + CLAUDE.md ("DB WAJIB via Action") dan VI (log via Action `activity()`).
- Endpoint `POST /patients/{patient}/deactivate` (mirror staff): staff pakai `deactivate` karena `destroy` di-exception untuk proteksi admin-terakhir. Pasien tidak punya proteksi serupa; `destroy` (DELETE) adalah idiom REST untuk nonaktifkan via soft delete — lebih sederhana, satu route.
- Endpoint `DELETE /patients/{id}/force` dengan try/catch `QueryException` → 409: YAGNI, tidak ada konsumen.
- Ubah `destroy` jadi hard-delete: melanggar FR-025.

**Verifikasi FR-027**: test assert `Patient::find($id)->forceDelete()` pada pasien dengan booking → melempar `QueryException` (restrict). Soft delete (`delete()` via Action) tidak memicu restrict (FK tetap, `deleted_at` terisi) → dibuktikan via test terpisah.

## R4 — DB writes via Action + activity log dengan withProperties (FR-029, konstitusi v1.1.0)

**Konteks**: `LogAuditAction` sudah ada (spatie/laravel-activitylog wrapper, signature `(action, subject, causer, context, description, tenant)` — `$context` dialirkan ke `withProperties`). Saat ini `PatientController::store/update` melakukan `Patient::create()`/`$patient->update()` **langsung di Controller** tanpa log. Konstitusi v1.1.0 (VI) + CLAUDE.md baru mewajibkan: (a) **Controller→Service→Action** — Controller WAJIB memanggil Service, dilarang langsung ke Action; Service orkestrasi & memanggil Action; DB write hanya di Action; (b) SEMUA Action ubah-data log via `activity()`/`LogAuditAction`, (c) `withProperties` WAJIB simpan semua atribut — create→full attributes, update→old+new, (d) exception yang ditangkap WAJIB `Log::error`. **Read exception**: Controller boleh langsung query read (list/detail via `InteractsWithDataTable`/Eloquent) tanpa lewat Service.

**Decision** — `PatientService` (orkestrasi) + tiga Action ubah-data (folder per entity `app/Actions/Patient/`, namespace `App\Actions\Patient`):

1. **`CreatePatientAction`** (`app/Actions/Patient/CreatePatientAction.php`):
   - `handle(array $attributes): Patient` → `Patient::create($attributes)` + `LogAuditAction('patient.created', $patient, auth()->user(), ['tenant_id' => …, 'attributes' => $patient->fresh()->getAttributes()], 'Membuat pasien {name}')`.
   - `withProperties` create → **full attributes** (konstitusi VI).
2. **`UpdatePatientAction`** (`app/Actions/Patient/UpdatePatientAction.php`):
   - `handle(Patient $patient, array $attributes): Patient` → capture `$old = $patient->getOriginal()` (atau `only([...])` field yang diubah) **sebelum** update, `$patient->update($attributes)` + `LogAuditAction('patient.updated', $patient->fresh(), auth()->user(), ['tenant_id' => …, 'old' => $old, 'new' => $patient->fresh()->only(array_keys($attributes))], 'Memperbarui pasien {name}')`.
   - `withProperties` update → **old + new** diff (konstitusi VI).
3. **`DeactivatePatientAction`** (`app/Actions/Patient/DeactivatePatientAction.php`) — lihat R3: `$patient->delete()` + `LogAuditAction('patient.deactivated', …, ['old' => ['deleted_at' => null], 'new' => ['deleted_at' => …]])`.

**`PatientService`** (`app/Services/PatientService.php`) — orkestrasi use case pasien, **tidak menyentuh DB langsung** (CLAUDE.md: "Service DILARANG menyentuh DB langsung ... WAJIB melalui Action"):
- `create(array $attributes): array` → panggil `CreatePatientAction` + deteksi duplikat phone (`Patient::where('phone',…)->where('id','!=',…)->first()` — read query, boleh di Service) → return `[$patient, $duplicate]`.
- `update(Patient $patient, array $attributes): array` → panggil `UpdatePatientAction` + deteksi duplikat → return `[$patient, $duplicate]`.
- `deactivate(Patient $patient): Patient` → panggil `DeactivatePatientAction`.

- `PatientController::store`: `authorize` → resolve `PatientRequest` → `app(PatientService::class)->create($request->validated())` → response 201 dengan `meta.duplicate_warning`/`duplicate_patient_id`. **Controller tidak menyentuh DB & tidak langsung ke Action** (CLAUDE.md).
- `PatientController::update`: `authorize` → `app(PatientService::class)->update($patient, $request->validated())` → response 200 + meta duplikat.
- `PatientController::destroy`: `authorize` → `app(PatientService::class)->deactivate($patient)` → response 200.
- `index`/`show`/`history`: **read exception** — Controller boleh query langsung (DataTable/pagination/`withTrashed`) tanpa Service (CLAUDE.md: "Exception untuk read: controller boleh langsung inject Repository / read-only interface").
- Validasi: **dilarang inline validation di Controller** — semua via `PatientRequest` (FormRequest). Duplicate detection bukan validasi (tidak menolak) → tetap boleh di Service, bukan FormRequest.
- Exception handling: Action single-write (tidak butuh `DB::transaction` multi-write). Bila Action/Service membungkus dengan try/catch, WAJIB `Log::error('deskripsi kontekstual', ['exception' => $e, 'patient_id' => …])` sebelum re-throw. MVP: tidak ada catch yang menelan — biarkan exception naik ke Laravel handler. Tidak menelan exception diam-diam.

**Rationale**: Konstitusi v1.1.0 (VI) + CLAUDE.md baru: "Controller WAJIB memanggil Service (tidak boleh langsung ke Action)"; "Service DILARANG menyentuh DB langsung ... WAJIB melalui Action"; "SEMUA Action WAJIB log activity via spatie"; "`withProperties` WAJIB menyimpan semua atribut". Tiga Action tipis masing-masing satu use case (SOLID single responsibility) + `PatientService` orkestrasi tipis (panggil Action + deteksi duplikat) = DRY (LogAuditAction satu pintu) + layering konsisten. Controller kembali ke peran: authorize + resolve FormRequest + panggil Service + response. Action boleh inject activity log (CLAUDE.md: "Action may inject: event dispatcher, activity log"). Folder per entity sesuai CLAUDE.md "Folder per entity".

**Alternatives ditolak**:
- Controller→Action langsung (tanpa Service): melanggar CLAUDE.md baru "Controller WAJIB memanggil Service, dilarang langsung ke Action". Meski use case pasien sederhana, layering WAJIB konsisten lintas modul.
- Log + DB write di Controller: melanggar konstitusi v1.1.0 (VI) "SEMUA Action WAJIB log" + CLAUDE.md "DB WAJIB via Action" + "Controller WAJIB via Service".
- spatie auto-log via `LogsActivity` trait: narasi robotik "updated Patient" — melanggar konstitusi VI (naratif) + `withProperties` old/new tidak terkontrol. Pakai `LogAuditAction` eksplisit.
- Satu `PatientAction` god-class create/update/deactivate: melanggar SOLID (banyak responsibility) + konstitusi I.
- Service `PatientService` menyentuh DB langsung (`Patient::create` di Service): melanggar CLAUDE.md "Service DILARANG menyentuh DB langsung". Service hanya orkestrasi panggil Action.

**Catatan narasi**: Gunakan `$patient->name`. Format: "Membuat pasien {name}", "Memperbarui pasien {name}", "Menonaktifkan pasien {name}".

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
- `patient-form.tsx`: tambah field `notes` (`FormTextarea`) + tambah `notes` ke `patientSchema` (`z.string().optional()`) + `patientDefaults` (`notes: ""`). Komponen form `FormInput/FormSelect/FormTextarea/FormDatePicker/FormSubmit/useForm` di `components/forms/` **sudah ada dan reusable** — `notes` pakai `FormTextarea` eksisting. **Tidak ada form komponen baru** (user input: reuse `components/forms/` eksisting, buat baru hanya bila belum tersedia — semua field tercover). `address` saat ini sudah pakai `FormTextarea`; `notes` reuse `FormTextarea` yang sama — bisa dibedakan via `name`/`label` prop, tidak perlu varian baru.
- `index.tsx`:
  - Tambah kolom `actions` → render `PatientActionsCell`. Komponen `DataTable`/toolbar/pagination di `components/datatable/` **reuse, tidak diubah** (user input).
  - Fix breadcrumb: item pertama `{ label: tenant, to: "/$tenant/clinic", params: { tenant } }` (pattern ProductsPage), item kedua `{ label: t("clinic.clinic") }` (no link), item ketiga `{ label: t("patient.title") }` (last, no link).
  - Row "Ubah" → `Link` ke `/$tenant/clinic/patients/$id/edit`. Row "Nonaktifkan" → AlertDialog confirm → `DELETE /patients/{id}`.
- `patient-actions-cell.tsx` (NEW, mirror `StaffActionsCell`): DropdownMenu "Ubah" (navigate edit) + "Nonaktifkan" (AlertDialog confirm → `apiDelete`). Karena "Ubah" adalah navigasi (bukan mutation), tidak perlu Dialog edit inline — pakai halaman edit eksisting. Lebih sederhana dari staff (staff pakai Dialog role inline karena hanya 1 field cepat).
- `edit.tsx`: tambah duplicate warning handling (sama dengan `new.tsx`): mutation `onSuccess` cek `meta.duplicate_warning` → tampilkan AlertDialog. Update BE juga deteksi duplikat (R4). Mutasi `PUT` tetap ke endpoint yang sama — perubahan hanya di BE (Controller→Action); FE tidak tahu layering Action.
- `history.tsx`: fix breadcrumb (pattern sama: `clinic` → `patient.title` link → nama pasien link ke detail → `history` last). Breadcrumb tampilkan nama pasien (fetch detail pasien via `useQuery` `show`, atau dari data history pertama — ambil dari query `show` terpisah agar punya nama walau history kosong).
- `new.tsx`: fix key reference `duplicate_warning` (sinkron R6).
- **FE tidak menyentuh layering Action** — perubahan BE (Controller→Action, R4) transparan via kontrak API yang sama. FE hanya berubah: tambah field `notes`, tambah actions column, duplicate warning di edit, fix breadcrumb.

**Rationale**: Halaman terpisah untuk 7 field = sesuai aturan form design (bukan over-engineering). Row-actions mirror pattern eksisting = konsistensi. Tidak ada form komponen baru (semua field tercover) = YAGNI + reuse eksisting (user input). Breadcrumb fix = bug fix (konstitusi V). Duplicate warning di edit = paritas dengan create.

**Alternatives ditolak**:
- Pindah form ke modal: 7 field > 5 → melanggar aturan form design CLAUDE.md.
- Buat `FormNotes`/`FormPhone` baru di `components/forms/`: `FormTextarea`/`FormInput` sudah handle. Tidak ada reuse value. YAGNI.
- Dialog edit inline di actions-cell (seperti staff role): pasien 7 field → halaman edit lebih sesuai; Dialog inline hanya untuk 1-2 field cepat.

## R8 — Testing strategy (delegasi zahiira)

Test yang ditulis oleh agent `zahiira` (Pest/PHPUnit feature+unit), sesuai konstitusi II (TDD):

1. **PatientController feature tests**:
   - admin dapat CRUD + nonaktifkan (store/update/destroy-softdelete). Verifikasi DB write terjadi via Action (bukan langsung di Controller) — test mengamati efek (patient tersimpan + audit log row) bukan implementasi.
   - doctor view + create (doctor `patient` rw per matriks? matriks: doctor `patient rw` — ya, doctor bisa CRUD pasien); therapist view only (`patient r`); cashier `patient rw` (matriks cashier `patient rw`). Verifikasi per matriks aktual.
   - index hanya pasien aktif (soft-delete tidak muncul).
   - duplicate phone: store/update dengan phone ganda → 201/200 + `meta.duplicate_warning=true`, tetap tersimpan.
   - tenant isolation: pasien tenant A tidak terlihat tenant B.
   - activity log tercatat untuk create/update/deactivate (assert `audit_logs` row + narasi mengandung nama + `properties` berisi `old`/`new` untuk update/deactivate dan `attributes`/full untuk create — konstitusi VI `withProperties` wajib).
   - history accessible untuk pasien nonaktif (`withTrashed`).
2. **Action unit tests** (konstitusi II: unit test untuk Action logic bisnis): `CreatePatientAction`/`UpdatePatientAction`/`DeactivatePatientAction` — masing-masing handle() menghasilkan perubahan DB + audit log dengan `withProperties` sesuai (create→full, update→old+new, deactivate→old+new). Bila Action menangkap exception, assert `Log::error` terpanggil (konstitusi VI).
3. **FK restrict test** (unit/integration): `Patient::find($id)->forceDelete()` dengan booking/transaksi/rekam medis ada → `QueryException`; tanpa referensi → sukses. Soft delete (`delete()` via Action) tidak memicu restrict.
4. **Soft-delete test**: nonaktifkan pasien → `deleted_at` terisi, tidak muncul di `index`, riwayat (`history`) tetap lengkap, relasi booking/rekam medis/transaksi tetap utuh.
5. **Validation test**: name/phone kosong → 422; birth_date masa depan → 422; gender invalid → 422.

Factory: `PatientFactory` (bila belum ada) pakai `BelongsToTenant` — create via relasi tenant.

## Ringkasan keputusan

| ID | Decision |
|----|----------|
| R1 | `Patient` +`SoftDeletes`; migration +`softDeletes()` + index `(tenant_id, deleted_at)`; Resource +`deleted_at` |
| R2 | Migration baru drop+recreate 3 FK (`bookings/medical_records/transactions.patient_id`) → `restrictOnDelete` |
| R3 | Route `destroy` = nonaktifkan via `PatientService::deactivate` → `DeactivatePatientAction` (`app/Actions/Patient/`, soft delete + `LogAuditAction` withProperties old/new); hard-delete tidak diekspos endpoint; restrict DB jadi penjaga FR-027. Controller→Service→Action, Controller tidak sentuh DB/langsung ke Action |
| R4 | Layering Controller→Service→Action: `PatientService` (orkestrasi, no DB) + `Create/Update/DeactivatePatientAction` (`app/Actions/Patient/`) masing-masing satu use case + `LogAuditAction` dengan `withProperties` (create→full attributes, update→old+new, deactivate→old+new), narasi "Membuat/Memperbarui/Menonaktifkan pasien {name}". Read (index/show/history) di Controller (read exception). Validasi via `PatientRequest` (no inline validation). Exception ditangkap → `Log::error`. Controller→Service→Action, DB write hanya di Action |
| R5 | `history`/`show` resolve `withTrashed` (TenantScope tetap aktif) → riwayat tetap dapat diakses walau nonaktif |
| R6 | Tambah/sinkron i18n key patient: duplicate_warning, history_empty, deactivate, deactivate_confirm, deactivated |
| R7 | FE: +field notes (`FormTextarea` reuse, tanpa form komponen baru), +actions column (`patient-actions-cell.tsx` mirror staff, `components/datatable/` reuse), duplicate warning di edit, fix breadcrumb index/history. Form 7 field = halaman terpisah. FE transparan terhadap layering Action BE |
| R8 | zahiira tulis: controller feature (CRUD+deactivate+duplicate+tenant isolation+history-nonaktif+withProperties), action unit (withProperties+exception log), FK restrict, soft-delete, validation tests. `PatientFactory` bila belum ada |

## Kepatuhan konstitusi v1.1.0 + CLAUDE.md baru

| Aspek baru | Cara dipenuhi |
|------------|---------------|
| VI: DB write WAJIB via Action (CLAUDE.md: "Service DILARANG menyentuh DB langsung ... setiap operasi yang menyentuh DB WAJIB melalui Action") | `Create/Update/DeactivatePatientAction` — DB write hanya di Action; Service orkestrasi (no DB); Controller→Service→Action (R4) |
| CLAUDE.md: Controller WAJIB memanggil Service, dilarang langsung ke Action | `PatientService` orkestrasi create/update/deactivate; Controller panggil Service bukan Action (R4). Read exception: index/show/history query langsung di Controller |
| VI: SEMUA Action ubah-data WAJIB log via `activity()`/`LogAuditAction` | Ketiga Action panggil `LogAuditAction` (R4) |
| VI: `withProperties` WAJIB simpan semua atribut — create→full, update→old+new | `CreatePatientAction`→full attributes; `UpdatePatientAction`/`DeactivatePatientAction`→old+new diff (R4) |
| VI: Exception ditangkap WAJIB `Log::error` | Action bila menangkap exception → `Log::error` sebelum re-throw; tidak menelan diam-diam (R4) |
| VI: Role dinamis WAJIB spatie/laravel-permission; exception role statik fixed boleh enum+Gate matrix **dengan `ponytail:`** | Patient pakai `ClinicPermission::MATRIX` + Gate `clinic.access` (role fixed admin/doctor/therapist/cashier, tidak CRUD-able runtime) — `ponytail:` exception (lihat data-model.md Permission). `SyncTenantClinicRolesAction` sudah sinkronkan ke spatie Role/Permission sebagai jembatan; saat role jadi dinamis → migrasi penuh ke spatie hasRole/can |
| CLAUDE.md: Folder per entity `app/Actions/<Entity>/` | `app/Actions/Patient/{Create,Update,Deactivate}PatientAction.php`, namespace `App\Actions\Patient` (R3, R4) |
| CLAUDE.md: Controller→Service→Action, Action tidak inject Service | `PatientService` orkestrasi (panggil Action, no DB) — Controller→Service→Action. Service tidak inject Service lain (pasien tidak butuh cross-cutting); Action tidak inject Service (R4) |
| CLAUDE.md: Dilarang inline validation di Controller, WAJIB via FormRequest | Semua validasi field pasien via `PatientRequest` (`app/Http/Requests`) — Controller tidak ada inline validation (R4) |

## Tidak butuh package baru

Semua kebutuhan tercover paket eksisting: spatie/laravel-activitylog (audit via `LogAuditAction`), spatie/laravel-permission (terpasang; patient pakai Gate matrix statik `ClinicPermission` + `SyncTenantClinicRolesAction` sinkron ke spatie Role/Permission — `ponytail:` exception konstitusi VI karena role fixed, bukan DB-driven/CRUD-able; migrasi penuh saat role jadi dinamis). FE: react-hook-form, zod, tanstack-query, shadcn primitives, hugeicons — semua ada. **Context7 tidak perlu dipanggil** — tidak ada library/SDK baru yang butuh dokumentasi terkini.
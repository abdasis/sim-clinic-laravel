# Research — Master Layanan Klinik (005-service-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

Status implementasi saat ini: fitur layanan **sudah ada sebagian** (ServiceController, Service model, ArchiveServiceAction, ServiceRequest, ServicePolicy, ServiceResource, ServiceStatus enum, migration `services`, route `apiResource`, FE halaman `services/index.tsx` + modal create `service-form-modal.tsx`). Spec ini adalah **revisi/penyempurnaan** terhadap AC yang belum terpenuhi, bukan greenfield.

## Temuan audit vs AC

| AC / FR | Status saat ini | Gap |
|---------|-----------------|-----|
| FR-011 price >= 0 | OK — `ServiceRequest` `gte:0`, FE `z.coerce.number().gte(0)` | tidak ada |
| FR-013 arsip via status=archived | OK — `ArchiveServiceAction::handle` set status | `ArchiveServiceAction` **tidak log activity** |
| FR-014 arsip tidak muncul di pilihan booking baru | **GAP** — `ServiceController::index` hanya filter `status` bila filter eksplisit dikirim; `BookingFormModal` fetch `/services?per_page=100` tanpa filter status → layanan arsip muncul di dropdown | perlu filter `active` default di index ATAU endpoint options khusus |
| FR-015 hard-delete direferensi → diblokir restrict | **GAP** — FK `bookings.service_id` = `cascadeOnDelete`, `treatment_records.service_id` & `transaction_items.service_id` = `nullOnDelete`. Tidak ada yang restrict. Juga `ServiceController::destroy` malah **mengarsipkan**, bukan hard-delete | perlu migration ubah FK + keputusan route destroy |
| FR-016 snapshot tidak tersinkron dari master | OK secara desain — `transaction_items.name/unit_price` & `treatment_records.service_name` ditulis sekali, tidak ada path update. **Tidak ada verifikasi/test** | perlu test assert immutability |
| FR-017 activity log "Mengarsipkan layanan {name}" | **GAP** — `ArchiveServiceAction` tidak panggil `LogAuditAction`. `LogAuditAction` sudah ada (spatie wrapper) | panggil `LogAuditAction` di archive + create + update |
| FR-018 tenant isolation | OK — `BelongsToTenant` + `TenantScope` | tidak ada |
| FR-019 search/sort/paginate | OK — `InteractsWithDataTable` | tidak ada |
| FR-020 breadcrumb | **BUG** — `ServicesPage` breadcrumb item pertama `to: "/$tenant/clinic/services"` (menunjuk diri sendiri), bukan `/clinic`. Pattern benar ada di `ProductsPage` (`to: "/$tenant/clinic"`) | perbaiki ke pattern ProductsPage |
| FE edit/archive action | **GAP** — hanya modal create. Tidak ada edit, tidak ada aksi arsip per-row | tambah row-actions (mirror `StaffActionsCell`) |

## R1 — FK restrictOnDelete: pendekatan migration

**Konteks**: 3 FK menunjuk `services`:
- `bookings.service_id` → `cascadeOnDelete` (salah — booking historis hilang bila layanan di-hard-delete)
- `treatment_records.service_id` (nullable) → `nullOnDelete`
- `transaction_items.service_id` (nullable) → `nullOnDelete`

AC spec: ketiganya `restrictOnDelete`.

**Decision**: Migration baru (`2026_08_14_*_restrict_service_foreign_keys`) drop + recreate ketiga FK dengan `restrictOnDelete`. PostgreSQL mendukung `ALTER TABLE ... DROP CONSTRAINT ... ADD CONSTRAINT ... FOREIGN KEY ... ON DELETE RESTRICT`. Laravel Blueprint: `dropForeign` lalu `foreign('service_id')->references('id')->on('services')->restrictOnDelete()`.

**Rationale**: `restrictOnDelete` memaksa integritas referensial di lapisan DB — hard-delete layanan yang masih direferensi diblokir DB terlepas dari path app. Ini pertahanan terakhir setelah Policy/app. Cocok dengan R6 (riwayat historis utuh).

**Alternatives ditolak**:
- App-only guard (Policy cek relasi): dapat dilewati oleh seed/job/bug, tidak ada DB guarantee.
- Biarkan `cascadeOnDelete`/`nullOnDelete`: melanggar AC FR-015 + R6 (cascade menghapus booking historis; null membuat relasi putus).
- `restrictOnDelete` hanya di bookings, null di sisanya: inkonsisten, snapshot `service_name` di treatment tetap utuh tapi FK putus → ambigu.

**Catatan nullable FK**: `treatment_records.service_id` & `transaction_items.service_id` nullable karena exclusive arc (product OR service). `restrictOnDelete` bekerja pada nilai non-null; baris dengan `service_id IS NULL` tidak terpengaruh (tidak ada constraint check). Aman.

## R2 — Route destroy: arsip vs hard-delete

**Konteks**: `ServiceController::destroy` saat ini memanggil `ArchiveServiceAction` (arsip, 200). `apiResource` mapping `DELETE /services/{service}` → `destroy`. AC spec menyebut "hard-delete layanan direferensi → diblokir restrict".

**Decision**: Pertahankan `destroy` = **arsip** (soft hide, FR-013). Hard-delete permanen **tidak diekspos via API** untuk MVP (YAGNI — tidak ada UI/kebutuhan hapus permanen; arsip sudah cukup). `restrictOnDelete` di DB tetap dipasang sebagai pertahanan integritas bila ada operasi internal (artisan/manual) yang mencoba hapus — pemblokiran terjadi di level DB, bukan endpoint.

**Rationale**: Spec FR-013 eksplisit "bukan hapus". Tidak ada FR yang minta endpoint hard-delete. Pasang restrict tetap memberi jaminan FR-015 ("hard-delete layanan direferensi → diblokir") untuk path apa pun yang mencoba, tanpa menambah endpoint baru.

**Alternatives ditolak**:
- Tambah endpoint `DELETE /services/{service}/force` dengan try/catch `QueryException` → 409: YAGNI, tidak ada konsumen. Tambah kompleksitas tanpa permintaan.
- Ubah `destroy` jadi hard-delete: melanggar FR-013.

**Verifikasi FR-015**: test assert bahwa `Service::delete()` pada layanan dengan booking ada → melempar `QueryException` (restrict). Ini bukan via endpoint, tapi via model langsung — membuktikan DB guard.

## R3 — FR-014: layanan arsip tidak muncul di pilihan booking baru

**Konteks**: `BookingFormModal` (dan `medical-records/new.tsx`, `pos/transaction-item-list.tsx`) fetch `/services` tanpa filter status. `ServiceController::index` hanya filter `status` bila `filter[status]` eksplisit.

**Decision**: `ServiceController::index` **default hanya `active`** kecuali filter `status` eksplisit dikirim (termasuk `archived` atau `all`). Logika:
```
if (filter[status] ada) where(status, filter[status])
else where(status, 'active')   // default hide arsip
```
Halaman master `ServicesPage` ingin lihat arsip → kirim `filter[status]=archived` (via faceted filter) atau `all`. Tambahkan faceted filter status di toolbar (komponen `DataTableFacetedFilter` sudah ada).

**Rationale**: Default hide arsip melindungi semua konsumen options (booking, medical record, POS) tanpa ubah tiap konsumen. Halaman master tetap bisa lihat arsip via filter eksplisit. Satu titik perubahan.

**Alternatives ditolak**:
- Endpoint terpisah `/services/active` untuk options: duplikasi route + resource, melanggar DRY.
- Filter di tiap konsumen FE (`rows.filter(r => r.status === 'active')`): client-side filter pada `per_page:100` bisa miss data paginasi; tidak robust.
- Query param `?active=1` baru: redundant dengan `filter[status]` yang sudah ada.

**Catatan**: `pos/transaction-item-list.tsx` & `medical-records/new.tsx` pakai `per_page:100` tanpa filter — dengan default active, otomatis hanya aktif. Tidak perlu ubah konsumen.

## R4 — Activity log wiring (FR-017)

**Konteks**: `LogAuditAction` sudah ada (spatie/laravel-activitylog wrapper, signature `(action, subject, causer, context, description, tenant)`). Saat ini `ArchiveServiceAction` tidak memanggilnya. `ServiceController::store/update` juga tidak log.

**Decision**:
- `ArchiveServiceAction::handle` inject `LogAuditAction`, panggil dengan `action: 'service.archived'`, `subject: $service`, `description: "Mengarsipkan layanan {name}"` (naratif, sesuai FR-017 & konstitusi VI).
- `ServiceController::store` → log `service.created` "Membuat layanan {name}".
- `ServiceController::update` → log `service.updated` "Memperbarui layanan {name}" (bila perlu diff field — MVP: narasi generik cukup; bila ada perubahan status dari active→archived via update form, log juga `service.archived`).

**Rationale**: Konstitusi VI mewajibkan setiap aksi ubah-data log naratif. `LogAuditAction` adalah satu pintu (DRY). Inject ke Action diizinkan (Action boleh inject event/log — lihat CLAUDE.md "Action may inject: event dispatcher, activity log").

**Alternatives ditolak**:
- spatie auto-log via model event (`activitylog` `LogsActivity` trait): terlalu generik, narasi robotik "updated Service" — melanggar konstitusi VI (naratif). Pakai `LogAuditAction` eksplisit.
- Log di Controller, bukan Action: `ArchiveServiceAction` adalah unit kerja arsip; log sebaiknya di Action agar tercatat walau dipanggil dari path lain. Tapi `store/update` di Controller acceptable (no Action for create/update saat ini — YAGNI buat Action hanya untuk log).

**Catatan narasi**: Gunakan `$service->name` di deskripsi. Format: `"Mengarsipkan layanan {name}"`, `"Membuat layanan {name}"`, `"Memperbarui layanan {name}"`.

## R5 — Snapshot immutability verification (FR-016)

**Konteks**: Snapshot di `transaction_items.name/unit_price` & `treatment_records.service_name` ditulis sekali saat create. Tidak ada path update snapshot. Saat ini tidak ada test yang membuktikan.

**Decision**: Test satu assert: buat transaction_item dengan service, lalu ubah `service.name` & `service.price` (& arsipkan), verifikasi `transaction_item.name/unit_price` & `treatment_record.service_name` **tidak berubah**. Ini memenuhi "verifikasi tidak ada path sync snapshot ke master" dari input spec.

**Rationale**: Test adalah bukti runnable (konstitusi II + CLAUDE.md "Non-trivial logic leaves ONE runnable check behind"). Tidak perlu kode produksi baru — desain sudah immutable; test hanya mengunci invariant.

**Alternatives ditolak**:
- Audit grep manual "cari semua path yang tulis `transaction_items.name`": rapuh, tidak runnable.
- CHECK constraint DB: snapshot bukan masalah constraint, masalah tidak adanya update path. Test cukup.

## R6 — FE: edit + archive row actions, perbaikan breadcrumb

**Konteks**:
- Modal create ada (`service-form-modal.tsx`). Edit belum ada. Arsip belum ada per-row.
- `StaffActionsCell` adalah pattern row-actions (DropdownMenu + Dialog edit + AlertDialog confirm) yang sudah ada — mirror ini.
- Breadcrumb `ServicesPage` buggy: item pertama `to: "/$tenant/clinic/services"` (self-link). Pattern benar: `ProductsPage` pakai `to: "/$tenant/clinic"`.
- Form fields: name, description, price, status = 4 field, tanpa logika kompleks → **modal** (sesuai aturan modal ≤5 field, CLAUDE.md). Tidak butuh halaman terpisah.
- Komponen form `FormInput/FormTextarea/FormSelect/FormSubmit/useForm` **sudah ada dan reusable** — tidak perlu buat form baru. **Tidak ada form baru yang perlu dibuat** di `components/forms/`.

**Decision**:
- Refactor `service-form-modal.tsx` → `service-form-dialog.tsx`: support mode **create + edit** (terima `service?: ServiceRow`, prefill defaultValues, PUT saat edit). Trigger dibuat terpisah (button "Tambah" di header untuk create; menu item "Ubah" per-row untuk edit).
- Tambah `service-actions-cell.tsx` (mirror `StaffActionsCell`): DropdownMenu dengan "Ubah" (buka form-dialog edit) + "Arsipkan" (AlertDialog confirm → `DELETE /services/{id}`).
- Tambah kolom aksi di `ServicesPage` table.
- Perbaiki breadcrumb `ServicesPage`: item pertama `to: "/$tenant/clinic"`, label `t("clinic.clinic")`, item terakhir `t("service.title")` (pattern `ProductsPage`).
- Tambah faceted filter status (`DataTableFacetedFilter`) di toolbar agar admin bisa lihat arsip.

**Rationale**: Modal reuse untuk create+edit = DRY. Row-actions mirror pattern eksisting = konsistensi. Tidak ada form komponen baru (semua field tercover) = YAGNI. Breadcrumb fix = bug fix sesuai konstitusi V (breadcrumb wajib, jalur benar).

**Alternatives ditolak**:
- Halaman edit terpisah (`services/$id/edit`): 4 field tanpa logika kompleks → modal cukup (aturan form design CLAUDE.md). Halaman terpisah = over-engineering.
- Buat `FormCurrency`/`FormStatusSelect` baru di `components/forms/`: `FormInput type=number` + `FormSelect` sudah handle. Tidak ada reuse value tambahan. YAGNI.
- Komponen `ServiceFormModal` dipakai untuk edit dengan trigger di luar: perlu expose open-state — lebih bersih jadikan dialog terbuka-control dengan props `open`/`onOpenChange` + `service`.

## R7 — i18n keys tambahan

**Konteks**: `lang/id/service.php` ada: title, name, description, price, status, add, created, updated, archived. `general.php` ada: save, cancel, edit, delete, actions, dll.

**Decision**: Tambah key di `service.php`:
- `edit` → "Ubah Layanan"
- `archive` → "Arsipkan"
- `archive_confirm` → "Arsipkan layanan ini? Layanan terarsip tidak muncul di pilihan booking baru, tetapi data lama tetap utuh."
- `description_label` (bila perlu, atau reuse `description`)

Tidak perlu key baru di `general.php` (`edit`, `cancel`, `actions`, `save` sudah ada).

**Rationale**: Semua teks UI via i18n (konstitusi V). Reuse general keys (DRY).

## R8 — Permission matrix: tidak ada perubahan

`ClinicPermission::MATRIX` sudah punya `service => 'rw'` untuk admin, `'r'` untuk doctor/therapist, tidak ada untuk cashier. Cocok dengan AC (admin CRUD+arsip; doctor/therapist view). **Tidak ada perubahan matriks**. `ServicePolicy` sudah delegasi ke Gate `clinic.access`. FE sidebar visibility sudah mirror matriks.

## R9 — Testing strategy (delegasi zahiira)

Test yang ditulis oleh agent `zahiira` (Pest/PHPUnit feature+unit), sesuai konstitusi II (TDD):

1. **ServiceController feature tests**:
   - admin dapat CRUD + arsip (store/update/destroy-archive).
   - doctor/therapist hanya view (403 pada create/update/destroy).
   - cashier 403 semua.
   - index default hanya active; filter `status=archived` tampilkan arsip.
   - tenant isolation: layanan tenant A tidak terlihat tenant B.
   - activity log tercatat untuk create/update/archive (assert `audit_logs` row + narasi mengandung nama).
2. **FK restrict test** (unit/integration): `Service::delete()` dengan booking ada → `QueryException`; tanpa referensi → sukses.
3. **Snapshot immutability test** (R5): ubah/arsipkan service → snapshot `transaction_items`/`treatment_records` tidak berubah.
4. **Validation test**: price negatif → 422; name kosong → 422.

Factory: `ServiceFactory` (bila belum ada) pakai `BelongsToTenant` — create via relasi tenant.

## R10 — Tidak butuh package baru

Semua kebutuhan tercover paket eksisting: spatie/laravel-activitylog (audit), spatie/laravel-permission (sudah terpasang untuk role dinamis, meski service pakai Gate matrix statik — acceptable per konstitusi VI exception karena `ClinicPermission` matrix statik fixed-role, beri konteks `ponytail:` tidak perlu karena sudah dicatat di spec 001 R2). FE: react-hook-form, zod, tanstack-query, shadcn primitives, hugeicons — semua ada. **Context7 tidak perlu dipanggil** — tidak ada library/SDK baru yang butuh dokumentasi terkini.

## Ringkasan keputusan

| ID | Decision |
|----|----------|
| R1 | Migration drop+recreate 3 FK → `restrictOnDelete` |
| R2 | `destroy` tetap arsip; hard-delete tidak diekspos endpoint; restrict DB jadi penjaga FR-015 |
| R3 | `index` default `active` kecuali filter `status` eksplisit |
| R4 | `LogAuditAction` di ArchiveServiceAction + store + update, narasi "Mengarsipkan/Membuat/Memperbarui layanan {name}" |
| R5 | Test assert snapshot immutability (bukan kode produksi baru) |
| R6 | Form-dialog create+edit reuse; `service-actions-cell.tsx` mirror `StaffActionsCell`; fix breadcrumb; faceted filter status. Tidak ada form komponen baru |
| R7 | Tambah i18n key: service.edit, archive, archive_confirm |
| R8 | Permission matrix tidak berubah |
| R9 | zahiira tulis: controller feature, FK restrict, snapshot immutability, validation tests |
| R10 | Tidak butuh package/library baru, Context7 skip |
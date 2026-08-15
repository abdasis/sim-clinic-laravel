# Research — Transaksi POS & Pembayaran (009-pos-transactions)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

Status implementasi: backend **sudah ada sebagian besar** (TransactionController, TransactionService, PayTransactionAction, CancelTransactionAction, TransactionRequest, PaymentRequest, TransactionResource, TransactionPolicy, InvoiceController+InvoiceService, PaymentController, 4 migration, routes, MATRIX permission `transaction`/`invoice` admin+cashier rw). Frontend **belum ada sama sekali** (sidebar mengarah ke `pos` & `pos/transactions` tapi tidak ada route file). Spec ini = revisi/penyempurnaan backend terhadap AC + frontend greenfield.

## Temuan audit vs AC

| AC / FR | Status saat ini | Gap |
|---------|-----------------|-----|
| FR-041 simpan transaksi + snapshot nama/harga | OK — `TransactionService::create` snapshot `name`/`unit_price` dari master | tidak ada |
| FR-042 invoice_number unik per tenant per hari, race-safe | **GAP** — `Transaction::generateInvoiceNumber()` pakai `count()+1` TANPA `lockForUpdate`/transaction isolation → dua kasir concurrent dapat hasilkan nomor sama (unik constraint melempar 500, bukan 422/retry) | perlu lockForUpdate dalam DB transaction |
| FR-043 subtotal = sum item.subtotal | OK — `TransactionService::create` hitung `array_sum` | tidak ada |
| FR-044 booking_id nullable, hanya bila booking done | **GAP** — `TransactionRequest` `booking_id` hanya `exists` , tidak cek `status=done` | perlu validasi booking done |
| FR-045 FK patient/cashier/booking restrictOnDelete | **SEPARI** — `cashier_id` sudah restrict (migration 033000); `patient_id` & `booking_id` masih `nullOnDelete` di migration awal | perlu migration baru ubah 2 FK |
| FR-046 paid_amount denormalized | **GAP** — kolom `paid_amount` belum ada di migration/model/fillable/casts | perlu migration + model edit |
| FR-047 payment_status diturunkan (3-state) | **GAP** — enum `PaymentStatus` hanya `unpaid`/`paid`; `PayTransactionAction` hanya set `paid` saat `totalPaid >= subtotal`, tidak set `partially_paid` | tambah enum case + logika turunan |
| FR-048 invoice issued_at, render dari relasi (R4) | OK — `InvoiceService::render` + `InvoiceController` view HTML; `issued_at` di tabel `invoices` | merge invoices YAGNI — lihat R5 (tunda) |
| FR-049 exclusive-arc product XOR service + qty>0 + stok cukup | **SEPARI** — qty `gt:0` OK, stok check di `productLine` OK; **exclusive-arc tidak dienforce** di FormRequest (`product_id` & `service_id` sama-sama nullable, bisa keduanya null/terisi) | perlu validasi XOR di FormRequest |
| FR-050 transaksi wajib pasien | **GAP** — `TransactionRequest` `patient_id` `nullable` (bisa tanpa pasien) | ubah `required` |
| FR-051 catat pembayaran (method/amount/paid_at, multi) | OK — `PaymentRequest` + `PayTransactionAction` + `PaymentController` | tidak ada |
| FR-052 kurangi stok (sold_pos) | OK — `TransactionService::create` panggil `StockService::adjust(SoldPos)` | tidak ada |
| FR-053 validasi stok cukup | OK — `productLine` abort 422 `pos.insufficient_stock` | tidak ada |
| FR-054 metode enum | OK — `PaymentMethod` enum + `PaymentRequest` | tidak ada |
| FR-055 paid_amount + payment_status atomik 3-state | **GAP** — `PayTransactionAction` update DB transaction OK, tapi `paid_amount` kolom tidak ada; status hanya `paid` (no `partially_paid`) | perlu paid_amount + logika 3-state |
| FR-056 snapshot immutable | OK — `transaction_items.name`/`unit_price` snapshot | tidak ada |
| FR-057 audit log naratif status lama→baru | **GAP KRITIS** — **tidak ada audit log sama sekali** di `TransactionService::create`, `PayTransactionAction`, `CancelTransactionAction` | tambah `LogAuditAction` di 3 titik |
| FR-058 pembatalan: cancelled_at + rollback stok | OK — `CancelTransactionAction` rollback stok + set `cancelled_at`; **tidak ada audit log**; tidak guard transaksi sudah batal | tambah audit + guard double-cancel |
| FR-058 soft-delete + index (tenant_id, deleted_at) | **GAP** — `transactions` belum soft-delete (`deleted_at` kolom tidak ada) | migration + `SoftDeletes` trait di model |
| FR-077 halaman kasir POS | **GAP** — FE belum ada | greenfield |
| FR-078 label i18n payment_status 3-state | **SEPARI** — `clinic.payment_status` hanya `unpaid`/`paid`; `partially_paid` belum ada | tambah key |
| FR-079 breadcrumb POS | **GAP** — FE belum ada | greenfield |

## R1 — Race-safe `generateInvoiceNumber` (FR-042)

**Konteks**: `Transaction::generateInvoiceNumber()` saat ini:
```
$countToday = static::withoutGlobalScope(...)->where('tenant_id', $tenantId)
    ->whereDate('created_at', now()->toDateString())->count();
$sequence = str_pad($countToday + 1, 4, ...);
```
`count()` tanpa lock → dua request concurrent dapat baca count sama (mis. 3) → keduanya generate `INV-...-0004` → insert kedua melempar unique constraint violation → HTTP 500 (bukan ditangani). `TransactionService::create` sudah bungkus dalam `DB::transaction`, tapi `count()` di dalam transaction default isolation (READ COMMITTED di PostgreSQL) tetap tidak lock baris yang belum ada — race tetap mungkin.

**Decision**: Pindahkan `generateInvoiceNumber` ke dalam `DB::transaction` (sudah) + pakai `lockForUpdate` pada query count agar concurrent transaction menunggu. Dua pendekatan:

- **(A) `lockForUpdate` pada count hari ini**: `static::withoutGlobalScope(...)->where('tenant_id', $tenantId)->whereDate('created_at', today)->lockForUpdate()->count()`. PostgreSQL: `SELECT ... FOR UPDATE` pada baris existing akan lock; tapi bila **belum ada baris hari itu** (transaksi pertama hari ini), tidak ada baris di-lock → dua insert pertama tetap race. **Tidak fully safe untuk kasus "first of day".**
- **(B) Advisory lock per tenant per hari (preferred)**: `DB::statement('SELECT pg_advisory_xact_lock(?)', [hash])` dalam transaction — lock global per (tenant, tanggal). Key stabil: mis. `crc32($tenantId . '-' . today()->toDateString())` atau gunakan `tenant_id * 100 + dayofyear`. Sederhana, fully race-safe walau first-of-day. PostgreSQL-only (SQLite test tidak punya advisory lock — fallback: pada SQLite, race tidak diuji / pakai `lockForUpdate` count + unique constraint catch-retry).
- **(C) Sequence table per tenant per hari**: tabel `invoice_sequences(tenant_id, date, last_seq)` dengan `lockForUpdate` pada baris sequence. Paling robust tapi tabel baru (YAGNI untuk MVP, kompleksitas cleanup sequence lama).

**Pilih (B) advisory lock** untuk PostgreSQL produksi. Untuk test SQLite: bungkus `generateInvoiceNumber` agar skip advisory lock pada driver SQLite (tidak didukung), andalkan `lockForUpdate()->count()` + unique constraint sebagai fallback. Tandai `ponytail: sequence table (C) add bila advisory lock terbukti tidak cukup di skala tinggi`.

**Rationale**: Advisory lock PostgreSQL = primitive native stdlib DB, fully race-safe, no new table, satu baris. Unique constraint `(tenant_id, invoice_number)` tetap jadi jaring terakhir (bila race lolot, insert melempar — tangani dengan retry 1x di Service). AC FR-042: "unik walau concurrent" terpenuhi.

**Alternatives ditolak**:
- UUID acak: tidak berformat `INV-YYYYMMDD-XXXX` (AC eksplisit format).
- App-level counter di cache (Redis): tidak ada Redis di stack MVP; race cache vs DB.
- Hanya andalkan unique constraint + catch + retry: workable tapi 500 di race pertama sebelum retry — UX buruk, bukan "unik walau concurrent" yang bersih.

## R2 — `paid_amount` denormalized + `payment_status` 3-state (FR-046, FR-047, FR-055)

**Konteks**: `paid_amount` belum ada kolom. `PaymentStatus` enum hanya `unpaid`/`paid`. `PayTransactionAction` hitung `$totalPaid = payments()->sum('amount')` lalu set `paid` bila `>= subtotal` — tidak ada state parsial.

**Decision**:
- Migration baru: tambah `paid_amount decimal(12,2) not null default 0` ke `transactions`.
- `PaymentStatus` enum: tambah `case PartiallyPaid = 'partially_paid'`. Update migration enum column? Migration awal pakai `$table->enum('payment_status', ['unpaid','paid'])` — perlu alter enum di PostgreSQL untuk sertakan `partially_paid` (skip SQLite: SQLite simpan sebagai string, tidak ada enum type). Migration baru alter: `DB::statement("ALTER TABLE transactions ALTER COLUMN payment_status TYPE varchar(20)")` atau drop+recreate column — pilih alter ke `varchar` dengan app-level enum (lebih portabel, hindari PostgreSQL enum type migration pain). `ponytail: pertahankan DB enum type bila konsistensi tipe kritikal`.
- `PayTransactionAction::handle`: set `paid_amount += $data['amount']` (atau recompute `paid_amount = payments()->sum('amount')` dalam transaction), lalu turunkan status:
  - `paid` bila `paid_amount >= subtotal`
  - `partially_paid` bila `0 < paid_amount < subtotal`
  - `unpaid` bila `paid_amount == 0` (edge: tidak terjadi saat tambah payment positif, tapi pertahankan untuk rollback future).
- `Transaction` model: tambah `paid_amount` ke fillable + cast `decimal:2`.

**Rationale**: `paid_amount` denormalized = query laporan omzet (FR-070) tanpa SUM relasi (Anomali normalisasi intensional, dijaga `PayTransactionAction` dalam DB transaction). 3-state = `partially_paid` dikenali (split payment, FR-055) → laporan tidak menyesatkan.

**Alternatives ditolak**:
- Hitung `paid_amount` on-the-fly via accessor: N+1/SUM per query laporan — kalah performa.
- Status `partially_paid` di-compute di FE saja: laporan BE (FR-070) butuh state persisten.

## R3 — FK `patient_id` + `booking_id` restrictOnDelete (FR-045)

**Konteks**: Migration awal buat `patient_id` + `booking_id` `nullOnDelete`. AC FR-045: keduanya `restrictOnDelete`. `cashier_id` sudah restrict (033000).

**Decision**: Migration baru `2026_08_14_*_change_transactions_patient_booking_foreign_keys_to_restrict` — drop + recreate `patient_id` & `booking_id` FK dengan `restrictOnDelete`. Sama persis pola 033000: skip SQLite (`dropForeign` tidak didukung), PostgreSQL produksi RESTRICT. `tenant_id` tetap `cascadeOnDelete`.

**Rationale**: `restrictOnDelete` memaksa integritas — hard-delete pasien/booking yang masih direferensi transaksi diblokir DB. Pasien di-nonaktifkan (soft spec 006), booking pakai status `cancelled` (tidak hard-delete) — restrict konsisten. Riwayat finansial utuh (R6 spirit).

**Alternatives ditolak**:
- `nullOnDelete` (saat ini): hard-delete pasien → transaksi `patient_id` null → jejak pasien hilang dari laporan.
- App-only guard: dapat dilewati seed/job/bug.

## R4 — Audit log naratif (FR-057) — gap kritis

**Konteks**: **Tidak ada audit log** di `TransactionService::create`, `PayTransactionAction`, `CancelTransactionAction`. Konstitusi VI WAJIB setiap Action ubah-data log via `LogAuditAction` (spatie wrapper) naratif + `withProperties` (create full attributes, update old/new).

**Decision**: Tambah `LogAuditAction` di 3 titik:
- `TransactionService::create` (setelah transaction+items+invoice tersimpan): event `transaction.created`, narasi "Mencatat transaksi {invoice_number} untuk {pasien} — {subtotal}.", properties `attributes` (full transaction + items snapshot) + `tenant_id`.
- `PayTransactionAction::handle` (dalam DB transaction, setelah update): event `transaction.payment_recorded`, narasi "Mencatat pembayaran transaksi {invoice_number} — status berubah dari '{lama}' ke '{baru}'." (FR-057 eksplisit), properties `old: {paid_amount, payment_status}`, `new: {paid_amount, payment_status}` + `tenant_id`. Tangkap status lama **sebelum** update.
- `CancelTransactionAction::handle` (dalam DB transaction): event `transaction.cancelled`, narasi "Membatalkan transaksi {invoice_number} — stok produk dikembalikan.", properties `old: {cancelled_at: null, ...}`, `new: {cancelled_at}` + `tenant_id`.

Causer: auth user (auto via `LogAuditAction`). `withProperties` WAJIB old/new untuk update (payment, cancel), full attributes untuk create.

**Rationale**: Konstitusi VI non-negotiable. Audit finansial penting untuk forensik/kepatuhan. Narasi status lama→baru (FR-057) persis seperti AC.

**Alternatives ditolak**:
- Hanya log create (skip payment/cancel): laporan audit tidak lengkap — pelanggaran konstitusi.
- Log di Controller: bukan tempatnya — Controller orkestrasi HTTP, audit = tanggung jawab Action/unit kerja (CLAUDE.md layering).

## R5 — Merge `invoices` ke `transactions` (Anomali 1:1 YAGNI) — DITUNDA

**Konteks**: `docs/normalization/README.md` rekomendasi: tabel `invoices` nyaris hanya `transaction_id` + `issued_at` — kandidat merge ke `transactions` (BCNF pure). AC spec FR-048: "default MVP: merge bila tidak ada kebutuhan nomor invoice terpisah/multi-cetak."

**Decision**: **Pertahankan tabel `invoices` untuk fitur ini (tidak merge sekarang).** Alasan: `InvoiceService::render` & `InvoiceController::show` sudah pakai relasi `$transaction->invoice` + `issued_at`; merge = pindah `issued_at` ke `transactions` + refactor `InvoiceService` + drop tabel — refactor di luar scope AC POS (fokus transaksi/pembayaran/FE). Tidak ada kolom baru di `invoices`. `ponytail: merge issued_at ke transactions saat butuh nomor invoice terpisah/multi-cetak/status cetak — atau saat refactor normalisasi berikutnya`. Catat keputusan di `docs/normalization/README.md` follow-up (di luar scope task).

**Rationale**: YAGNI — fitur ini tidak butuh merge; merge = risiko refactor untuk nilai nol saat ini. Tabel sudah berfungsi. Konstitusi IV: kode paling baik adalah kode yang tidak pernah ditulis ulang tanpa kebutuhan.

**Alternatives ditolak**:
- Merge sekarang: scope creep, risiko regressi InvoiceService/Controller, tidak ada AC yang menuntut.
- Drop tabel tanpa merge: hilang `issued_at` + relasi → InvoiceService rusak.

## R6 — `TransactionRequest`: exclusive-arc + patient required + booking done (FR-049, FR-050, FR-044)

**Konteks**: `TransactionRequest` saat ini: `patient_id` nullable, `booking_id` nullable exists, items `product_id`/`service_id` sama-sama nullable (tidak enforce XOR), qty `gt:0` OK.

**Decision**:
- `patient_id`: ubah `required|exists:patients,id` (FR-050 — transaksi wajib pasien).
- `booking_id`: `nullable|exists:bookings,id` + `withValidator` cek bila diisi → booking `status === Done` (FR-044). Bila booking belum `done` → 422 pada `booking_id` "Hanya booking yang sudah selesai (done) dapat ditautkan ke transaksi." (`pos.booking_done_only`).
- items: `required|array|min:1`, tiap item `qty` `required|integer|gt:0`, dan exclusive-arc via `withValidator`: untuk tiap item, tepat satu dari `product_id`/`service_id` terisi → bila keduanya null atau keduanya terisi → 422 pada `items.{i}` "Item harus produk atau layanan, tidak keduanya." (`pos.items_exclusive_arc`).
- `service_id`/`product_id` tetap `nullable|exists` (XOR di withValidator, bukan rule tunggal).

**Rationale**: Exclusive-arc = Anomali #1 (app-level enforcement di MVP, CHECK constraint DB ditunda `ponytail`). Booking done link = FR-033/FR-044. Patient required = FR-050.

**Alternatives ditolak**:
- CHECK constraint DB untuk XOR: Anomali #1 rekomendasi preferred, tapi PostgreSQL CHECK `(product_id IS NULL) <> (service_id IS NULL)` butuh migration alter + skip SQLite — `ponytail: add saat audit integritas berikutnya`. App-level (FormRequest) cukup untuk MVP + UX error per-field.
- Hanya validasi `service_id` atau `product_id` required (tanpa XOR): tidak handle kasus keduanya terisi.

## R7 — Soft-delete transaksi (FR-058 bagian soft-delete)

**Konteks**: `transactions` belum soft-delete. AC: transaksi finansial tidak hard-delete; soft-delete (`deleted_at`) untuk sembunyi dari daftar aktif, record tetap audit. Index `(tenant_id, deleted_at)`.

**Decision**:
- Migration `2026_08_14_*_add_paid_amount_soft_delete_to_transactions`: tambah `deleted_at timestamp nullable` + `index(tenant_id, deleted_at)` (gabung dengan R2 `paid_amount` di satu migration).
- `Transaction` model: pakai `SoftDeletes` trait, `$fillable` tambah `paid_amount`, `casts` tambah `deleted_at => datetime`.
- `TransactionController::index`: query otomatis exclude soft-deleted via `SoftDeletes` global scope (Eloquent default). Tambah route `DELETE /transactions/{transaction}` → `destroy` → `$transaction->delete()` (soft) + audit log `transaction.deleted`. `TransactionPolicy::delete` sudah `transaction.manage`.
- Pembatalan (`cancelled_at`) vs soft-delete (`deleted_at`) = dua konsep berbeda: cancel = transaksi batal + rollback stok (masih tampil di daftar dengan badge batal); soft-delete = sembunyi dari daftar aktif (record tetap audit). Soft-delete hanya pada transaksi sudah batal atau skema koreksi data, bukan hapus finansial.

**Rationale**: Eloquent `SoftDeletes` = native, stdlib platform. Index `(tenant_id, deleted_at)` = query daftar aktif cepat. Tidak hard-delete = audit finansial utuh (konstitusi + AC SC-019).

**Alternatives ditolak**:
- Hard-delete: melanggar AC SC-019 (transaksi finansial tetap ada).
- Tanpa soft-delete (hanya cancel): tidak ada cara sembunyi dari daftar aktif (AC FR-058 eksplisit soft-delete).

## R8 — Frontend kasir POS + riwayat + detail (FR-077, FR-078, FR-079) — greenfield

**Konteks**: FE POS belum ada. Sidebar `route.tsx` sudah konfigur `pos` (admin+cashier) dengan children `pos` (kasir) + `pos/transactions` (riwayat). Komponen reusable ada: `components/datatable/*` (DataTable, toolbar, pagination, faceted filter, column-header, view-options), `components/forms/*` (FormInput, FormSelect, FormDatePicker, FormTextarea, FormSubmit, useForm, applyServerErrors). `useFieldArray` sudah dipakai di `medical-records/new.tsx` (preseden). UI primitives: `badge`, `dialog`, `card`, `table`, `separator`, `command`.

**Decision**:
- **`pos/index.tsx`** — halaman kasir POS (halaman terpisah, bukan modal — >5 field + logika line-item dinamis per aturan form design CLAUDE.md). Komposisi: pilih pasien (`FormSelect`, options dari `GET /patients`), pilih booking done opsional (`FormSelect`, options dari booking status done — endpoint? `GET /bookings?filter[status]=done` sudah ada), line items dinamis (`FormRepeatableItems` baru — useFieldArray, tiap row: select item [layanan/produk], qty, computed subtotal), ringkasan subtotal, submit `POST /transactions`. Toast sukses + invalidasi query. Breadcrumb "Beranda Klinik > Kasir POS".
- **`pos/transactions/index.tsx`** — riwayat transaksi, `DataTable` + `useDataTable` (mirror pattern `products/index.tsx`), kolom: invoice_number, patient_name, subtotal, paid_amount, sisa_bayar (subtotal - paid_amount), badge `payment_status` 3-state (label i18n `clinic.payment_status.*`), created_at, aksi (lihat/bayar/batal). Faceted filter `payment_status`. Breadcrumb "Beranda Klinik > Transaksi".
- **`pos/transactions/$id/index.tsx`** — detail: items, payments history, sisa bayar, badge status, aksi "Catat Pembayaran" (buka `payment-dialog`), "Batalkan" (confirm alert), "Cetak Invoice" (link `GET /transactions/{id}/invoice` → buka tab view HTML). Breadcrumb "Beranda Klinik > Transaksi > {invoice_number}".
- **`components/forms/form-repeatable-items.tsx`** — reusable line-item array: props `control`, `name`, `options` (item list layanan+produk), `onSubtotalChange`. Render rows (select item + qty input + computed subtotal + remove button) + "Tambah Item" button. Pakai `useFieldArray`. ≤300 baris. Disimpan di `components/forms/` per instruksi user (reusable, potensi reuse edit transaksi/treatment).
- **`pos/components/transaction-form.tsx`** — form kasir (gunakan `FormRepeatableItems` + `FormSelect` pasien/booking + `FormSubmit`), submit via `apiPost`. ≤300 baris.
- **`pos/components/payment-dialog.tsx`** — dialog catat pembayaran (`FormSelect` method, `FormInput` amount, `FormDatePicker` paid_at, `FormSubmit`) — 3 field → modal/dialog acceptable (≤5 field, logika sederhana). Submit `POST /transactions/{id}/payments`.
- **i18n** (`lang/id/pos.php` + `clinic.php`): tambah `partially_paid` label, `balance_due` (Sisa Bayar), `booking_done_only`, `items_exclusive_arc`, `cancel`, `cancel_confirm`, `print_invoice`, `pay` (Catat Pembayaran), `payment_recorded` (audit — tidak tampil FE, untuk BE log). FE mirror via `useTrans`.

**Rationale**: Halaman terpisah untuk kasir = sesuai aturan form design (line-item dinamis = logika kompleks). Reuse semua komponen form/datatable eksisting. Satu komponen form baru (`FormRepeatableItems`) reusable — bukan abstraksi prematur (line-item array berulang adalah pattern nyata, ≥1 konsumen + potensi reuse). Badge 3-state + sisa bayar = AC eksplisit. Breadcrumb = konstitusi V.

**Alternatives ditolak**:
- Kasir POS sebagai modal: >5 field + line-item dinamis → melanggar aturan form design (modal ≤5 field tanpa logika kompleks).
- Buat `FormSelectAsync` baru untuk pasien/booking: `FormSelect` + `useQuery` options di page cukup — tidak perlu komponen form baru (YAGNI). Options di-load di page, dilewatkan ke `FormSelect` sebagai props.
- Tanpa `FormRepeatableItems` (inline useFieldArray di page): page >300 baris, tidak reusable. Extract = DRY + bounded size.

## R9 — Permission matrix: tidak ada perubahan (R10 mirror)

`SyncTenantClinicRolesAction::MATRIX` sudah punya `transaction` rw (admin, cashier) + `invoice` rw (admin, cashier). Doctor/therapist tidak punya modul transaction → 403. `TransactionPolicy` delegasi `transaction.view`/`transaction.manage`. FE sidebar `pos` roles `["admin","cashier"]`. **Tidak ada perubahan matriks** — cocok AC (kasir+admin kelola POS). Konstitusi VI: role dinamis via spatie ✅.

## R10 — Tidak butuh package baru, Context7 tidak perlu

Semua kebutuhan tercover paket eksisting: spatie/laravel-activitylog, spatie/laravel-permission, react-hook-form (`useFieldArray` sudah dipakai), zod, tanstack-query, shadcn primitives, date-fns. PostgreSQL advisory lock = native DB feature (bukan package). **Context7 tidak perlu dipanggil** — tidak ada library/SDK baru. Tidak ada komponen form baru selain `FormRepeatableItems` (line-item array, tercover `useFieldArray` native).

## R11 — Testing strategy (delegasi zahiira)

Test ditulis agent `zahiira` (Pest/PHPUnit feature+unit), konstitusi II (TDD), setelah BE authoring (ammar):

1. **TransactionController feature tests**:
   - admin/cashier bisa create transaksi (pasien + items layanan+produk) → 201, invoice_number format `INV-YYYYMMDD-XXXX`, subtotal benar, items snapshot.
   - exclusive-arc: item dengan keduanya null → 422; keduanya terisi → 422 (FR-049).
   - patient_id null → 422 (FR-050).
   - booking_id booking belum done → 422 (FR-044); booking done → 201 link.
   - insufficient stock → 422 (FR-053).
   - tenant isolation: transaksi tenant A tidak terlihat tenant B.
2. **Race invoice_number**: dua transaksi concurrent (proses/test parallel atau `DB::transaction` bersamaan) → nomor berbeda berurutan, tidak duplikasi (FR-042, SC-015). PostgreSQL test bila memungkinkan; SQLite fallback: andalkan unique constraint + assert tidak ada duplikat.
3. **PayTransactionAction**: pembayaran < subtotal → `partially_paid` + `paid_amount` benar; pembayaran sisa → `paid` (FR-055). Overpaid → peringatan, status `paid`. Audit log naratif "Mencatat pembayaran … status {lama}→{baru}" (FR-057).
4. **CancelTransactionAction**: rollback stok produk + `cancelled_at` + audit log. Double-cancel guard.
5. **Soft-delete**: `DELETE /transactions/{id}` → soft-delete (tidak muncul di index, record tetap di DB) (FR-058).
6. **FK restrict**: hard-delete patient/booking direferensi → blocked (PostgreSQL; SQLite skip — `ponytail`).
7. **Audit log**: create/payment/cancel/delete tercatat naratif + withProperties (old/new untuk payment+cancel, full untuk create) (FR-057).
8. **Factory**: `TransactionFactory`/`TransactionItemFactory`/`PaymentFactory` (bila belum ada) — `BelongsToTenant`, create via relasi.

## R12 — i18n keys tambahan

`lang/id/pos.php` tambah: `balance_due` (Sisa Bayar), `booking_done_only` (Hanya booking yang sudah selesai dapat ditautkan.), `items_exclusive_arc` (Item harus produk atau layanan, tidak keduanya.), `cancel` (Batalkan), `cancel_confirm` (Batalkan transaksi ini? Stok produk akan dikembalikan.), `print_invoice` (Cetak Invoice), `pay` (Catat Pembayaran), `edit` (Ubah Transaksi — bila perlu), `deleted` (Transaksi diarsipkan.).
`lang/id/clinic.php` `payment_status` tambah: `'partially_paid' => 'Dibayar Sebagian'`.
BE log narasi (audit) pakai key tersendiri di `pos.php` atau hardcode naratif di Action (preseden booking) — pilih hardcode naratif di Action (konsisten `ChangeBookingStatusAction`).

## Ringkasan keputusan

| ID | Decision |
|----|----------|
| R1 | `generateInvoiceNumber` race-safe via PostgreSQL advisory lock per (tenant, hari) dalam DB transaction; SQLite fallback `lockForUpdate` count + unique constraint; `ponytail: sequence table add bila perlu` |
| R2 | Migration tambah `paid_amount decimal default 0` + alter `payment_status` ke varchar (support `partially_paid`); `PayTransactionAction` set `paid_amount` + turunkan status 3-state atomik |
| R3 | Migration ubah FK `patient_id` + `booking_id` → `restrictOnDelete` (skip SQLite, preseden 033000) |
| R4 | Audit log `LogAuditAction` di 3 titik: create (`transaction.created`), payment (`transaction.payment_recorded` narasi status lama→baru), cancel (`transaction.cancelled`) |
| R5 | Merge `invoices` DITUNDA (YAGNI) — pertahankan tabel, `ponytail: merge saat butuh` |
| R6 | `TransactionRequest`: `patient_id` required; `booking_id` harus booking done; exclusive-arc product XOR service via withValidator |
| R7 | Soft-delete `transactions` (`deleted_at` + index `(tenant_id, deleted_at)`) via migration + `SoftDeletes` trait + `destroy` route + audit |
| R8 | FE greenfield: `pos/index.tsx` (kasir, halaman terpisah + `FormRepeatableItems` baru reusable), `pos/transactions/index.tsx` (DataTable + badge 3-state + sisa bayar), `pos/transactions/$id/index.tsx` (detail + payment-dialog + cancel + cetak). Reuse `components/datatable/` + `components/forms/`; `FormRepeatableItems` di `components/forms/` |
| R9 | Permission MATRIX tidak berubah (transaction/invoice admin+cashier rw) |
| R10 | Tidak butuh package/library baru, Context7 skip |
| R11 | zahiira tulis: feature (create, exclusive-arc, patient required, booking done, stock, tenant isolation), race invoice, payment 3-state, cancel rollback+audit, soft-delete, FK restrict, audit log, factory |
| R12 | i18n: `pos.php` tambah balance_due/booking_done_only/items_exclusive_arc/cancel/cancel_confirm/print_invoice/pay/deleted; `clinic.payment_status.partially_paid` |
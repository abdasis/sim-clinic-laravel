# Research — Transaksi POS & Pembayaran Klinik (008-transactions-pos)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

Fase riset menyelesaikan semua titik ambigu teknis. Sumber kebenaran data model: `docs/erd/transactions.md`, `docs/erd/payments.md`, `docs/erd/transaction_items.md`, `docs/erd/invoices.md`, `docs/erd/README.md`, `docs/normalization/README.md`, `docs/normalization/workflow.md` langkah 9. Eksplorasi status implementasi via agent.

## R1 — Status eksisting modul transaksi (BE)

**Decision**: Modul transaksi sebagian besar sudah terimplementasi. Sudah ada: migration `transactions` (tenant_id, patient_id nullable+nullOnDelete, booking_id nullable+nullOnDelete, cashier_id restrict via migration kedua [skip SQLite], invoice_number, subtotal, payment_status 2-state `unpaid`/`paid`, cancelled_at, unique(tenant_id,invoice_number), index(tenant_id,payment_status,created_at)), migration `payments` (transaction_id cascade), migration `transaction_items` (product_id/service_id nullable+nullOnDelete, indexes), model `Transaction` (BelongsToTenant, cast PaymentStatus, relasi items/payments/invoice, `generateInvoiceNumber()` count()+1 TANPA lockForUpdate), model `Payment`+`TransactionItem`, enum `PaymentStatus` (2-state)+`PaymentMethod` (4-state), `TransactionController` (index/store/show/cancel via TransactionService+CancelTransactionAction, no update/destroy), `TransactionService::create()` (DB transaction, buildLines snapshot, stock check+adjust sold_pos, buat invoice row issued_at), `PayTransactionAction` (sum payments → set paid bila >= subtotal, tidak set partially_paid), `CancelTransactionAction` (rollback stok, set cancelled_at, tanpa guard status), `TransactionRequest`+`PaymentRequest`, `TransactionResource` (tidak expose paid_amount), `InvoiceService`+`InvoiceController`+`Invoice` model+tabel terpisah (issued_at di invoices), `LogAuditAction::handle(action, subject, causer, context, description, tenant)`. Route: `transactions` apiResource only index/store/show + `payments` + `cancel` + `invoice`.

**Rationale**: Spec 008 = revisi/penyempurnaan, bukan greenfield. Reuse mayoritas, tutup gap di bawah.

**Alternatives considered**: Bangun ulang — ditolak (YAGNI, duplikasi, break invoice print existing).

## R2 — `paid_amount` kolom denormalized (FR-079, revisi input)

**Decision**: Migration tambah kolom `paid_amount decimal(12,2) default 0 not null` ke `transactions`. Model: tambah ke `$fillable` + cast `decimal:2`. `PayTransactionAction` update `paid_amount += amount` dalam DB transaction bersama payment create + set status. `TransactionResource` expose `paid_amount`. Default 0 konsisten ERD `transactions.md`.

**Rationale**: Query laporan omzet (FR-070) butuh status lunas/parsial tanpa SUM relasi `payments` tiap query. Denormalized intensional (normalisasi README §denormalisasi, `transactions.paid_amount` = `SUM(payments.amount)`). Jaga-konsistensi: `PayTransactionAction` sync dalam DB transaction. `ponytail: reconcile dari sum(payments) add saat drift terdeteksi`.

**Alternatives considered**: Hitung `sum(payments)` tiap query — ditolak (N+1/lambat di laporan, melanggar justifikasi denormalisasi ERD).

## R3 — `payment_status` 3-state `partially_paid` (FR-055, revisi input)

**Decision**: Enum `PaymentStatus` tambah `PartiallyPaid = 'partially_paid'`. Migration: alter enum column `transactions.payment_status` tambah value `partially_paid` (PostgreSQL `ALTER TYPE` / SQLite tidak dukung alter enum — lihat R8 migration strategy). `PayTransactionAction` logic: set `paid` bila `paid_amount >= subtotal`, `partially_paid` bila `0 < paid_amount < subtotal`, `unpaid` bila 0. Lock row transaction `lockForUpdate` sebelum update untuk hindari race dua pembayaran konkuren. i18n: tambah `clinic.payment_status.partially_paid` di `lang/id/clinic.php`.

**Rationale**: Split payment (cicilan) lazim di klinik. State `partially_paid` mencegah laporan omzet menyesatkan (transaksi cicilan tampak unpaid). ERD `transactions.md` + `payments.md` FR-055 eksplisit 3-state.

**Alternatives considered**: Tetap 2-state + flag `is_partial` — ditolak (enum 3-state lebih jelas, cocok badge 3-state FE).

## R4 — `invoice_number` race fix `lockForUpdate` (FR-077, revisi input)

**Decision**: `generateInvoiceNumber()` dipindah/dibungkus dalam DB transaction dengan `lockForUpdate` pada query count. Implementasi: `DB::transaction` di `TransactionService::create()` sudah ada — query `Transaction::where(tenant_id, today)->lockForUpdate()->count()` lalu `+1`, BUKAN `count()` tanpa lock. Unique composite `(tenant_id, invoice_number)` tetap sebagai pertahanan terakhir (bila race lolos, insert abort duplicate → retry). Format `INV-YYYYMMDD-XXXX`, urutan reset per tenant per hari (whereDate created_at).

**Rationale**: `count()+1` tanpa lock = race condition diam-diam (dua transaksi konkuren dapat count sama → nomor sama). Unique constraint menangkap tapi insert abort = UX buruk + perlu retry. `lockForUpdate` mencegah race di sumber. ERD `transactions.md` + revisi input eksplisit.

**Alternatives considered**: Sequence PostgreSQL per tenant per hari (`CREATE SEQUENCE`) — lebih robust tapi DB-specific (break SQLite test) + kompleks reset harian. `ponytail: sequence add bila >9999 transaksi/hari/tenant atau throughput tinggi`. Unique constraint + retry saja — ditolak (UX buruk, insert abort di beban nyata).

## R5 — Soft delete transaksi (FR-081, revisi input)

**Decision**: Migration tambah `softDeletes('deleted_at')` + index `(tenant_id, deleted_at)` ke `transactions`. Model: `use SoftDeletes;`. `TransactionController` tambah `destroy` endpoint (soft delete, bukan hard). Tidak expose `restore`/`forceDelete` di MVP (`ponytail:` add bila butuh). Query list default exclude soft-deleted (SoftDeletes global scope otomatis). Hard-delete diblokir restrict FK (`transaction_id` di payments/items cascade — tapi cascade hanya saat hard-delete parent; soft delete tidak trigger FK, jadi child tetap utuh untuk audit).

**Rationale**: Transaksi finansial wajib bertahan untuk audit/laporan (ERD `transactions.md`, konstitusi VI). Soft-delete = koreksi tanpa kehilangan jejak. Index `(tenant_id, deleted_at)` untuk list aktif per tenant.

**Alternatives considered**: Hard-delete — ditolak (hilang data finansial, melanggar audit). `cancelled_at` saja — sudah ada untuk pembatalan bisnis (FR-058), berbeda semantik dari soft-delete (hapus catatan salah).

## R6 — FK `restrictOnDelete` (FR-083, revisi input)

**Decision**: Migration ubah FK: `transactions.patient_id` nullOnDelete → **restrictOnDelete**, `transactions.booking_id` nullOnDelete → **restrictOnDelete`. (`cashier_id` sudah restrict via migration kedua.) Child admin tetap cascade: `payments.transaction_id` cascade, `transaction_items.transaction_id` cascade (ERD: cascade aman karena parent soft-delete, cascade DB hanya saat hard-delete parent = kasus terlarang). `transaction_items.product_id`/`service_id` nullOnDelete → **restrictOnDelete** (anomali #1 terkait, spec langkah 11, tapi FK restrict sama-sama di spec 008 — kerjakan di sini bila belum).

**Rationale**: Pasien/kasir/booking yang direferensi transaksi tidak boleh terhapus begitu (referensi finansial putus). ERD `README.md` delete rule + `transactions.md`. restrictOnDelete = blok hard-delete parent; soft-delete parent tetap diizinkan (transaksi tetap utuh).

**Alternatives considered**: nullOnDelete — ditolak (memutus referensi historis, transaksi tunjuk null). cascadeOnDelete — ditolak (hapus transaksi finansial beruntun, fatal).

**Catatan migration SQLite**: FK delete rule alter tidak didukung SQLite. Guard `if (DB::getDriverName() === 'pgsql')` atau `$table->dropForeign`+`addForeign` skip di SQLite. `ForeignKeyRestrictTest` hanya jalan via `phpunit.pgsql.xml` (sudah konvensi project).

## R7 — F0 merge invoices (keputusan user: MERGE)

**Decision**: **MERGE** — `issued_at` pindah ke `transactions`, drop tabel `invoices` + model `Invoice` + `InvoicePolicy` + `InvoiceController` + relasi `Transaction::invoice()`. Migration: tambah `issued_at datetime` ke `transactions`, drop tabel `invoices`. `TransactionService::create()` ganti `$transaction->invoice()->create(['issued_at'=>now()])` → `$transaction->update(['issued_at'=>now()])` (atau set saat create). `InvoiceController::show` / `InvoiceService::render()` ganti `$transaction->invoice?->issued_at` → `$transaction->issued_at`. `Transaction` tambah `issued_at` fillable+cast `datetime`. `TransactionResource` expose `issued_at`.

**Rationale**: User pilih merge (BCNF lebih pure, YAGNI — tabel invoices nyaris hanya `transaction_id`+`issued_at` 1:1 tanpa atribut tambahan). Normalisasi README §Hubungan 1:1 rekomendasi default MVP merge. `invoice_number` tetap di `transactions` (sudah ada) — nomor invoice = nomor transaksi, tidak terpisah.

**Alternatives considered**: Pertahankan tabel — ditolak user (YAGNI, 1:1 tanpa atribut tambahan). `ponytail: add tabel invoices bila butuh multi-cetakan/nomor invoice terpisah/status cetak`.

**Impact**: Route `transactions/{transaction}/invoice` tetap (render dari transaction, bukan relasi). FE `pos/invoices/$id.tsx` tetap (baca `issued_at` dari transaction). InvoicePolicy dihapus, authorize via `TransactionPolicy@view`.

## R8 — Migration strategy PostgreSQL vs SQLite

**Decision**: Spec 008 menyentuh schema: tambah `paid_amount`, alter `payment_status` enum (+partially_paid), tambah `deleted_at` soft delete, tambah index, alter FK delete rules, tambah `issued_at`, drop tabel `invoices`. Alter enum + alter FK delete rule tidak didukung SQLite. Strategi:
- Kolom baru (`paid_amount`, `deleted_at`, `issued_at`) + index: dijalankan di SQLite+PostgreSQL.
- Alter enum `payment_status` tambah `partially_paid`: SQLite tidak dukung `ALTER TYPE` — SQLite pakai `DB::statement` raw terbatas; di SQLite enum disimpan sebagai string constraint `CHECK` — bila pakai `$table->enum` awal, alter butuh `DB::statement` drop+add CHECK. **Pragmatic**: bila migration awal pakai `$table->enum(...)` (SQLite jalan), migration alter ganti full column recreate di SQLite, atau guard `if DriverName pgsql` jalankan `ALTER TYPE`. Suite PostgreSQL (`phpunit.pgsql.xml`) WAJIB sebelum rilis (konvensi CLAUDE.md).
- Alter FK delete rule: guard `if pgsql` dropForeign+foreignConstrained restrictOnDelete; SQLite skip (FK alter tidak didukung, `ForeignKeyRestrictTest` hanya pgsql).

**Rationale**: SQLite = test cepat dev; PostgreSQL = produksi. Constraint skema produksi teruji lewat `phpunit.pgsql.xml` (CLAUDE.md eksplisit). `ponytail: test SQLite cukup untuk logika app; constraint restrict diverifikasi pgsql`.

**Alternatives considered**: Drop SQLite, test pgsql saja — ditolak (lambat, butuh docker per test run, break konvensi existing).

## R9 — Exclusive arc CHECK (anomali #1, terkait spec 008)

**Decision**: Spec 008 fokus transactions; exclusive arc `transaction_items` CHECK `(product_id IS NULL) <> (service_id IS NULL)` secara teknis di langkah 11 workflow. TAPI `TransactionRequest` validasi app-layer (`required_without`/`prohibits`) WAJIB ditambah di spec 008 (sebagai pertahanan UX + integritas, karena `buildLines()` sudah pilih salah satu tapi request bisa kirim keduanya null/terisi). CHECK DB-level dikerjakan di langkah 11 (PostgreSQL). Spec 008: tambah validasi `TransactionRequest` items `product_id` XOR `service_id` + test.

**Rationale**: Pertahanan berlapis — app validation (UX, 422) + DB CHECK (integritas, langkah 11). Spec 008 menyentuh TransactionRequest, jadi tutup gap app-layer di sini.

**Alternatives considered**: Tunggu langkah 11 saja — ditolak (request bisa kirim item invalid sekarang, `buildLines` silent fail).

## R10 — Guard cancel transaksi (FR-058 edge case)

**Decision**: `CancelTransactionAction` tambah guard: tolak cancel bila `cancelled_at` sudah terisi (sudah batal) — 422/409. Batal transaksi yang sudah `paid`/`partially_paid`: default MVP **diizinkan** (rollback stok + set cancelled_at, `paid_amount` tetap untuk audit — pembatalan transaksi lunas = scenario refund manual di luar sistem). `ponytail: refund workflow add bila butuh`.

**Rationale**: Double-cancel = bug (stok di-rollback dua kali). Cancel transaksi lunas lazim di klinik (pasien batal, refund manual). Guard mencegah idempotensi rusak.

**Alternatives considered**: Blok cancel transaksi paid — ditolak (klinik butuh batal walau lunas, refund manual di luar).

## R11 — FE komponen reusable (input user)

**Decision**: Spec 008 FE pakai `components/forms/` + `components/datatable/` eksisting. Komponen reusable baru (dipakai >=2 tempat / gap nyata):
1. **`formatCurrency` → `src/lib/format.ts`** (bukan komponen, helper) — pindah dari `pos/components/format.ts` (lokal, dipakai 5 file pos). Dapat dipakai products/services table (saat ini price mentah) + reports. >=3 tempat.
2. **`StatusBadge`** (`components/ui/` atau `components/datatable/`) — pemetaan status→variant+label inline di >=4 halaman (transactions, products, services, payment-panel). Hapus duplikasi. Dipakai badge `payment_status` 3-state di transactions + status produk/layanan.
3. **`FormCombobox`** (`components/forms/`) — `FormSelect` (NativeSelect statis) tidak bisa handle select pasien (ratusan opsi, butuh search). UI primitives `combobox.tsx`+`command.tsx` sudah ada di `components/ui/`, belum dibungkus react-hook-form. POS `index.tsx` pakai NativeSelect mentah tanpa search. Berpotensi dipakai booking (select pasien/dokter) + POS item katalog.

Tidak dibuat (YAGNI): `FormNumericInput` formatter Rupiah (`FormInput type=number`+`z.coerce.number()` sudah jalan), `DataTableCurrencyCell` (1-baris `formatCurrency` di cell, abstraksi hemat 1 import saja).

**Rationale**: Input user eksplisit minta pakai + improve `forms/`+`datatable/`. 3 item = gap nyata + duplikasi terbukti. Sisanya reuse as-is.

**Alternatives considered**: Build semua komponen baru — ditolak (over-engineering, melanggar YAGNI konstitusi IV).

## R12 — FE POS eksisting + i18n (FR-080/087)

**Decision**: FE POS sudah ada `pos/index.tsx` (buat transaksi: Card item list + Card payment) + `pos/transactions.tsx` (DataTable riwayat) + `pos/invoices/$id.tsx` + `pos/components/{payment-panel,transaction-item-list,format}.tsx`. Revisi spec 008:
- `pos/transactions.tsx`: tambah faceted filter `payment_status` (3-state via `DataTableFacetedFilter`), kolom `paid_amount` + sisa bayar (`subtotal - paid_amount`), `StatusBadge` 3-state, pakai `formatCurrency` dari `src/lib/`.
- `pos/index.tsx`: ganti NativeSelect pasien → `FormCombobox` (searchable), validasi via `useForm`+zod (saat ini state manual tanpa validasi), badge status 3-state, label i18n `clinic.payment_status.partially_paid`.
- `payment-panel.tsx`: badge 3-state, `paid_amount` vs subtotal + sisa bayar.
- i18n: tambah `clinic.payment_status.partially_paid` di `lang/id/clinic.php` (saat ini hanya `unpaid`/`paid`).
- Breadcrumb: `pos/` sudah pakai `ClinicBreadcrumb` — verifikasi jalur induk→aktif (FR-087).

**Rationale**: Reuse struktur POS eksisting, perbaiki gap (searchable pasien, validasi, 3-state badge, sisa bayar). Konsistensi pola halaman master (breadcrumb + header + DataTable + form).

**Alternatives considered**: Pindah `pos/` → `transactions/` folder — ditolak (break route existing, YAGNI rename).

## R13 — Audit log naratif (FR-084, konstitusi VI)

**Decision**: `PayTransactionAction` catat activity log via `LogAuditAction::handle('pos.payment.created', $payment, $causer, ['old_status'=>..., 'new_status'=>..., 'amount'=>..., 'invoice_number'=>...], "Mencatat pembayaran {invoice} — status {lama}→{baru}", $tenant)`. `TransactionService::create()` → `LogAuditAction('pos.transaction.created', ...)`. `CancelTransactionAction` → `LogAuditAction('pos.transaction.cancelled', ...)`. Soft-delete (`destroy`) → `LogAuditAction('pos.transaction.deleted', ...)`. Properties: create=full attributes, update/payment=old/new diff, delete=subject context.

**Rationale**: Konstitusi VI wajib audit naratif tiap Action ubah-data. Spec 008 AC eksplisit "Mencatat pembayaran … status {lama}→{baru}". `LogAuditAction` signature sudah ada (R1).

**Alternatives considered**: Log di controller — ditolak (melanggar layering, konstitusi VI penempatan audit di Action).
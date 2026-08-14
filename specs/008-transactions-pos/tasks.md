# Tasks: Transaksi POS & Pembayaran Klinik

**Input**: Design documents from `/specs/008-transactions-pos/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/transactions-api.md, quickstart.md

**Tests**: TDD WAJIB (konstitusi II). Test tasks ditulis lebih dulu (Red) oleh agent `zahiira` sebelum implementasi (Green). Backend authoring → `ammar`. Frontend authoring → `sierly`. Delegasi: FE → `sierly` langsung; BE → `ammar`; tests → `zahiira`.

**Organization**: Tasks grouped by user story (spec.md: US1 P1, US2 P2, US3 P3, US4 P4, US5 P5) for independent implementation & testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g. US1, US2, US3, US4, US5)
- Include exact file paths in descriptions

## Path Conventions

- **Web app monorepo**: `apps/api/` (backend Laravel), `apps/web/` (frontend TanStack Start). Paths below repo-relative.
- Backend code: `apps/api/app/...`, migrations `apps/api/database/migrations/`, factories `apps/api/database/factories/`, tests `apps/api/tests/...`, lang `apps/api/lang/id/`.
- Frontend code: `apps/web/src/...`, routes `apps/web/src/routes/$tenant/clinic/pos/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Migration schema revision (paid_amount, soft-delete, issued_at, enum 3-state, FK restrict, drop invoices) + factories for test seeding.

- [ ] T001 Create migration to add `paid_amount` (decimal(12,2) default 0 not null), `deleted_at` (softDeletes), index `(tenant_id, deleted_at)`, and `issued_at` (datetime nullable) to transactions in apps/api/database/migrations/2026_08_14_add_transactions_paid_amount_softdelete_issued_at.php (R2/R5/R7)
- [ ] T002 Create migration to add `partially_paid` to payment_status enum in apps/api/database/migrations/2026_08_14_alter_payment_status_add_partially_paid.php (R3) — guard: PostgreSQL `ALTER TYPE` / SQLite skip or CHECK recreate
- [ ] T003 Create migration to change FK `transactions.patient_id` + `transactions.booking_id` from nullOnDelete → restrictOnDelete in apps/api/database/migrations/2026_08_14_restrict_transaction_foreign_keys.php (R6, FR-083) — guard `if DB::getDriverName() === 'pgsql'` (SQLite skip; `ForeignKeyRestrictTest` pgsql only)
- [ ] T004 Create migration to drop `invoices` table (F0 merge) in apps/api/database/migrations/2026_08_14_drop_invoices_table.php (R7) — drop table + foreign key
- [ ] T005 [P] Create TransactionFactory in apps/api/database/factories/TransactionFactory.php — tenant_id, patient_id, cashier_id, booking_id nullable, invoice_number, subtotal, paid_amount default 0, payment_status default unpaid, issued_at nullable — for test seeding
- [ ] T006 [P] Create PaymentFactory in apps/api/database/factories/PaymentFactory.php — tenant_id, transaction_id, method, amount, paid_at — for payment test seeding
- [ ] T007 [P] Create TransactionItemFactory in apps/api/database/factories/TransactionItemFactory.php — tenant_id, transaction_id, product_id/service_id nullable (XOR), name, unit_price, qty, subtotal — for item test seeding

**Checkpoint**: Migration + factories ready. Run `php artisan migrate` setelah implementasi. `php artisan test -c phpunit.pgsql.xml --filter=Transaction` sebelum rilis (constraint restrict + enum).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Model/enum/service/action core revision — MUST complete before user stories. shared oleh semua story.

**⚠️ CRITICAL**: `paid_amount` sync, 3-state enum, `lockForUpdate` invoice race, F0 merge issued_at, audit log naratif — fondasi semua story.

- [ ] T008 Edit PaymentStatus enum in apps/api/app/Enums/PaymentStatus.php — tambah `PartiallyPaid = 'partially_paid'` case + label() `__('clinic.payment_status.partially_paid')` (R3, FR-055)
- [ ] T009 Edit Transaction model in apps/api/app/Models/Transaction.php — `use SoftDeletes`; tambah `paid_amount`, `issued_at` ke $fillable; tambah cast `paid_amount` => 'decimal:2', `issued_at` => 'datetime', `deleted_at` => 'datetime'; hapus relasi `invoice()` (F0 merge); generateInvoiceNumber() pindah query ke dalam DB transaction dengan `lockForUpdate` (R4, FR-077) — method <100 baris
- [ ] T010 Delete Invoice model in apps/api/app/Models/Invoice.php (R7, F0 merge)
- [ ] T011 [P] Delete InvoicePolicy in apps/api/app/Policies/InvoicePolicy.php (atau app/Http/Policies/, sesuai lokasi eksisting) (R7, F0 merge)
- [ ] T012 Edit TransactionService::create in apps/api/app/Services/TransactionService.php — generateInvoiceNumber() dipanggil dalam DB::transaction dengan `lockForUpdate` count (R4); ganti `$transaction->invoice()->create(['issued_at'=>now()])` → set `issued_at` saat create transaction (F0 merge, R7); tambah LogAuditAction event `pos.transaction.created` narasi "Mencatat transaksi {invoice}" full attributes (R13). Service dilarang sentuh DB langsung — tetap via Action bila perlu. Method <100 baris.
- [ ] T013 Edit PayTransactionAction in apps/api/app/Actions/PayTransactionAction.php — DB::transaction: `payments()->create` → `lockForUpdate` row transaction → `paid_amount += amount` → set `payment_status` (`paid` bila >= subtotal, `partially_paid` bila 0 < < subtotal, `unpaid` bila 0) (R3, FR-055/079) → LogAuditAction event `pos.payment.created` narasi "Mencatat pembayaran {invoice} — status {lama}→{baru}" properties old_status/new_status/amount/paid_amount (R13, FR-084). Method <100 baris.
- [ ] T014 Edit CancelTransactionAction in apps/api/app/Actions/CancelTransactionAction.php — tambah guard: tolak bila `cancelled_at` sudah terisi (R10, throw 409/ValidationException); rollback stok tetap; LogAuditAction event `pos.transaction.cancelled` narasi "Membatalkan transaksi {invoice}" (R13). Method <100 baris.
- [ ] T015 Create SoftDeleteTransactionAction in apps/api/app/Actions/Transaction/SoftDeleteTransactionAction.php — `execute(Transaction): Transaction` soft-delete ($transaction->delete()) + LogAuditAction event `pos.transaction.deleted` narasi "Menghapus transaksi {invoice}" (R5, FR-081, R13). Inject LogAuditAction (bukan Service).
- [ ] T016 Edit TransactionRequest in apps/api/app/Http/Requests/TransactionRequest.php — `patient_id` jadi required (FR-050, sebelumnya nullable); tambah exclusive arc validation items `product_id` XOR `service_id` (R9: `required_without:service_id` + `prohibits:service_id` atau custom rule); booking_id bila terisi harus status=done (FR-033)
- [ ] T017 Edit TransactionController in apps/api/app/Http/Controllers/TransactionController.php — tambah `destroy(Transaction)` endpoint soft-delete via SoftDeleteTransactionAction (FR-081); authorize `delete`; method <100 baris
- [ ] T018 Edit TransactionResource in apps/api/app/Http/Resources/TransactionResource.php — expose `paid_amount`, `issued_at`, `booking_id` (R2/R7); tetap `payment_status` + `payment_status_label`
- [ ] T019 Create PaymentResource in apps/api/app/Http/Resources/PaymentResource.php — fields: id, method, method_label, amount, paid_at, created_at (R1 gap)
- [ ] T020 Edit InvoiceService in apps/api/app/Services/InvoiceService.php — ganti `$transaction->invoice?->issued_at` → `$transaction->issued_at` (F0 merge, R7); render dari transaction
- [ ] T021 Edit InvoiceController in apps/api/app/Http/Controllers/InvoiceController.php — authorize via `TransactionPolicy@view` (bukan InvoicePolicy yang dihapus); render dari transaction (R7)
- [ ] T022 Edit routes/api.php in apps/api/routes/api.php — tambah `DELETE transactions/{transaction}` ke apiResource (FR-081); hapus referensi InvoicePolicy; verifikasi route `transactions/{transaction}/invoice` tetap
- [ ] T023 [P] Edit lang/id/clinic.php — tambah key `payment_status.partially_paid` = "Dibayar Sebagian" (R3, FR-080)

**Checkpoint**: Foundation ready — 3-state enum, paid_amount sync, lockForUpdate invoice, F0 merge issued_at, soft-delete, FK restrict, audit naratif. User story implementation dapat mulai.

---

## Phase 3: User Story 1 - Kasir Catat Transaksi POS Baru (Priority: P1) MVP

**Goal**: Kasir mencatat transaksi POS (pasien + item produk/layanan + link booking opsional); invoice_number auto-generate lockForUpdate; snapshot nama+harga; subtotal; status unpaid awal.

**Independent Test**: Buat satu transaksi satu item layanan tanpa booking → tersimpan subtotal benar, status unpaid, paid_amount 0, invoice_number tergenerasi, issued_at terisi. Tanpa modul pembayaran cicilan/pembatalan.

### Tests for User Story 1 (TDD — write FIRST, must FAIL)

- [ ] T024 [P] [US1] Feature test: create transaksi satu item layanan tanpa booking → 201, payment_status=unpaid, paid_amount=0, invoice_number format INV-YYYYMMDD-XXXX, issued_at terisi, audit_logs row `pos.transaction.created` narasi mengandung invoice — in apps/api/tests/Feature/Transaction/CreateTransactionTest.php
- [ ] T025 [P] [US1] Feature test: create transaksi link booking done → booking_id terisi (FR-033); link booking non-done → 422 — in apps/api/tests/Feature/Transaction/TransactionBookingLinkTest.php
- [ ] T026 [P] [US1] Feature test: create transaksi item produk → stok berkurang via sold_pos (FR-052); stok kurang → 422 (FR-053) — in apps/api/tests/Feature/Transaction/TransactionStockTest.php
- [ ] T027 [P] [US1] Feature test: exclusive arc — item product_id+service_id keduanya terisi → 422; keduanya null → 422 (R9) — in apps/api/tests/Feature/Transaction/TransactionExclusiveArcTest.php
- [ ] T028 [P] [US1] Feature test: snapshot immutability — ubah master service/product + arsip → transaction_items.name/unit_price tetap (R6, FR-056) — in apps/api/tests/Feature/Transaction/TransactionSnapshotTest.php
- [ ] T029 [P] [US1] Feature test: patient_id required — tanpa patient_id → 422 (FR-050) — in apps/api/tests/Feature/Transaction/TransactionValidationTest.php
- [ ] T030 [P] [US1] Feature test: tenant isolation — transaksi tenant A tidak terlihat tenant B, GET id tenant lain → 404 — in apps/api/tests/Feature/Transaction/TransactionTenantIsolationTest.php
- [ ] T031 [P] [US1] Feature test: permission — role tanpa izin transaction → GET/POST 403 — in apps/api/tests/Feature/Transaction/TransactionPermissionTest.php
- [ ] T032 [P] [US1] Unit test: TransactionService::create menghasilkan LogAuditAction row naratif `pos.transaction.created` — in apps/api/tests/Unit/Transaction/TransactionServiceAuditTest.php

### Implementation for User Story 1

- [ ] T033 [US1] (Verifikasi) Konfirmasi T012, T016, T018 benar — jalankan T024–T032 hingga Green. Tidak ada file baru bila foundational benar.
- [ ] T034 [P] [US1] FE: create formatCurrency helper in apps/web/src/lib/format.ts — pindah dari apps/web/src/routes/$tenant/clinic/pos/components/format.ts (R11) — `Intl.NumberFormat("id-ID", {style:"currency", currency:"IDR"})`. Reusable untuk products/services table + reports.
- [ ] T035 [US1] FE: edit pos/index.tsx in apps/web/src/routes/$tenant/clinic/pos/index.tsx — ganti NativeSelect pasien → FormCombobox searchable (T038); tambah validasi `useForm`+zod (saat ini state manual); pakai formatCurrency dari src/lib/format.ts; badge status 3-state via StatusBadge (T039). Component <=300 baris, extract bila perlu.
- [ ] T036 [US1] FE: edit pos/components/transaction-item-list.tsx in apps/web/src/routes/$tenant/clinic/pos/components/transaction-item-list.tsx — ganti import formatCurrency dari `#/lib/format` (bukan lokal); tetap pakai pola eksisting (optgroup service/product, qty input, subtotal).
- [ ] T037 [US1] FE: delete pos/components/format.ts in apps/web/src/routes/$tenant/clinic/pos/components/format.ts — sudah pindah ke src/lib/format.ts (R11). Update import di pos/invoices/$id.tsx + payment-panel.tsx + transactions.tsx.

**Checkpoint**: US1 fully functional & testable independently. Kasir catat transaksi POS, invoice_number auto lockForUpdate, snapshot, link booking opsional, FE POS pakai FormCombobox + formatCurrency lib.

---

## Phase 4: User Story 2 - Nomor Invoice Unik walau Concurrent (Priority: P2)

**Goal**: invoice_number unik per tenant per hari walau konkuren — generateInvoiceNumber lockForUpdate dalam DB transaction; urutan reset harian.

**Independent Test**: Simulasikan 2+ transaksi konkuren per tenant per hari → nomor berurutan berbeda, tidak ada duplikat; tanggal ganti → reset urutan.

### Tests for User Story 2 (TDD — write FIRST, must FAIL)

- [ ] T038 [P] [US2] Feature test: invoice_number urutan naik per tenant per hari — transaksi pertama ...-0001, kedua ...-0002 — in apps/api/tests/Feature/Transaction/InvoiceNumberSequenceTest.php
- [ ] T039 [P] [US2] Feature test: invoice_number konkuren — 2 request bersamaan → nomor berurutan berbeda, tidak duplikat (pakai fork/async di test) (FR-077, SC-002) — in apps/api/tests/Feature/Transaction/InvoiceNumberConcurrencyTest.php
- [ ] T040 [P] [US2] Feature test: invoice_number reset per hari — transaksi pertama hari baru → ...-0001 (mock now() / pakai tanggal berbeda) — in apps/api/tests/Feature/Transaction/InvoiceNumberDailyResetTest.php
- [ ] T041 [P] [US2] Feature test: invoice_number unik per tenant — tenant A dan B sama-sama dapat ...-0001 hari yang sama (scoped per tenant) — in apps/api/tests/Feature/Transaction/InvoiceNumberPerTenantTest.php

### Implementation for User Story 2

- [ ] T042 [US2] (Verifikasi) Konfirmasi T009 (generateInvoiceNumber lockForUpdate) + T012 (DB::transaction) benar — jalankan T038–T041 hingga Green. `ponytail: sequence per tenant per hari add bila >9999 transaksi/hari/tenant`. Unique composite `(tenant_id, invoice_number)` tetap pertahanan terakhir.

**Checkpoint**: US2 fully functional. Nomor invoice unik walau konkuren, reset harian, scoped per tenant.

---

## Phase 5: User Story 3 - Status Pembayaran 3-State & Sisa Bayar (Priority: P3)

**Goal**: payment_status 3-state (unpaid/partially_paid/paid); paid_amount akumulasi; sisa bayar = subtotal - paid_amount; badge 3-state + label i18n partially_paid; overpaid peringatan.

**Independent Test**: Buat transaksi subtotal 300000 → bayar 100000 (partially_paid, sisa 200000) → bayar 200000 (paid, sisa 0) → overpaid peringatan.

### Tests for User Story 3 (TDD — write FIRST, must FAIL)

- [ ] T043 [P] [US3] Feature test: bayar parsial — transaksi 300000, bayar 100000 → paid_amount=100000, payment_status=partially_paid (FR-055, R3) — in apps/api/tests/Feature/Payment/PayPartialTest.php
- [ ] T044 [P] [US3] Feature test: pelunasan — partially_paid, bayar 200000 → paid_amount=300000, payment_status=paid — in apps/api/tests/Feature/Payment/PayFullTest.php
- [ ] T045 [P] [US3] Feature test: overpaid — bayar melebihi subtotal → 201, meta.overpaid=true, peringatan (tidak 422) (FR-055 edge) — in apps/api/tests/Feature/Payment/PayOverpaidTest.php
- [ ] T046 [P] [US3] Feature test: payment_status_label 3-state — response label "Dibayar Sebagian" untuk partially_paid (i18n clinic.payment_status.partially_paid) — in apps/api/tests/Feature/Payment/PaymentStatusLabelTest.php
- [ ] T047 [P] [US3] Feature test: amount <=0 → 422 (PaymentRequest gt:0) — in apps/api/tests/Feature/Payment/PaymentValidationTest.php
- [ ] T048 [P] [US3] Feature test: paid_amount sync akumulasi multi-payment dalam DB transaction — 3 pembayaran → paid_amount = sum (FR-079, R2) — in apps/api/tests/Feature/Payment/PaidAmountAccumulationTest.php
- [ ] T049 [P] [US3] Unit test: PayTransactionAction menghasilkan LogAuditAction row naratif "Mencatat pembayaran {invoice} — status {lama}→{baru}" properties old_status/new_status/amount (FR-084, R13) — in apps/api/tests/Unit/Payment/PayTransactionActionAuditTest.php

### Implementation for User Story 3

- [ ] T050 [US3] (Verifikasi) Konfirmasi T008 (enum), T013 (PayTransactionAction 3-state + paid_amount + audit), T019 (PaymentResource), T023 (i18n) benar — jalankan T043–T049 hingga Green.
- [ ] T051 [US3] FE: create status-badge.tsx in apps/web/src/components/ui/status-badge.tsx — reusable `<StatusBadge status variantMap label>` (R11) — hapus duplikasi inline di >=4 halaman. Props: status string, label, variantMap (status→badge variant). Dipakai payment_status 3-state + status produk/layanan.
- [ ] T052 [US3] FE: edit pos/components/payment-panel.tsx in apps/web/src/routes/$tenant/clinic/pos/components/payment-panel.tsx — badge status 3-state via StatusBadge; tampilkan paid_amount vs subtotal + sisa bayar (subtotal - paid_amount); formatCurrency dari src/lib/format.ts; label i18n partially_paid.
- [ ] T053 [US3] FE: edit pos/transactions.tsx in apps/web/src/routes/$tenant/clinic/pos/transactions.tsx — tambah faceted filter payment_status 3-state (Belum Lunas/Dibayar Sebagian/Lunas) via DataTableFacetedFilter; kolom paid_amount + sisa bayar diformat formatCurrency; StatusBadge 3-state ganti inline Badge. Component <=300 baris.

**Checkpoint**: US3 fully functional. 3-state pembayaran, paid_amount akumulasi, sisa bayar, overpaid peringatan, badge 3-state FE + label i18n.

---

## Phase 6: User Story 4 - Soft-Delete Transaksi (Priority: P4)

**Goal**: Soft-delete transaksi (deleted_at); data utuh audit; tidak muncul di list aktif; hard-delete + hapus parent direferensi diblokir restrict.

**Independent Test**: Soft-delete satu transaksi → tidak muncul di list aktif, tetap ada di DB deleted_at terisi; hard-delete transaksi dengan payment → diblokir restrict; hapus pasien/booking direferensi → diblokir restrict.

### Tests for User Story 4 (TDD — write FIRST, must FAIL)

- [ ] T054 [P] [US4] Feature test: soft-delete transaksi → DELETE 200, deleted_at terisi, tidak muncul di GET index, GET show → 404 (FR-081, R5) — in apps/api/tests/Feature/Transaction/SoftDeleteTransactionTest.php
- [ ] T055 [P] [US4] Feature test: hard-delete transaksi dengan payment → QueryException restrict (FR-082) — in apps/api/tests/Feature/Transaction/HardDeleteRestrictTest.php (pgsql only, `phpunit.pgsql.xml`)
- [ ] T056 [P] [US4] Feature test: hapus pasien direferensi transaksi → QueryException restrict (FR-083, R6) — in apps/api/tests/Feature/Transaction/DeletePatientRestrictTest.php (pgsql only)
- [ ] T057 [P] [US4] Feature test: hapus booking direferensi transaksi → QueryException restrict (FR-083, R6) — in apps/api/tests/Feature/Transaction/DeleteBookingRestrictTest.php (pgsql only)
- [ ] T058 [P] [US4] Feature test: cancel transaksi sudah cancelled → 409 (R10 guard double-cancel) — in apps/api/tests/Feature/Transaction/CancelDoubleGuardTest.php
- [ ] T059 [P] [US4] Feature test: cancel transaksi → rollback stok produk (FR-058), cancelled_at terisi — in apps/api/tests/Feature/Transaction/CancelTransactionTest.php
- [ ] T060 [P] [US4] Unit test: SoftDeleteTransactionAction menghasilkan LogAuditAction row naratif `pos.transaction.deleted` (R13) — in apps/api/tests/Unit/Transaction/SoftDeleteActionAuditTest.php

### Implementation for User Story 4

- [ ] T061 [US4] (Verifikasi) Konfirmasi T003 (FK restrict migration), T009 (SoftDeletes trait), T015 (SoftDeleteTransactionAction), T014 (cancel guard), T017 (destroy endpoint), T022 (route) benar — jalankan T054–T060 hingga Green. Pgsql suite untuk restrict test.

**Checkpoint**: US4 fully functional. Soft-delete, restrict FK, cancel guard, rollback stok.

---

## Phase 7: User Story 5 - Activity Log Pembayaran & Breadcrumb (Priority: P5)

**Goal**: Audit log naratif "Mencatat pembayaran … status {lama}→{baru}" tiap aksi; breadcrumb halaman POS + detail transaksi.

**Independent Test**: Catat pembayaran unpaid→partially_paid → audit log narasi transisi status; buka halaman POS → breadcrumb jalur induk→aktif.

### Tests for User Story 5 (TDD — write FIRST, must FAIL)

- [ ] T062 [P] [US5] Feature test: audit log pembayaran — bayar (unpaid→partially_paid) → audit_logs row `pos.payment.created` narasi "Mencatat pembayaran {invoice} — status unpaid→partially_paid" properties old_status/new_status/amount/paid_amount (FR-084) — in apps/api/tests/Feature/Payment/PaymentAuditLogTest.php
- [ ] T063 [P] [US5] Feature test: audit log pelunasan — partially_paid→paid narasi transisi benar — in apps/api/tests/Feature/Payment/PaymentAuditLogTransitionTest.php
- [ ] T064 [P] [US5] Feature test: audit log cancel + soft-delete naratif — `pos.transaction.cancelled` + `pos.transaction.deleted` (R13) — in apps/api/tests/Feature/Transaction/TransactionAuditLogTest.php
- [ ] T065 [P] [US5] Feature test: audit log create transaksi naratif `pos.transaction.created` — in apps/api/tests/Feature/Transaction/CreateAuditLogTest.php

### Implementation for User Story 5

- [ ] T066 [US5] (Verifikasi) Konfirmasi T012, T013, T014, T015 audit naratif benar — jalankan T062–T065 hingga Green.
- [ ] T067 [US5] FE: verifikasi breadcrumb pos/index.tsx + pos/transactions.tsx in apps/web/src/routes/$tenant/clinic/pos/ — ClinicBreadcrumb jalur "Beranda Klinik > Transaksi" (item terakhir non-link, induk link ke `/$tenant/clinic`); detail transaksi "Beranda Klinik > Transaksi > {invoice_number}" (FR-087). Edit bila jalur salah.
- [ ] T068 [US5] FE: create form-combobox.tsx in apps/web/src/components/forms/form-combobox.tsx — searchable select terintegrasi react-hook-form (R11) — bungkus combobox.tsx+command.tsx primitives eksisting. Props: control, name, label, options (atau async loader), placeholder. Dipakai POS select pasien (T035) + berpotensi booking. Hapus NativeSelect mentah pasien di pos/index.tsx.

**Checkpoint**: US5 fully functional. Audit log naratif semua aksi, breadcrumb benar, FormCombobox searchable.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Konsistensi lintas story, verifikasi, cleanup.

- [ ] T069 [P] FE: update import formatCurrency di pos/invoices/$id.tsx in apps/web/src/routes/$tenant/clinic/pos/invoices/$id.tsx — dari `#/lib/format` (bukan lokal) (R11)
- [ ] T070 [P] FE: terapkan StatusBadge di products/index.tsx + services/index.tsx in apps/web/src/routes/$tenant/clinic/ — ganti inline Badge status kondisional → StatusBadge reusable (R11, >=4 tempat)
- [ ] T071 [P] FE: format kolom price products/index.tsx + services/index.tsx via formatCurrency dari src/lib/format.ts (R11 — saat ini price mentah)
- [ ] T072 Run quickstart.md validation — jalankan 10 skenario di specs/008-transactions-pos/quickstart.md secara manual (T001–T068 selesai)
- [ ] T073 [P] BE: run `php artisan test` (sqlite) — seluruh test transaksi+payment hijau
- [ ] T074 BE: run `php artisan test -c phpunit.pgsql.xml --filter=Transaction` — constraint restrict + enum 3-state teruji (WAJIB sebelum rilis, CLAUDE.md)
- [ ] T075 Code cleanup — hapus dead code, verifikasi class PHP <=300 baris / method <=100 baris / komponen React <=300 baris (konstitusi V)
- [ ] T076 Verify tenant isolation test suite hijau (konstitusi III) — TransactionTenantIsolationTest + PaymentTenantIsolationTest

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — migration + factories. Run `php artisan migrate` setelah.
- **Foundational (Phase 2)**: Depends on Phase 1 — model/enum/service/action/resource/route/lang revision. BLOCKS all user stories.
- **User Stories (Phase 3–7)**: All depend on Phase 2 completion.
  - US1 (P1): create transaksi + FE POS core — foundational T012/T016/T018 + FE reusable T034/T038(FormCombobox di US5 tapi dipakai US1 — **urutan**: FormCombobox T068 naik ke foundational/US1 bila US1 butuh, atau US1 pakai temporary + refine di US5). Lihat catatan.
  - US2 (P2): invoice concurrency — depends T009/T012 (lockForUpdate). Independent dari US1 logic.
  - US3 (P3): 3-state payment — depends T008/T013/T019/T023. Independent.
  - US4 (P4): soft-delete + restrict — depends T003/T009/T015/T017. Independent.
  - US5 (P5): audit + breadcrumb — depends T012–T015 audit + FE breadcrumb. Independent.
- **Polish (Phase 8)**: Depends on all desired user stories complete.

### Catatan dependency FE FormCombobox

`FormCombobox` (T068, US5) dipakai US1 (T035 select pasien). Solusi: **pindah T068 ke Phase 2 Foundational** (komponen reusable, dipakai >=1 story sebelum US5). Eksekusi: T068 dikerjakan awal (foundational FE) sehingga US1 T035 langsung pakai. US5 T068 hilang / jadi verifikasi. `sierly` kerjakan FormCombobox sebelum US1 FE.

### User Story Dependencies

- **US1 (P1)**: Phase 2 + FormCombobox (T068 naik foundational). No dependency on other stories.
- **US2 (P2)**: Phase 2 (T009/T012). Independently testable.
- **US3 (P3)**: Phase 2 (T008/T013/T019/T023). Independently testable.
- **US4 (P4)**: Phase 2 (T003/T009/T015/T017). Independently testable.
- **US5 (P5)**: Phase 2 audit (T012–T015) + FE breadcrumb. Independently testable.

### Within Each User Story

- Tests (TDD) WAJIB ditulis lebih dulu `zahiira`, konfirmasi FAIL (Red) sebelum implementasi
- Implementation `ammar` (BE) / `sierly` (FE) hingga test Green
- Commit setelah task/kelompok logis selesai (Conventional Commits, no AI attribution)
- Story complete sebelum next priority (atau paralel bila tim kapasitas)

### Parallel Opportunities

- Phase 1: T005, T006, T007 (factories) paralel — file berbeda
- Phase 2: T010, T011, T023 paralel (independent file); T019 paralel bila tidak konflik resource
- User Stories: US2, US3, US4, US5 dapat paralel setelah Phase 2 + US1 foundational (tim berbeda)
- Tests per story [P] paralel (file test berbeda)
- FE reusable (T034 formatCurrency, T051 StatusBadge, T068 FormCombobox) paralel — file berbeda

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together (zahiira):
Task: "Feature test create transaksi in tests/Feature/Transaction/CreateTransactionTest.php"
Task: "Feature test booking link in tests/Feature/Transaction/TransactionBookingLinkTest.php"
Task: "Feature test stock in tests/Feature/Transaction/TransactionStockTest.php"
Task: "Feature test exclusive arc in tests/Feature/Transaction/TransactionExclusiveArcTest.php"

# Launch FE reusable + page together (sierly):
Task: "formatCurrency helper in apps/web/src/lib/format.ts"
Task: "FormCombobox in apps/web/src/components/forms/form-combobox.tsx"
Task: "pos/index.tsx edit in apps/web/src/routes/$tenant/clinic/pos/index.tsx"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (migration + factories) — `ammar`
2. Complete Phase 2: Foundational (model/enum/service/action/resource/route/lang) — `ammar` (BE) + `sierly` (FE: FormCombobox T068, formatCurrency T034)
3. Complete Phase 3: US1 (create transaksi + FE POS core) — `zahiira` tests first → `ammar` BE verify → `sierly` FE
4. **STOP and VALIDATE**: quickstart skenario 1, 2 (urutan), 5 (exclusive arc), 10 (FE POS)
5. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → foundation ready
2. + US1 → test independent → MVP (kasir catat transaksi)
3. + US2 → test independent → invoice unik konkuren
4. + US3 → test independent → 3-state pembayaran + sisa bayar
5. + US4 → test independent → soft-delete + restrict
6. + US5 → test independent → audit naratif + breadcrumb
7. Polish → konsistensi + pgsql suite

### Parallel Team Strategy

Dengan multiple developers/agents:

1. Tim completes Setup + Foundational together — `ammar` BE, `sierly` FE (FormCombobox, formatCurrency)
2. Once Foundational done:
   - `ammar` + `zahiira`: US1 (tests + BE verify)
   - `sierly`: US1 FE (pos/index.tsx, transaction-item-list.tsx)
3. US2–US5 paralel bila kapasitas:
   - `zahiira` writes tests paralel per story
   - `ammar` verify BE paralel
   - `sierly` FE US3 (StatusBadge, payment-panel, transactions) + US5 (breadcrumb, FormCombobox refine)

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- TDD WAJIB: `zahiira` tests Red → `ammar`/`sierly` Green → refactor
- Delegasi: BE `ammar`, FE `sierly`, tests `zahiira`, push `haikal` (saat user minta, `/code-review` low)
- Migration SQLite vs PostgreSQL: guard driver; `phpunit.pgsql.xml` WAJIB sebelum rilis
- `ponytail:` sequence invoice (>9999), reconcile paid_amount, restore/forceDelete, refund workflow — add saat butuh
- F0 merge: tabel invoices di-drop, issued_at di transactions, InvoiceController/InvoiceService render dari transaction
- Commit Conventional Commits, no AI attribution, no emoji
- Verify tests fail before implementing; commit after each task/logical group; stop at checkpoint to validate independently
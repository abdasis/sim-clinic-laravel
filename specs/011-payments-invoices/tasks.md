# Tasks: Integritas Item Transaksi, Pembayaran Cicilan & Cetak Invoice

**Input**: Design documents from `specs/011-payments-invoices/`

**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/api-contracts.md, quickstart.md

**Tests**: TDD WAJIB (constitution II). Test tasks oleh `zahiira`, ditulis lebih dulu (Red) sebelum implementasi (Green).

**Organization**: Tasks grouped by user story. BE migration = foundational (blocking). US1 (integritas DB), US2 (cicilan FE), US3 (invoice FE).

**Tim**: BE migration → `ammar`. FE → `sierly`. Tests → `zahiira`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story (US1, US2, US3)
- Include exact file paths

## Path Conventions

- **Backend**: `apps/api/` (Laravel)
- **Frontend**: `apps/web/src/` (TanStack Start)
- **Tests BE**: `apps/api/tests/`
- Monorepo — path relatif root.

---

## Phase 1: Setup

**Purpose**: Tidak ada setup baru — project + infra sudah ada (spec 003–008). Migration integritas = Phase 2 (foundational, blocking US1 test).

- [ ] T001 Verifikasi prasyarat: master produk (007) + layanan (005) + transaksi (008) sudah ter-migrate. Jalankan `php artisan migrate` (user) untuk konfirmasi skema baseline.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migration integritas DB `transaction_items` — WAJIB selesai sebelum US1 test constraint bisa verifikasi. FK restrict juga butuh PostgreSQL.

**⚠️ CRITICAL**: US1 test butuh migration ini. US2/US3 tidak bergantung migration (FE), tapi isolasi phase agar BE migration tidak bentrok.

### Tests for Foundational (Red first)

- [ ] T002 [P] Write test DB CHECK exclusive arc di apps/api/tests/Feature/Transaction/TransactionItemExclusiveArcTest.php — item product_id+service_id terisi → reject; keduanya null → reject; tepat satu → OK. Guard: jalan di PostgreSQL (`phpunit.pgsql.xml`), sqlite skip bila CHECK behavior berbeda.
- [ ] T003 [P] Write test FK restrict master di apps/api/tests/Feature/Transaction/TransactionItemForeignKeyRestrictTest.php — hapus product yang dirujuk item → reject; arsip product → OK item tetap utuh. Guard PostgreSQL only (sqlite tidak support drop FK).
- [ ] T004 [P] Write test tenant invariant di apps/api/tests/Feature/Transaction/TransactionItemTenantInvariantTest.php — item dibuat via `$transaction->items()->create()` → tenant_id == transaction tenant_id. (sqlite OK)

### Implementation for Foundational

- [ ] T005 Create migration apps/api/database/migrations/2026_08_14_*_enforce_transaction_items_integrity.php — tambah CHECK `((product_id IS NULL) <> (service_id IS NULL))` + alter FK `product_id`/`service_id` `nullOnDelete` → `restrictOnDelete` (drop+recreate constraint). Guard driver: PostgreSQL jalankan alter FK; SQLite skip FK alter (SQLite tidak support drop FK). Delegate `ammar`.

**Checkpoint**: Migration ready. `php artisan migrate` (user) + `php artisan test -c phpunit.pgsql.xml --filter="TransactionItem"` sebelum rilis. Constraint restrict + CHECK teruji PostgreSQL.

---

## Phase 3: User Story 1 — Integritas Item Transaksi (Priority: P1) 🎯 MVP

**Goal**: Item transaksi tepat satu produk/layanan, ditegakkan DB + app; snapshot immutable; master restrict; tenant invariant.

**Independent Test**: Sisipkan item ambigu langsung ke basis (meniru jalur non-UI) → ditolak constraint. Lihat quickstart skenario 1–4.

### Tests for User Story 1 (Red first)

- [ ] T006 [P] [US1] Write test snapshot immutability di apps/api/tests/Feature/Transaction/TransactionSnapshotTest.php — ubah master service/product price + arsip → transaction_items.name/unit_price tetap (R6, FR-056). (sqlite OK)
- [ ] T007 [P] [US1] Write test app validation exclusive arc di apps/api/tests/Feature/Transaction/TransactionExclusiveArcTest.php — POST transaksi item product_id+service_id terisi → 422; keduanya null → 422 (TransactionRequest sudah ada, verifikasi tetap jalan). (sqlite OK)

### Implementation for User Story 1

- [ ] T008 [US1] Verifikasi TransactionRequest exclusive arc validation di apps/api/app/Http/Requests/TransactionRequest.php sudah benar (`required_without` + `prohibits`) — tidak ada perubahan bila sudah (konfirmasi dari eksplorasi). Bila ada edge case validasi (items kosong, dst), perbaiki. Delegate `ammar`.
- [ ] T009 [US1] Verifikasi snapshot path: tidak ada code yang sync `transaction_items.name`/`unit_price` ke master setelah create. Grep `transaction_items.*update|items.*name.*=` — konfirmasi hanya create yang set snapshot. Delegate `ammar`.

**Checkpoint**: US1 fully functional. CHECK constraint + FK restrict + app validation + snapshot immutable + tenant invariant. Test PostgreSQL: `php artisan test -c phpunit.pgsql.xml --filter="TransactionItem|TransactionSnapshot|TransactionExclusiveArc"`.

---

## Phase 4: User Story 2 — Pembayaran Cicilan Bertahap (Priority: P2)

**Goal**: Halaman detail transaksi + catat cicilan bertahap + riwayat pembayaran + sisa + overpaid warning.

**Independent Test**: Buat transaksi subtotal 300rb, catat 3 pembayaran parsial → paid_amount akumulatif, status unpaid→partially_paid→paid, riwayat lengkap. Lihat quickstart skenario 5.

**Backend**: Sudah ada (PayTransactionAction sync, PaymentController, PaymentRequest, PaymentResource, TransactionController@show load payments). Tidak ada task BE baru.

### Tests for User Story 2 (Red first)

- [ ] T010 [P] [US2] Write feature test cicilan flow di apps/api/tests/Feature/Transaction/PaymentCicilanTest.php — 3 pembayaran parsial subtotal 300rb → paid_amount akumulatif (0→100k→200k→300k), status unpaid→partially_paid→paid; overpaid → meta overpaid=true; riwayat 3 entry. (sqlite OK) Delegate `zahiira`.
- [ ] T011 [P] [US2] Write FE test (vitest) halaman cicilan di apps/web/src/routes/$tenant/clinic/pos/transactions/$id.test.tsx — render detail + form + riwayat; mock GET transactions/{id} + POST payments; verifikasi sisa + status badge + riwayat. Delegate `zahiira`.

### Implementation for User Story 2

- [ ] T012 [US2] Create route halaman detail transaksi apps/web/src/routes/$tenant/clinic/pos/transactions/$id.tsx — file-based route. Fetch `GET /{tenant}/clinic/transactions/{id}` via TanStack Query. Render: ClinicBreadcrumb (Beranda Klinik > Transaksi > {invoice_number}), header (invoice_number, pasien, cashier, StatusBadge payment_status, issued_at), ringkasan finansial (subtotal/paid_amount/outstanding_amount via formatCurrency tabular-nums), tombol "Cetak Invoice" link ke pos/invoices/$id. Delegate `sierly`.
- [ ] T013 [P] [US2] Create payment form component apps/web/src/routes/$tenant/clinic/pos/components/payment-form.tsx — reuse FormSelect (method: cash/transfer/qris/debit) + FormInput (amount, type number min>0) + FormInput (paid_at, type datetime-local, default now) + FormSubmit. useForm zod schema (method required, amount gt:0, paid_at required date) + applyServerErrors untuk 422. POST `/{tenant}/clinic/transactions/{id}/payments` via apiPost; invalidasi query transactions/{id}. Tampilkan peringatan overpaid bila meta.overpaid=true (alert/inline). Delegate `sierly`.
- [ ] T014 [P] [US2] Create payment history component apps/web/src/routes/$tenant/clinic/pos/components/payment-history.tsx — Table/DataTable daftar payments: method_label, amount (formatCurrency tabular-nums), paid_at (locale id-ID), terurut desc. Empty state "Belum ada pembayaran." Reuse DataTable primitives bila sesuai, atau Table ui sederhana. Delegate `sierly`.
- [ ] T015 [US2] Integrate payment-form + payment-history ke halaman detail apps/web/src/routes/$tenant/clinic/pos/transactions/$id.tsx — form di atas, riwayat di bawah. Refresh data via TanStack Query invalidation setelah POST sukses. Delegate `sierly`.
- [ ] T016 [US2] Add i18n keys di apps/api/lang/id/clinic.php (dan en fallback bila ada) — `pos.payment_history`, `pos.record_payment`, `pos.overpaid_warning`, `pos.no_payments`, `pos.view_invoice`. Value Indonesia semi-formal friendly. Delegate `ammar` (lang file BE — sumber translation via GET /translations).
- [ ] T017 [US2] Regenerate route tree: `cd apps/web && bun run generate-routes` (user jalankan) setelah route file $id.tsx ditambah. Verifikasi `npx tsc --noEmit --incremental` (user).

**Checkpoint**: US2 fully functional. Halaman detail + cicilan bertahap + riwayat + overpaid warning + breadcrumb. FE test jalan.

---

## Phase 5: User Story 3 — Cetak Invoice Lengkap (Priority: P3)

**Goal**: FE invoice render items + payments dari relasi (R4 lengkap). Konten selalu reflect transaksi terbaru.

**Independent Test**: Buka invoice transaksi dengan item + pembayaran → semua tampil akurat. Lihat quickstart skenario 6.

**Backend**: Sudah ada (InvoiceController + InvoiceService::render dari relasi + blade view). Tidak ada task BE baru.

### Tests for User Story 3 (Red first)

- [ ] T018 [P] [US3] Write FE test (vitest) invoice render payments di apps/web/src/routes/$tenant/clinic/pos/invoices/$id.test.tsx — mock GET transactions/{id} dengan 2 item + 2 payments; verifikasi section pembayaran tampil (method, amount, paid_at) + total paid + outstanding + items + print button. Delegate `zahiira`.

### Implementation for User Story 3

- [ ] T019 [US3] Edit apps/web/src/routes/$tenant/clinic/pos/invoices/$id.tsx — tambah `payments` + `paid_amount` + `outstanding_amount` ke interface InvoiceData. Tambah section pembayaran (method_label, amount formatCurrency, paid_at locale) + total dibayar + sisa (bila >0). Posisi: setelah tabel items, sebelum/sekitar total. Reuse formatCurrency. Pertahankan `print:hidden` pada elemen non-cetak + `window.print()`. Breadcrumb sudah benar (tidak diubah). Delegate `sierly`.
- [ ] T020 [US3] Add i18n keys di apps/api/lang/id/clinic.php — `invoice.payments`, `invoice.paid_amount`, `invoice.outstanding`. Delegate `ammar`.

**Checkpoint**: US3 fully functional. Invoice render items + payments (R4 lengkap FE). Print jalan.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Validasi lintas story + constraint rilis.

- [ ] T021 [P] Run quickstart.md validation — skenario 1–6 end-to-end. User jalankan FE dev + BE serve.
- [ ] T022 [P] Run PostgreSQL test suite: `cd apps/api && php artisan test -c phpunit.pgsql.xml` (user) — verifikasi CHECK + FK restrict + cicilan + snapshot. WAJIB sebelum rilis.
- [ ] T023 [P] Run sqlite test suite: `cd apps/api && php artisan test` (user) — cicilan flow + app validation + tenant invariant (constraint restrict skip, OK).
- [ ] T024 [P] Run FE typecheck: `cd apps/web && npx tsc --noEmit --incremental` (user) — verifikasi route + komponen baru tidak ada type error.
- [ ] T025 Verifikasi komponen reusable forms/datatable tidak terduplikasi — grep konfirmasi tidak ada file baru di `components/forms/` atau `components/datatable/` (YAGNI, reuse as-is). Bila implementasi menemukan pola reusable 2+ konsumen, baru extract.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001 verifikasi baseline — no real work.
- **Foundational (Phase 2)**: T002–T004 tests (Red) paralel → T005 migration (Green). BLOCKS US1 test verifikasi.
- **US1 (Phase 3)**: T006–T007 tests paralel → T008–T009 verifikasi. Bergantung Phase 2 migration.
- **US2 (Phase 4)**: T010–T011 tests paralel → T012 route → T013/T014 komponen paralel → T015 integrate → T016 i18n → T017 regen route. Tidak bergantung Phase 2/3 (FE, backend sudah ada). Bisa paralel dengan US1.
- **US3 (Phase 5)**: T018 test → T019 edit invoice → T020 i18n. Tidak bergantung US1/US2. Bisa paralel.
- **Polish (Phase 6)**: T021–T025 validasi semua. Bergantung semua story selesai.

### User Story Dependencies

- **US1 (P1)**: Phase 2 migration → US1. Independen dari US2/US3.
- **US2 (P2)**: Backend sudah ada. FE saja. Independen, bisa paralel US1.
- **US3 (P3)**: Backend sudah ada. FE saja. Independen, bisa paralel US1/US2.

### Parallel Opportunities

- T002, T003, T004 (foundational tests) — file berbeda, paralel (`zahiira`).
- T006, T007 (US1 tests) — paralel.
- T010, T011 (US2 tests BE + FE) — paralel.
- T013, T014 (US2 komponen) — file berbeda, paralel (`sierly`).
- US1 (BE), US2 (FE), US3 (FE) — tim berbeda, paralel penuh setelah Phase 2.

---

## Parallel Example: US2 (FE)

```bash
# Tests US2 (zahiira):
Task: "Feature test cicilan flow in apps/api/tests/Feature/Transaction/PaymentCicilanTest.php"
Task: "FE test halaman cicilan in apps/web/src/routes/$tenant/clinic/pos/transactions/$id.test.tsx"

# Komponen US2 (sierly, paralel):
Task: "Create payment form in apps/web/src/routes/$tenant/clinic/pos/components/payment-form.tsx"
Task: "Create payment history in apps/web/src/routes/$tenant/clinic/pos/components/payment-history.tsx"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1: Verifikasi baseline (T001).
2. Phase 2: Tests Red (T002–T004) → migration Green (T005). `php artisan migrate` (user).
3. Phase 3: US1 tests Red (T006–T007) → verifikasi app validation + snapshot (T008–T009).
4. **STOP and VALIDATE**: quickstart skenario 1–4. PostgreSQL test suite.
5. Integritas DB terjamin. Deploy/demo bila ready.

### Incremental Delivery

1. Foundational + US1 → integritas data (MVP).
2. + US2 → alur cicilan FE lengkap.
3. + US3 → invoice cetak lengkap (R4 FE).
4. Polish → validasi rilis (PostgreSQL suite WAJIB).

### Parallel Team Strategy

- `ammar`: T005 migration (Phase 2) → T008/T009 verifikasi (US1) → T016/T020 i18n (US2/US3).
- `zahiira`: T002–T004 (Phase 2 tests) → T006/T007 (US1) → T010/T011 (US2) → T018 (US3).
- `sierly`: T012–T015 (US2) → T019 (US3). Mulai FE saat backend 008 sudah ada (tidak tunggu Phase 2).

---

## Notes

- TDD WAJIB: test Red sebelum implementasi Green. Constitution II.
- BE inti dari 008 tidak ditulis ulang — verifikasi only (T008/T009).
- FE reuse forms/datatable/StatusBadge/formatCurrency/ClinicBreadcrumb as-is (YAGNI).
- Constraint restrict + CHECK = PostgreSQL only. SQLite skip FK alter. WAJIB `phpunit.pgsql.xml` sebelum rilis.
- Tidak auto-run build/dev/migrate/test tanpa perintah user. Konfirmasi sendiri.
- Commit per task/kelompok logis. Conventional Commits, no AI attribution.
- Push → `haikal` via `/code-review` low, hanya saat user minta "push".
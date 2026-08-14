# Tasks: Master Produk Klinik

**Input**: Design documents from `/specs/007-product-master/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/products-api.md, quickstart.md

**Tests**: TDD WAJIB (konstitusi II). Test tasks ditulis lebih dulu (Red) oleh agent `zahiira` sebelum implementasi (Green). Backend authoring → `ammar`. Frontend authoring → `sierly`.

**Organization**: Tasks grouped by user story (spec.md: US1 P1, US2 P2, US3 P3, US4 P4) for independent implementation & testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g. US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

- **Web app monorepo**: `apps/api/` (backend Laravel), `apps/web/` (frontend TanStack Start). Paths below repo-relative.
- Backend code: `apps/api/app/...`, migrations `apps/api/database/migrations/`, tests `apps/api/tests/...`, lang `apps/api/lang/id/`.
- Frontend code: `apps/web/src/...`, routes `apps/web/src/routes/$tenant/clinic/products/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Foundation for all user stories — migration FK + factory test + layering skeleton.

- [ ] T001 Create migration to change FK `stock_movements.product_id` cascadeOnDelete → restrictOnDelete and `transaction_items.product_id` nullOnDelete → restrictOnDelete in apps/api/database/migrations/2026_08_14_restrict_product_foreign_keys.php (R2, FR-068)
- [ ] T002 [P] Create ProductFactory in apps/api/database/factories/ProductFactory.php (R9, bila belum ada) with tenant_id, name, unit, stock_balance, min_threshold, price, status default active — for test seeding
- [ ] T003 Create StockMovementFactory in apps/api/database/factories/StockMovementFactory.php (R9, bila belum ada) — for mutasi test

**Checkpoint**: Migration + factories ready. Run `php artisan migrate` setelah implementasi.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Layering Service/Action + `stock_balance` bukan input — MUST complete before user stories.

**⚠️ CRITICAL**: Modul produk saat ini menyentuh Eloquent langsung dari controller tanpa Service/Action/audit. Layering + audit log diperbaiki di sini sebelum fitur story.

- [ ] T004 Create ArchiveProductAction in apps/api/app/Actions/Product/ArchiveProductAction.php — `execute(Product): Product` set status=archived + LogAuditAction event `product.archived` narasi "Mengarsipkan produk {name}" properties status old→new (R4, mirror ArchiveServiceAction + audit). Inject LogAuditAction (bukan Service).
- [ ] T005 Create CreateProductAction in apps/api/app/Actions/Product/CreateProductAction.php — `execute(array $attributes): Product` Product::create (saldo default 0 via DB) + LogAuditAction event `product.created` narasi "Membuat produk {name}" full attributes (R4)
- [ ] T006 Create UpdateProductAction in apps/api/app/Actions/Product/UpdateProductAction.php — `execute(Product, array $attributes): Product` capture old attributes → update + LogAuditAction event `product.updated` narasi "Memperbarui produk {name}" old/new diff (R4)
- [ ] T007 Create ProductService in apps/api/app/Services/ProductService.php — orkestrasi `create(array)/update(Product,array)/archive(Product)` memanggil Action terkait. DILARANG menyentuh DB langsung (CLAUDE.md). Method <100 baris. (R4)
- [ ] T008 Edit ProductRequest in apps/api/app/Http/Requests/ProductRequest.php — hapus `stock_balance` dari rules() + attributes() (R3, FR-060/063). Tetap: name, unit, min_threshold, price, status nullable enum.
- [ ] T009 Edit ProductController in apps/api/app/Http/Controllers/ProductController.php — store/update/destroy WAJIB panggil ProductService (bukan Eloquent langsung); index default filter `status=active` bila filter[status] tidak dikirim (R8, FR-067); index/show tetap read langsung (exception CLAUDE.md). Method <100 baris.
- [ ] T010 [P] Edit lang/id/product.php — tambah key: edit, archive, archive_confirm, status_active, status_archived, status_all (R11)
- [ ] T011 [P] Edit lang/id/inventory.php — isi key hilang yang sudah dipakai FE: product, history, created_at, movement_recorded (R11)

**Checkpoint**: Foundation ready — layering lurus (Controller→Service→Action), `stock_balance` bukan input, default active filter. User story implementation dapat mulai.

---

## Phase 3: User Story 1 - Kelola Daftar Produk (Priority: P1) MVP

**Goal**: Admin dapat membuat, melihat, memperbarui produk; saldo diawali 0; validasi harga/satuan; pencarian + pengurutan server-side.

**Independent Test**: Buat satu produk baru tanpa field saldo → muncul di daftar dengan saldo 0; ubah harga → tersimpan. Tanpa modul transaksi/stok.

### Tests for User Story 1 (TDD — write FIRST, must FAIL)

- [ ] T012 [P] [US1] Feature test: create product tanpa stock_balance → 201, stock_balance=0, status=active, audit_logs row `product.created` narasi mengandung nama — in apps/api/tests/Feature/Product/CreateProductTest.php
- [ ] T013 [P] [US1] Feature test: create product dengan stock_balance di body → diabaikan, saldo tetap 0 (FR-060, SC-007) — in apps/api/tests/Feature/Product/StockBalanceNotInputTest.php
- [ ] T014 [P] [US1] Feature test: update product dengan stock_balance di body → saldo tidak berubah (FR-063) — in apps/api/tests/Feature/Product/UpdateProductTest.php
- [ ] T015 [P] [US1] Feature test: validation price negatif/name kosong/unit kosong → 422 — in apps/api/tests/Feature/Product/ProductValidationTest.php
- [ ] T016 [P] [US1] Feature test: index default hanya active (arsip tidak muncul), filter[status]=archived/all bekerja (R8) — in apps/api/tests/Feature/Product/ListProductTest.php
- [ ] T017 [P] [US1] Feature test: search + sort + paginate server-side — in apps/api/tests/Feature/Product/ProductDataTableTest.php
- [ ] T018 [P] [US1] Feature test: tenant isolation — produk tenant A tidak terlihat tenant B, GET id tenant lain → 404 — in apps/api/tests/Feature/Product/ProductTenantIsolationTest.php
- [ ] T019 [P] [US1] Feature test: permission — role tanpa izin product → GET/POST/PUT/DELETE 403 — in apps/api/tests/Feature/Product/ProductPermissionTest.php
- [ ] T020 [P] [US1] Unit test: CreateProductAction + UpdateProductAction + ArchiveProductAction menghasilkan LogAuditAction row naratif — in apps/api/tests/Unit/Product/ProductActionAuditTest.php

### Implementation for User Story 1

- [ ] T021 [US1] (Verifikasi) Konfirmasi ProductController::store/update kini via ProductService (T009 selesai) — jalankan T012-T020 hingga Green. Tidak ada file baru bila T004-T009 sudah benar.
- [ ] T022 [P] [US1] FE: edit product-form-modal.tsx in apps/web/src/routes/$tenant/clinic/products/components/product-form-modal.tsx — hapus field stock_balance (FR-060); ubah dari create-only → create+edit (prefill saat edit, PUT saat edit, POST saat create); field: name, unit, min_threshold, price. Pakai FormInput/FormSubmit/useForm eksisting (R10).
- [ ] T023 [US1] FE: edit products/index.tsx in apps/web/src/routes/$tenant/clinic/products/index.tsx — tambah kolom aksi per-row "Ubah" (buka modal edit prefill via product-form-modal edit mode); kirim filter[status] eksplisit agar arsip tampil di halaman master (R8). Pakai DataTable eksisting. (Tidak sentuh breadcrumb — US4.)

**Checkpoint**: US1 fully functional & testable independently. Admin kelola produk, saldo default 0, validasi, datatable, tenant isolation.

---

## Phase 4: User Story 2 - Indikator Stok Menipis (Priority: P2)

**Goal**: Indikator `is_low_stock` (stock_balance <= min_threshold) tampil akurat di daftar produk termasuk edge equality.

**Independent Test**: Set min_threshold > saldo → indikator muncul; saldo = threshold → tetap muncul (kondisi `<=`); saldo > threshold → tidak muncul.

### Tests for User Story 2 (TDD — write FIRST, must FAIL)

- [ ] T024 [P] [US2] Unit test: Product is_low_stock — saldo < threshold → true; saldo = threshold → true (equality); saldo > threshold → false; min_threshold=0 & saldo=0 → true (FR-065) — in apps/api/tests/Unit/Product/IsLowStockTest.php
- [ ] T025 [P] [US2] Feature test: ProductResource mengandung is_low_stock benar untuk ketiga kasus — in apps/api/tests/Feature/Product/IsLowStockResourceTest.php

### Implementation for User Story 2

- [ ] T026 [US2] (Verifikasi) `is_low_stock` sudah computed di Product model (`getIsLowStockAttribute`) + appended di ProductResource — konfirmasi T024/T025 Green tanpa kode baru (R6). Bila test gagal, perbaiki model/resource.
- [ ] T027 [P] [US2] FE: edit products/index.tsx — Badge "Stok menipis" (variant destructive) pada baris is_low_stock=true sudah ada; verifikasi tampil untuk edge equality (R10 finishing). Pakai Badge eksisting.

**Checkpoint**: US2 functional. Indikator low-stock akurat termasuk equality.

---

## Phase 5: User Story 3 - Arsip Produk (Priority: P3)

**Goal**: Arsip produk via status=archived (bukan hapus); produk terarsip tersembunyi dari pilihan POS baru; hard-delete direferensi diblokir restrict; activity log arsip; snapshot transaction_items utuh.

**Independent Test**: Arsipkan produk aktif → tidak muncul di pilihan POS baru, tetap terlihat di daftar (filter arsip); hard-delete produk dengan mutasi/transaksi → QueryException; snapshot nama/harga transaksi lama utuh setelah master diubah/arsip.

### Tests for User Story 3 (TDD — write FIRST, must FAIL)

- [ ] T028 [P] [US3] Feature test: DELETE product → 200 status=archived, audit_logs row `product.archived` narasi "Mengarsipkan produk {name}" — in apps/api/tests/Feature/Product/ArchiveProductTest.php
- [ ] T029 [P] [US3] Feature test: hard-delete produk dengan stock_movements → QueryException (FK restrict, R2) — in apps/api/tests/Feature/Product/RestrictDeleteTest.php
- [ ] T030 [P] [US3] Feature test: hard-delete produk dengan transaction_items → QueryException (FK restrict, R2) — same RestrictDeleteTest.php atau file terpisah
- [ ] T031 [P] [US3] Feature test: produk arsip tidak muncul di GET products default active (FR-067) — in apps/api/tests/Feature/Product/ArchiveProductTest.php
- [ ] T032 [P] [US3] Feature test: snapshot immutability — ubah/arsip master produk → transaction_items.name & unit_price lama tetap utuh (FR-069, R5) — in apps/api/tests/Feature/Product/SnapshotImmutabilityTest.php
- [ ] T033 [P] [US3] Unit test: StockService::adjust() mutasi in/out mengubah stock_balance + balance_after konsisten (R7) — in apps/api/tests/Unit/Product/StockServiceAdjustTest.php

### Implementation for User Story 3

- [ ] T034 [US3] (Verifikasi) ArchiveProductAction (T004) + restrictOnDelete migration (T001) selesai → jalankan T028-T033 hingga Green. Konfirmasi tidak ada path hard-delete endpoint (R7).
- [ ] T035 [US3] FE: create product-actions-cell.tsx in apps/web/src/routes/$tenant/clinic/products/components/product-actions-cell.tsx — row actions "Ubah" (buka modal edit) + "Arsipkan" (alert confirm → DELETE). Mirror service-actions-cell pattern (R10). Pakai Button/Tooltip/AlertDialog eksisting.
- [ ] T036 [US3] FE: edit products/index.tsx — integrasikan product-actions-cell sebagai kolom aksi; tambah DataTableFacetedFilter status (active/archived/all) di toolbar (R10). Pakai DataTableFacetedFilter eksisting.

**Checkpoint**: US3 functional. Arsip + restrict + snapshot + low-stock all verified.

---

## Phase 6: User Story 4 - Breadcrumb Navigasi (Priority: P4)

**Goal**: Breadcrumb "Beranda Klinik > Produk" di halaman master produk, item terakhir bukan link, induk link ke `/$tenant/clinic`.

**Independent Test**: Buka `/{tenant}/clinic/products` → breadcrumb menampilkan jalur, "Produk" item terakhir (bukan link), "Beranda Klinik" link ke rute induk.

### Implementation for User Story 4

- [ ] T037 [US4] FE: verifikasi/perbaiki breadcrumb di products/index.tsx — ClinicBreadcrumb items: {label: tenant, to: "/$tenant/clinic", params:{tenant}}, {label: t("clinic.clinic")}, {label: t("product.title")} (item terakhir, no `to`). Saat ini sudah benar (tidak self-link) — konfirmasi & sesuaikan label sesuai konvensi (US4).

**Checkpoint**: US4 functional. Breadcrumb benar di seluruh halaman produk.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Verifikasi lintas story + finishing.

- [ ] T038 [P] Run `php artisan test --filter=Product` di apps/api — semua test Product/StockMovement Green (R9)
- [ ] T039 [P] Run `npx tsc --noEmit --incremental` di apps/web — FE type check lulus (T022/T023/T035/T036)
- [ ] T040 [P] FE finishing: tooltip + state lengkap pada product-actions-cell (hover/focus/disabled), aria-label aksi (CLAUDE.md authoring discipline FE)
- [ ] T041 [P] FE: generate ulang route tree `bun run generate-routes` di apps/web bila ada perubahan route file (tidak ada route baru di spec ini — skip bila tidak perlu)
- [ ] T042 Run quickstart.md validation — 9 skenario end-to-end (quickstart.md)
- [ ] T043 [P] Verifikasi tidak ada path update langsung stock_balance di luar StockService::adjust() — grep `stock_balance` di app/Http pastikan hanya via Action/StockService (SC-007, FR-063)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately. T001 independent; T002/T003 [P].
- **Foundational (Phase 2)**: Depends on Phase 1. T004-T006 [P] (3 Action, file berbeda) → T007 (Service, depends on T004-T006) → T008/T009 (controller+request, depends T007). T010/T011 [P] lang. **BLOCKS all user stories.**
- **User Stories (Phase 3-6)**: All depend on Foundational (Phase 2).
  - US1 (P3): test-first (T012-T020 [P]) → impl verifikasi (T021) → FE (T022/T023)
  - US2 (P4): test-first (T024-T025 [P]) → verifikasi (T026) → FE (T027)
  - US3 (P5): test-first (T028-T033 [P]) → verifikasi (T034) → FE (T035/T036)
  - US4 (P6): FE-only (T037), depends on US1 page exists
- **Polish (Phase 7)**: Depends on desired user stories complete.

### User Story Dependencies

- **US1 (P1)**: After Foundational. No dependency on other stories. (FE edit form/index.)
- **US2 (P2)**: After Foundational. Independent — `is_low_stock` sudah ada di model/resource; verifikasi + FE badge.
- **US3 (P3)**: After Foundational. Depends on ArchiveProductAction (T004) + restrict migration (T001) di Foundational. Independent dari US1/US2 secara logika, tapi FE row actions (T035) menyentuh file yang sama dengan US1 (index.tsx) → koordinasi edit.
- **US4 (P4)**: After US1 (halaman produk ada). FE-only.

### Within Each User Story

- Tests MUST be written first (Red) oleh `zahiira` sebelum impl.
- Model/Action before Service (Foundational).
- Service before Controller wiring.
- Test Green before FE integration.
- FE: form components eksisting sebelum komponen domain baru.

### Parallel Opportunities

- Phase 1: T002 || T003 (factories, file berbeda)
- Phase 2: T004 || T005 || T006 (3 Action, file berbeda) → T007; T010 || T011 (lang)
- US1 tests: T012-T020 semua [P] (file test berbeda) — fan-out ke `zahiira`
- US2 tests: T024 || T025
- US3 tests: T028-T033 semua [P]
- BE authoring (`ammar`) dan FE authoring (`sierly`) paralel setelah test Green — BE T004-T009, FE T022/T023/T035-T037 di thread terpisah

---

## Parallel Example: User Story 1

```bash
# zahiira: launch all US1 tests together (Red first):
Task: "T012 CreateProductTest"
Task: "T013 StockBalanceNotInputTest"
Task: "T014 UpdateProductTest"
Task: "T015 ProductValidationTest"
Task: "T016 ListProductTest"
Task: "T017 ProductDataTableTest"
Task: "T018 ProductTenantIsolationTest"
Task: "T019 ProductPermissionTest"
Task: "T020 ProductActionAuditTest"

# ammar: implement BE (T004-T009 Foundational) to turn tests Green
# sierly: FE T022 (form modal) + T023 (index actions) in parallel after Green
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (migration + factories)
2. Complete Phase 2: Foundational (Service + 3 Action + ProductRequest + Controller) — CRITICAL, blocks all
3. Complete Phase 3: US1 (tests Red → Green → FE form+index)
4. **STOP and VALIDATE**: quickstart.md skenario 1-3 (CRUD + saldo default 0 + tenant isolation)
5. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → layering lurus, `stock_balance` bukan input
2. + US1 → kelola produk MVP (test independently)
3. + US2 → indikator low-stock (test independently)
4. + US3 → arsip + restrict + snapshot (test independently)
5. + US4 → breadcrumb (FE finishing)
6. Polish → quickstart validation

### Parallel Team Strategy

- `zahiira`: semua test task (Red) per story, fan-out [P]
- `ammar`: BE Foundational (T004-T009) → verifikasi Green per story
- `sierly`: FE (T022/T023/T035-T037) setelah BE Green
- `haikal`: review `/code-review` level low sebelum push (hanya saat user minta push BE)

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to user story for traceability
- Test FIRST (Red) sebelum implementasi (Green) — konstitusi II TDD non-negotiable
- Tidak ada entity/tabel/kolom baru — revisi FK + layering + FE saja
- Tidak ada form komponen baru di components/forms/ — pakai eksisting (R10)
- Activity log via LogAuditAction di Action (R4), bukan controller
- Commit after each task or logical group; NO AI attribution; Conventional Commits
- Backend modul produk sebagian besar sudah ada — banyak task US1/US2 adalah verifikasi (test Green) bukan kode baru
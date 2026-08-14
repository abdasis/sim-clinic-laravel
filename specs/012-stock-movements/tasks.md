# Tasks: Integritas Mutasi Stok & Riwayat Stok Produk

**Input**: Design documents from `specs/012-stock-movements/`

**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/api-contracts.md, quickstart.md

**Tests**: TDD WAJIB (constitution II). Test tasks oleh `zahiira`, ditulis lebih dulu (Red) sebelum implementasi (Green).

**Organization**: Tasks grouped by user story. BE foundational = migration revisi morph + morph map (blocking US1). US1 (jejak audit + guard saldo), US2 (race condition), US3 (riwayat + reverse lookup FE).

**Tim**: BE → `ammar`. FE → `sierly`. Tests → `zahiira`.

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

**Purpose**: Tidak ada setup baru — project + infra sudah ada (spec 003–008). Verifikasi baseline skema `stock_movements` eksisting.

- [ ] T001 Verifikasi prasyarat: master produk (007) + transaksi (008) + `StockService::adjust()` + model `StockMovement` + enum `StockMovementType` + policy + request + migration `2026_07_06_140100_create_stock_movements_table` + FK restrict migration `2026_08_14_060000_restrict_product_foreign_keys` sudah ada. Jalankan `php artisan migrate` (user) untuk konfirmasi skema baseline.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Revisi skema `stock_movements` morph + daftarkan morph map konsisten. WAJIB selesai sebelum US1/US2/US3 test verifikasi morph + reverse lookup.

**⚠️ CRITICAL**: US1/US2 test butuh morph map aktif + composite index. US3 reverse lookup butuh composite index. Migration revisi blocking.

### Tests for Foundational (Red first)

- [ ] T002 [P] Write test composite index morph reverse lookup di apps/api/tests/Feature/Inventory/StockMovementMorphIndexTest.php — insert mutasi `sold_pos` related ke Transaction + mutasi `in` (related null); query `where('related_type', 'transaction')->where('related_id', $tx->id)` → hanya mutasi transaksi terkait. Guard: PostgreSQL (`phpunit.pgsql.xml`) untuk verifikasi index; sqlite OK untuk query logic. Delegate `zahiira`.
- [ ] T003 [P] Write test morph map alias di apps/api/tests/Feature/Inventory/StockMovementMorphMapTest.php — `StockService::adjust()` dengan `$related=$transaction` → `related_type` = 'transaction' (alias), bukan FQCN `App\Models\Transaction`. Guard: `Relation::enforceMorphMap` aktif. (sqlite OK) Delegate `zahiira`.

### Implementation for Foundational

- [ ] T004 [P] Edit apps/api/app/Providers/AppServiceProvider.php — tambah `Relation::enforceMorphMap(['transaction' => \App\Models\Transaction::class])` di `boot()`. Import `Illuminate\Database\Eloquent\Relations\Relation`. Alias `'transaction'` stabil, bukan FQCN. Delegate `ammar`.
- [ ] T005 Create migration apps/api/database/migrations/2026_08_14_140000_revise_stock_movements_related_morph.php — `up()`: `dropColumns(['related_type', 'related_id'])` lalu `nullableMorphs('related')` (buat `related_type` + `related_id` + composite index `(related_type, related_id)` otomatis). Pertahankan index `(tenant_id, product_id, created_at)` eksisting. `down()`: reverse — `dropMorphs('related')` + restore manual `string('related_type')->nullable()` + `unsignedBigInteger('related_id')->nullable()`. Guard driver tidak perlu (morph bukan FK). Delegate `ammar`.
- [ ] T006 [US1] Edit apps/api/app/Models/StockMovement.php — hapus `related_type` + `related_id` dari `$fillable` (morph `related()` kelola sendiri via `related()` relation; fillable manual tidak dipakai lagi karena `StockService` set via array create — verifikasi: bila `StockService::adjust()` masih set `related_type`/`related_id` explicit di array create, pertahankan di fillable ATAU ubah `StockService` pakai `->related()->associate()`. Keputusan: tetap set explicit di `StockService` (konsisten dengan array create), jadi `related_type`/`related_id` tetap di fillable. Tidak ada perubahan model bila fillable sudah benar — konfirmasi. Delegate `ammar`.

**Checkpoint**: Migration + morph map ready. `php artisan migrate` (user) + `php artisan test -c phpunit.pgsql.xml --filter="StockMovementMorph"` sebelum rilis. Composite index + alias morph teruji PostgreSQL.

---

## Phase 3: User Story 1 — Jejak Audit + Saldo Konsisten (Priority: P1) 🎯 MVP

**Goal**: Setiap mutasi `StockService::adjust()` tinggalkan jejak `balance_after` immutable, `stock_balance` sinkron, audit log naratif, guard saldo negatif.

**Independent Test**: Catat stok masuk/keluar beberapa kali → `balance_after` konsisten ± quantity, `stock_balance` = saldo mutasi terakhir, audit log tercatat. Lihat quickstart skenario 1.

### Tests for User Story 1 (Red first)

- [ ] T007 [P] [US1] Write test saldo konsisten di apps/api/tests/Feature/Inventory/StockMovementBalanceTest.php — produk saldo 0 → adjust `in` qty 10 → balance_after=10 + stock_balance=10 → adjust `out_manual` qty 3 → balance_after=7 + stock_balance=7. Verifikasi tiap mutasi `balance_after` = saldo sebelumnya ± qty. (sqlite OK) Delegate `zahiira`.
- [ ] T008 [P] [US1] Write test immutability di apps/api/tests/Feature/Inventory/StockMovementImmutabilityTest.php — upaya `UPDATE stock_movements SET quantity=99` via raw query setelah create → buktikan tidak ada path app update (tidak ada route update/delete, model `$timestamps=false`); verifikasi jejak tetap original. (sqlite OK) Delegate `zahiira`.
- [ ] T009 [P] [US1] Write test audit log mutasi di apps/api/tests/Feature/Inventory/StockMovementAuditLogTest.php — adjust `out_manual` qty 3 produk "Serum" → `Activity::where('event','inventory.stock.adjusted')->latest()->first()` punya description "Menyesuaikan stok Serum — Stok Keluar 3" + properties full (product_id, type, quantity, balance_after, note, related_type, related_id). (sqlite OK) Delegate `zahiira`.
- [ ] T010 [P] [US1] Write test guard saldo negatif di apps/api/tests/Feature/Inventory/StockMovementNegativeBalanceTest.php — produk saldo 5 → adjust `out_manual` qty 10 → 422 "Stok produk tidak mencukupi." + stock_balance tetap 5 + tidak ada jejak baru. (sqlite OK) Delegate `zahiira`.

### Implementation for User Story 1

- [ ] T011 [US1] Edit apps/api/app/Services/StockService.php — tambah guard saldo negatif sebelum `StockMovement::create`: bila `!$type->isInbound()` dan `$newBalance < 0` → `abort(422, __('inventory.insufficient_stock'))`. Tambah audit log via `LogAuditAction::handle('inventory.stock.adjusted', $movement, auth()->user(), $context, $description)` setelah `DB::transaction` return. `$context` = full attributes (`product_id`, `type`, `quantity`, `balance_after`, `note`, `related_type`, `related_id`, `tenant_id`, `product_name`). `$description` = `sprintf('Menyesuaikan stok %s — %s %d', $locked->name, $movement->type->label(), $movement->quantity)`. Method tetap <= 100 baris (extract helper bila perlu). Import `LogAuditAction`. Delegate `ammar`.
- [ ] T012 [US1] Add i18n key `inventory.insufficient_stock` = "Stok produk tidak mencukupi." di apps/api/lang/id/inventory.php. Delegate `ammar`.
- [ ] T013 [US1] Verifikasi `StockMovementController@store` response shape konsisten kontrak (data: id, product_id, type, type_label, quantity, balance_after, note, created_at). Tidak ada perubahan bila sudah benar — konfirmasi dari eksplorasi. Delegate `ammar`.

**Checkpoint**: US1 fully functional. Jejak audit + saldo konsisten + guard saldo negatif + audit log naratif. Test: `php artisan test --filter="StockMovementBalance|StockMovementImmutability|StockMovementAuditLog|StockMovementNegativeBalance"`.

---

## Phase 4: User Story 2 — Mutasi Konkuren Aman Race Condition (Priority: P2)

**Goal**: Dua mutasi konkuren pada produk sama tidak saling timpa saldo; row lock + DB transaction dijaga.

**Independent Test**: Picu dua mutasi keluar konkuren → saldo akhir benar, tidak ada mutasi hilang/tertimpa. Lihat quickstart skenario 2.

### Tests for User Story 2 (Red first)

- [ ] T014 [P] [US2] Write test mutasi konkuren di apps/api/tests/Feature/Inventory/StockMovementConcurrentTest.php — produk saldo 10 → dua `StockService::adjust()` `out_manual` qty 3 konkuren (via two threads/processes atau DB transaction nested simulasi) → stock_balance akhir = 4, dua mutasi tercatat, balance_after berurutan tidak sama. Guard: PostgreSQL (`phpunit.pgsql.xml`) untuk verifikasi row lock real; sqlite OK untuk logic. Delegate `zahiira`.
- [ ] T015 [P] [US2] Write test rollback transaksi idempoten di apps/api/tests/Feature/Transaction/CancelTransactionIdempotencyTest.php — batalkan transaksi → stok kembali (rollback); batalkan lagi → 409 already_cancelled, tidak ada mutasi rollback ketiga. (sqlite OK — CancelTransactionAction check `cancelled_at` eksisting) Delegate `zahiira`.

### Implementation for User Story 2

- [ ] T016 [US2] Verifikasi `StockService::adjust()` row lock `lockForUpdate()` + `DB::transaction` sudah benar (eksisting). Verifikasi `CancelTransactionAction` check `cancelled_at !== null` → abort 409 (eksisting, idempoten). Tidak ada perubahan bila sudah benar — konfirmasi dari eksplorasi + tests T014/T015 jadi bukti. Bila test T014 gagal (race leak), perbaiki lock scope. Delegate `ammar`.

**Checkpoint**: US2 fully functional. Mutasi konkuren aman + rollback idempoten. Test: `php artisan test -c phpunit.pgsql.xml --filter="StockMovementConcurrent"` + `php artisan test --filter="CancelTransactionIdempotency"`.

---

## Phase 5: User Story 3 — Riwayat Stok + Reverse Lookup (Priority: P3)

**Goal**: Halaman riwayat stok per produk pakai DataTable reusable + kolom transaksi terkait + link + state kosong. Endpoint reverse lookup per transaksi (FR-012).

**Independent Test**: Buka riwayat produk dengan 5 mutasi → DataTable terurut + kolom transaksi link. Reverse lookup transaksi → 2 mutasi (sold_pos + rollback). Lihat quickstart skenario 3 + 6.

### Tests for User Story 3 (Red first)

- [ ] T017 [P] [US3] Write test reverse lookup endpoint di apps/api/tests/Feature/Inventory/StockMovementReverseLookupTest.php — buat transaksi jual qty 2 + batalkan (rollback) → `GET /{tenant}/clinic/transactions/{tx}/stock-movements` → 200, 2 mutasi (sold_pos + rollback), related_type='transaction' related_id=$tx->id. Verifikasi shape sama `indexByProduct`. (sqlite OK) Delegate `zahiira`.
- [ ] T018 [P] [US3] Write test indexByProduct response field transaksi di apps/api/tests/Feature/Inventory/StockMovementIndexTest.php — `GET /{tenant}/clinic/products/{product}/stock-movements` → 200, tiap mutasi punya `related_type` + `related_id` (nullable untuk in/out_manual). Paginasi meta. (sqlite OK) Delegate `zahiira`.
- [ ] T019 [P] [US3] Write FE test (vitest) riwayat stok DataTable di apps/web/src/routes/$tenant/clinic/inventory/components/stock-movement-history.test.tsx — mock GET products/{p}/stock-movements 5 mutasi; verifikasi DataTable render 5 baris + kolom (Waktu, Jenes, Jumlah, Saldo Setelah, Keterangan, Transaksi link) + paginasi. Empty state "Belum ada mutasi stok." Delegate `zahiira`.

### Implementation for User Story 3

- [ ] T020 [US3] Edit apps/api/app/Http/Controllers/StockMovementController.php — tambah method `indexByTransaction(Transaction $transaction)`: authorize `viewAny`; query `StockMovement::where('related_type', $transaction->getMorphClass())->where('related_id', $transaction->id)->latest('created_at')->paginate()`; response shape sama `indexByProduct` + `related_type`/`related_id` field. Delegate `ammar`.
- [ ] T021 [US3] Edit apps/api/routes/api.php — tambah route `Route::get('transactions/{transaction}/stock-movements', [StockMovementController::class, 'indexByTransaction'])` dalam group clinic (auth:sanctum + resolve.tenant + clinic.access gate). Delegate `ammar`.
- [ ] T022 [US3] Edit apps/api/app/Http/Controllers/StockMovementController.php `indexByProduct` — tambah field `related_type` + `related_id` ke response map (saat ini tidak ada). Delegate `ammar`.
- [ ] T023 [P] [US3] Rewrite apps/web/src/routes/$tenant/clinic/inventory/components/stock-movement-history.tsx — ganti Table manual → pakai `DataTable` (`#/components/datatable/datatable.tsx`) + `useReactTable` + `getCoreRowModel` + `getPaginationRowModel` + kolom def. Kolom: Waktu (created_at), Jenes (type_label), Jumlah (quantity, right-align tabular-nums), Saldo Setelah (balance_after, right-align), Keterangan (note ?? '-'), Transaksi (Link ke `/$tenant/clinic/pos/transactions/$id` bila related_type==='transaction' + related_id terisi; null → '-'). State: DataTable skeleton loading, empty "Belum ada mutasi stok." (`t('inventory.empty_movements')`), paginasi via DataTablePagination + meta server-side. Reuse DataTable as-is, tidak buat baru. Delegate `sierly`.
- [ ] T024 [P] [US3] Edit apps/web/src/routes/$tenant/clinic/inventory/components/stock-movement-form.tsx — pastikan feedback saldo negatif: server 422 → `applyServerErrors(form, err.errors)` map ke field quantity + toast error. Reuse FormSelect/FormInput/FormTextarea/FormSubmit/useForm (sudah pakai). Tidak ada perubahan besar bila sudah benar — konfirmasi. Delegate `sierly`.
- [ ] T025 [US3] Add i18n keys di apps/api/lang/id/inventory.php — `empty_movements` = "Belum ada mutasi stok.", `related_transaction` = "Transaksi Terkait". Delegate `ammar`.
- [ ] T026 [US3] (Opsional, bila spec 011 halaman detail transaksi ada) Edit apps/web/src/routes/$tenant/clinic/pos/transactions/$id.tsx — tambah section "Pengaruh Stok" (reverse lookup): `useQuery` GET `/{tenant}/clinic/transactions/{id}/stock-movements` + Table sederhana (type_label, quantity, balance_after, created_at). YAGNI: tidak buat route terpisah. Bila halaman detail transaksi belum ada (spec 011 belum selesai), skip — tunda ke integrasi spec 011. Delegate `sierly`.
- [ ] T027 [US3] Regenerate route tree: `cd apps/web && bun run generate-routes` (user jalankan) bila ada route file baru. Verifikasi `npx tsc --noEmit --incremental` (user). Tidak ada route file FE baru bila T026 skip → skip regen.

**Checkpoint**: US3 fully functional. Riwayat stok DataTable + kolom transaksi link + state kosong. Reverse lookup endpoint + (opsional) section detail transaksi. FE test jalan.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Validasi lintas story + constraint rilis.

- [ ] T028 [P] Run quickstart.md validation — skenario 1–6 end-to-end. User jalankan FE dev + BE serve.
- [ ] T029 [P] Run PostgreSQL test suite: `cd apps/api && php artisan test -c phpunit.pgsql.xml` (user) — verifikasi composite index morph + FK restrict + race condition + guard saldo. WAJIB sebelum rilis.
- [ ] T030 [P] Run sqlite test suite: `cd apps/api && php artisan test` (user) — saldo konsisten + immutability + audit log + guard saldo + reverse lookup logic (FK restrict skip, OK).
- [ ] T031 [P] Run FE typecheck: `cd apps/web && npx tsc --noEmit --incremental` (user) — verifikasi komponen rewrite DataTable tidak ada type error.
- [ ] T032 [P] Verifikasi komponen reusable `components/forms/` + `components/datatable/` tidak terduplikasi — grep konfirmasi tidak ada file baru di kedua folder (YAGNI, reuse as-is per instruksi user). Bila implementasi menemukan pola reusable 2+ konsumen baru, baru extract.
- [ ] T033 Verifikasi morph map tidak melanggar relasi lain — grep `morphTo`/`morphMany` di model lain; konfirmasi `enforceMorphMap` hanya map `transaction` (extensibel bila model lain pakai morph, tambah ke map). `ponytail: audit_logs morph pakai map sama saat migrasi spatie`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001 verifikasi baseline — no real work.
- **Foundational (Phase 2)**: T002–T003 tests (Red) paralel → T004 morph map + T005 migration (paralel, file beda) → T006 model verifikasi. BLOCKS US1/US2/US3 test morph + reverse lookup.
- **US1 (Phase 3)**: T007–T010 tests paralel → T011 service guard+audit → T012 i18n → T013 controller verifikasi. Bergantung Phase 2 (morph map aktif untuk audit log related_type alias).
- **US2 (Phase 4)**: T014–T015 tests paralel → T016 verifikasi. Bergantung Phase 2 (morph map untuk rollback related_type alias). Bisa paralel dengan US1 (file beda: StockService di US1, CancelTransactionAction verify di US2).
- **US3 (Phase 5)**: T017–T019 tests paralel → T020/T021/T022 endpoint+route → T023/T024 FE paralel → T025 i18n → T026 opsional → T027 regen. Bergantung Phase 2 (composite index morph untuk reverse lookup). Bisa paralel dengan US1/US2 (BE endpoint beda file, FE beda file).
- **Polish (Phase 6)**: T028–T033 validasi semua. Bergantung semua story selesai.

### User Story Dependencies

- **US1 (P1)**: Phase 2 morph map → US1. Independen dari US2/US3.
- **US2 (P2)**: Phase 2 morph map → US2 (verifikasi race + idempoten). Independen, bisa paralel US1.
- **US3 (P3)**: Phase 2 composite index → US3 reverse lookup. Independen, bisa paralel US1/US2.

### Parallel Opportunities

- T002, T003 (foundational tests) — file berbeda, paralel (`zahiira`).
- T004 (AppServiceProvider), T005 (migration) — file berbeda, paralel (`ammar`).
- T007, T008, T009, T010 (US1 tests) — file berbeda, paralel (`zahiira`).
- T014, T015 (US2 tests) — paralel.
- T017, T018, T019 (US3 tests BE+FE) — paralel.
- T023 (history rewrite), T024 (form verify) — file berbeda, paralel (`sierly`).
- US1 (BE StockService), US2 (BE verify), US3 (BE endpoint+FE) — tim berbeda, paralel penuh setelah Phase 2.

---

## Parallel Example: US1 + US3 (BE+FE)

```bash
# Tests US1 (zahiira, paralel):
Task: "Test saldo konsisten in apps/api/tests/Feature/Inventory/StockMovementBalanceTest.php"
Task: "Test immutability in apps/api/tests/Feature/Inventory/StockMovementImmutabilityTest.php"
Task: "Test audit log in apps/api/tests/Feature/Inventory/StockMovementAuditLogTest.php"
Task: "Test guard saldo negatif in apps/api/tests/Feature/Inventory/StockMovementNegativeBalanceTest.php"

# Implementation US1 (ammar):
Task: "Edit StockService guard+audit in apps/api/app/Services/StockService.php"

# Implementation US3 (sierly + ammar, paralel):
Task: "Rewrite stock-movement-history.tsx pakai DataTable in apps/web/src/routes/$tenant/clinic/inventory/components/stock-movement-history.tsx"
Task: "Add indexByTransaction + route reverse lookup in apps/api/app/Http/Controllers/StockMovementController.php + routes/api.php"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1: Verifikasi baseline (T001).
2. Phase 2: Tests Red (T002–T003) → morph map (T004) + migration (T005) paralel → model verify (T006). `php artisan migrate` (user).
3. Phase 3: US1 tests Red (T007–T010) → service guard+audit (T011) → i18n (T012) → controller verify (T013).
4. **STOP and VALIDATE**: quickstart skenario 1 + 4. PostgreSQL test suite.
5. Jejak audit + saldo konsisten + guard + audit log terjamin. Deploy/demo bila ready.

### Incremental Delivery

1. Foundational + US1 → jejak audit + guard (MVP).
2. + US2 → race condition aman + rollback idempoten.
3. + US3 → riwayat DataTable + reverse lookup FE/BE.
4. Polish → validasi rilis (PostgreSQL suite WAJIB).

### Parallel Team Strategy

- `ammar`: T004 (AppServiceProvider) + T005 (migration) Phase 2 → T011 (StockService) US1 → T016 verify US2 → T020/T021/T022 (endpoint+route) US3 → T012/T025 i18n.
- `zahiira`: T002–T003 (Phase 2) → T007–T010 (US1) → T014–T015 (US2) → T017–T019 (US3).
- `sierly`: T023 (history rewrite) + T024 (form verify) US3 → T026 opsional. Mulai FE saat backend morph map + endpoint ready.

---

## Notes

- TDD WAJIB: test Red sebelum implementasi Green. Constitution II.
- BE inti dari 007/008 tidak ditulis ulang — revisi + tambah guard/audit/endpoint only.
- FE reuse `components/forms/` + `components/datatable/` + `ClinicBreadcrumb` as-is (YAGNI, per instruksi user). Tidak buat komponen baru di kedua folder kecuali 2+ konsumen nyata.
- Composite index morph + FK restrict = PostgreSQL only. SQLite skip FK alter. WAJIB `phpunit.pgsql.xml` sebelum rilis.
- `StockMovementType::label()` pakai key `clinic.stock_movement_type.*` (sudah ada).
- Tidak auto-run build/dev/migrate/test tanpa perintah user. Konfirmasi sendiri.
- Commit per task/kelompok logis. Conventional Commits, no AI attribution.
- Push → `haikal` via `/code-review` low, hanya saat user minta "push".
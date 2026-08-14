# Research: Integritas Mutasi Stok & Riwayat Stok Produk

**Branch**: `012-stock-movements` | **Date**: 2026-08-14

Konteks awal: implementasi `stock_movements` SUDAH ADA (model, enum, policy, controller, request, migration, FK restrict). Research fokus pada 5 celah yang ditunda, bukan greenfield.

## R1 — Migrasi `nullableMorphs('related')` dari kolom manual

**Decision**: Ganti `related_type` (string manual) + `related_id` (bigint manual) + index terpisah → `nullableMorphs('related')`. Migration drop kolom manual + index lama, lalu `nullableMorphs('related')` yang sekaligus buat `related_type` + `related_id` + composite index `(related_type, related_id)`.

**Rationale**: Helper migration `nullableMorphs('related')` menghasilkan persis kolom + composite index yang spec butuhkan (FR-006), satu baris deklaratif, bukan 3 pernyataan manual. Konsisten dengan konvensi Laravel morph. Reverse lookup per transaksi (FR-012) pakai composite index ini efisien.

**Alternatives considered**:
- Pertahankan kolom manual + tambah index `(related_type, related_id)` terpisah — lebih boris baris, rawan typo kolom, tidak idiomatis Laravel. Ditolak.
- Polimorfik single morph map custom — over-engineering, `nullableMorphs` cukup.

**Catatan SQLite**: Composite index morph + FK restrict tidak teruji penuh di SQLite default suite. `phpunit.pgsql.xml` wajib sebelum rilis (sudah pola proyek, ponytail di migration 060000).

## R2 — Morph map konsisten (alias vs FQCN)

**Decision**: Daftarkan `Relation::enforceMorphMap([...])` di `AppServiceProvider::boot()`. Map minimal: `'transaction' => \App\Models\Transaction::class`. `related_type` akan menyimpan `'transaction'` (alias), bukan `App\Models\Transaction` (FQCN). `StockService::adjust()` saat ini set `related_type => $related?->getMorphClass()` — dengan morph map aktif, `getMorphClass()` otomatis return alias.

**Rationale**: FR-005 minta "morph map konsisten". FQCN di kolom = rapuh saat refactor namespace; alias stabil + pendek. `enforceMorphMap` (bukan `morphMap` biasa) melarang model tak terdaftar di-morph — pertahanan integritas tambahan. Satu titik deklarasi, dipakai semua morph di proyek (audit_logs juga bakal makai saat migrasi spatie, ponytail).

**Alternatives considered**:
- `morphMap()` non-enforcing — tidak melarang alias liar; enforce lebih kuat. Ditolak.
- Simpan FQCN (status quo) — panjang, rapuh. Ditolak (spec minta konsisten alias).

**Migration data**: Implementasi saat ini `related_type` = FQCN (karena belum morph map). Saat migration revisi + morph map diaktifkan, data historik `related_type` FQCN perlu di-migrate ke alias `'transaction'` — TAPI karena transaksi + stock_movements di MVP belum punya data produksi (seed test saja), `enforceMorphMap` langsung berlaku. `ponytail: data-migration FQCN→alias add saat ada data historik produksi`.

## R3 — Audit log di StockService::adjust()

**Decision**: Tambah `LogAuditAction::handle('inventory.stock.adjusted', $movement, auth()->user(), $context, "Menyesuaikan stok {product} — {type} {qty}")` di akhir `StockService::adjust()` (di luar `DB::transaction` closure return, atau setelah commit). `$context` = `withProperties` full attributes mutasi (`product_id`, `type`, `quantity`, `balance_after`, `note`, `related_type`, `related_id`) + `product_name` untuk narasi. Narasi: `sprintf('Menyesuaikan stok %s — %s %d', $product->name, $movement->type->label(), $movement->quantity)`.

**Rationale**: FR-014 WAJIB audit naratif "Menyesuaikan stok {product} — {type} {qty}". Saat ini `StockService::adjust()` tidak log apa-apa (verifikasi: tidak ada `LogAuditAction`/`activity()` di file). `TransactionService::create` + `CancelTransactionAction::handle` sudah log transaksi-level, tapi bukan mutasi-level — spec minta per-mutasi. LogAuditAction sudah ada infra (L0), signature cocok. `Transaction`-level audit tetap ada (transaksi created/cancelled); mutasi-level audit tambahan, bukan duplikat — beda granularity.

**Alternatives considered**:
- Audit di controller `StockMovementController@store` saja — lewat kalau mutasi lewat `TransactionService`/`CancelTransactionAction` (sold_pos/rollback tidak lewat controller). Harus di service supaya semua path terjangkau. Ditolak.
- Observer model `StockMovement` `created` event — otomatis semua path, tapi narasi butuh konteks produk+type yang notabene sudah ada di mutasi; observer ok juga. Pilihan: tetap service-level supaya narasi + context eksplisit di satu tempat dengan logika bisnis. Observer = alternatif valid bila mau de-couple; MVP pilih service-level (narasi di tempat logika). `ponytail: observer add saat banyak path mutasi non-StockService muncul`.

**Catatan**: `auth()->user()` bisa null di job/CLI. LogAuditAction sudah handle null causer (fallback). Tidak perlu guard khusus.

## R4 — Reverse lookup endpoint per transaksi (FR-012)

**Decision**: Endpoint baru `GET /{tenant}/clinic/transactions/{transaction}/stock-movements` → `StockMovementController::indexByTransaction`. Query: `StockMovement::where('related_type', $transaction->getMorphClass())->where('related_id', $transaction->id)->latest('created_at')->paginate()`. Pakai composite index morph (R1). Response shape sama `indexByProduct` + field `type_label`. Policy `viewAny` (inventory.view).

**Rationale**: FR-012 minta reverse lookup semua mutasi yang berhubungan dengan satu transaksi via index morph. Saat ini hanya ada `indexByProduct`. Endpoint ini melayani audit/rekonsiliasi: transaksi jual 2 unit + batal (rollback 2 unit) → 2 mutasi ditemukan. Composite index `(related_type, related_id)` buat ini efisien.

**Alternatives considered**:
- Reverse lookup via query param filter di endpoint `indexByProduct` (`?related_transaction=X`) — campur semantik (produk vs transaksi). Ditolak.
- Tanpa endpoint, hitung dari relasi `$transaction->stockMovements()` — butuh relasi morphMany di Transaction. Bisa, tapi endpoint eksplisit + paginasi lebih RESTful + lebih gampang FE konsumsi. Endpoint dipilih.

**Catatan**: Untuk relasi morph, `Transaction` bisa punya `stockMovements(): morphMany` via `related()` — tapi morph `related` di StockMovement generic (bisa ke model lain nanti). Endpoint query manual `where related_type/related_id` lebih presisi + tidak mengikat `Transaction` ke morph tertentu.

## R5 — Guard saldo negatif + rollback idempoten

**Decision saldo negatif (FR-015)**: Tambah guard di `StockService::adjust()`: bila `type->isInbound()` false (out_manual/sold_pos) dan `$newBalance < 0`, `abort(422, __('inventory.insufficient_stock'))` — sebelum `StockMovement::create`. `TransactionService::productLine` sudah cek `$product->stock_balance < qty` (FR-053), tapi `out_manual` lewat controller tidak cek — guard di service = satu titik, semua path terjangkau (bug fix root cause, bukan symptom).

**Decision rollback idempoten (US3 edge)**: `CancelTransactionAction::handle` sudah cek `if ($transaction->cancelled_at !== null) abort(409)` — pembatalan berulang ditolak, tidak duplikat mutasi. Tidak perlu tambahan. Hanya dokumentasikan di spec/data-model.

**Rationale**: Guard saldo negatif di service = pertahanan integritas data di trust boundary (input dari controller `out_manual` maupun `TransactionService` `sold_pos`). `TransactionService` sudah cek pre-emptive (UX, 422 cepat), service guard = backstop (race: antara cek `productLine` dan `adjust`, stok bisa turun). Bug fix root cause: satu guard di service menutup semua path.

**Alternatives considered**:
- Guard saldo negatif hanya di FormRequest `StockMovementRequest` — lewat untuk `sold_pos` (tidak lewat request itu). Ditolak.
- CHECK constraint DB `balance_after >= 0` — bagus tapi hanya cegah saldo negatif setelah compute; abort 422 di service lebih informatif (pesan i18n). `ponytail: CHECK constraint add saat audit integritas butuh DB-level backstop`.

## R6 — FE pakai DataTable reusable + improve forms

**Decision**: Rewrite `stock-movement-history.tsx` dari Table manual → pakai `DataTable` (`components/datatable/datatable.tsx`) + `useReactTable` + kolom def + `DataTablePagination`. Tambah kolom transaksi terkait (link ke detail transaksi bila `related_type` terisi). State kosok pakai `t("general.empty")` (sudah ada). Form `stock-movement-form.tsx` tetap reuse `FormSelect`/`FormInput`/`FormTextarea` (sudah pakai), tambah feedback saldo negatif dari server error 422 (sudah ada via `applyServerErrors`).

**Rationale**: User input eksplisit minta pakai `components/datatable/` + `components/forms/` yang sudah ada. History saat ini pakai Table manual (bukan DataTable) — tidak ada paginasi server-side, tidak ada sorting, tidak ada toolbar. DataTable eksisting sudah handle loading skeleton + empty state + paginasi. Reuse > rewrite. Form sudah pakai forms/ reusable, tinggal pastikan saldo negatif feedback muncul.

**Alternatives considered**:
- Tetap Table manual — tidak ada paginasi, tidak scalable > 100 baris (SC-008). Ditolak.
- Buat DataTable baru khusus stock — duplikasi. Ditolak, reuse `components/datatable/datatable.tsx`.

**Reverse lookup FE**: Bila endpoint R4 dipakai, UI bisa ditanam di detail transaksi (`pos/transactions/$id.tsx` dari spec 011) sebagai section "Pengaruh Stok" — bukan halaman terpisah. YAGNI: tidak buat route baru bila bisa jadi section. `ponytail: halaman terpisah add saat reverse lookup butuh filter/konteks tersendiri`.

## Ringkasan keputusan

| Celah | Keputusan | File |
|-------|-----------|------|
| R1 Kolom morph | `nullableMorphs('related')` ganti manual | migration baru |
| R2 Morph map | `enforceMorphMap` di AppServiceProvider | AppServiceProvider edit |
| R3 Audit log | `LogAuditAction` di StockService::adjust | StockService edit |
| R4 Reverse lookup | endpoint `transactions/{t}/stock-movements` | controller + route edit |
| R5 Guard saldo | abort 422 di StockService bila saldo < 0 | StockService edit |
| R6 FE DataTable | rewrite history pakai DataTable reusable | stock-movement-history.tsx rewrite |

Semua NEEDS CLARIFICATION ter-resolve. Tidak ada unknown tersisa.
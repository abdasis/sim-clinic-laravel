# Quickstart: Integritas Mutasi Stok & Riwayat Stok Produk

**Branch**: `012-stock-movements` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Skenario validasi runnable end-to-end. Bukan implementasi — detail kode di tasks.md.

## Prasyarat

- PostgreSQL jalan: `docker compose up -d db` (port 5435).
- Backend: `cd apps/api && cp .env.example .env && php artisan key:generate && php artisan migrate`.
- DB test (sekali): `docker compose exec db createdb -U postgres sim_clinic_laravel_test`.
- Tenant + user admin clinic + produk ter-seed (dari spec 003/007).
- Frontend: `cd apps/web && bun install && bun run dev` — port 3001.
- Login sebagai admin clinic (clinic_role=admin, punya `inventory.manage`).

Referensi kontrak: [api-contracts.md](contracts/api-contracts.md). Referensi data: [data-model.md](data-model.md).

## Skenario 1 — Jejak audit + saldo konsisten (US1, SC-001/002)

**Tujuan**: setiap mutasi tinggalkan jejak `balance_after` immutable, `stock_balance` sinkron.

1. Admin buka halaman Inventaris (`/{tenant}/clinic/inventory`).
2. Pilih produk "Serum Vitamin C" (saldo awal 0).
3. Catat stok masuk quantity 10, note "Restock awal" → submit.
4. **Verifikasi BE**:
   - `GET /{tenant}/clinic/products/{product}/stock-movements` → 1 mutasi, `type=in`, `quantity=10`, `balance_after=10`.
   - `GET /{tenant}/clinic/products/{product}` → `stock_balance=10`.
5. Catat stok keluar manual quantity 3, note "Rusak" → submit.
6. **Verifikasi**: 2 mutasi, mutasi kedua `type=out_manual`, `balance_after=7`; `stock_balance=7`.
7. **Audit log**: `Activity::where('event', 'inventory.stock.adjusted')->latest()->first()` → description "Menyesuaikan stok Serum Vitamin C — Stok Keluar 3", properties punya `product_id`, `type`, `quantity`, `balance_after`.
8. **Immutability**: upaya `UPDATE stock_movements SET quantity=99 WHERE id=…` (manual/DB) — tidak ada path app; verifikasi tidak ada route update/delete + model `$timestamps=false`.

**Expected**: jejak `balance_after` konsisten ± quantity; `stock_balance` = `balance_after` mutasi terakhir; audit log naratif tercatat; jejak immutable.

## Skenario 2 — Mutasi konkuren aman race condition (US2, SC-003)

**Tujuan**: dua mutasi bersamaan tidak timpa saldo.

1. Produk saldo 10.
2. Picu dua mutasi keluar masing-masing 3 unit nyaris bersamaan (dua request `POST products/{p}/stock-movements` type `out_manual` quantity 3 konkuren, atau test konkuren via two threads).
3. **Verifikasi BE**:
   - `stock_balance` akhir = 4 (10 − 3 − 3).
   - Dua mutasi, `balance_after` berurutan (7 lalu 4, atau sebaliknya sesuai lock) — tidak ada mutasi dengan `balance_after` yang sama (tanda race overwrite).
4. Jalankan via `phpunit.pgsql.xml` test konkuren (zahiira) untuk verifikasi otomatis.

**Expected**: saldo akhir benar, dua mutasi tercatat, tidak ada mutasi hilang/tertimpa.

## Skenario 3 — Reverse lookup per transaksi (US3, FR-012, SC-005)

**Tujuan**: satu transaksi jual + batal → tepat 2 mutasi ditemukan via index morph.

1. Buat transaksi POS dengan item produk "Serum Vitamin C" qty 2 (saldo 10 → 8). FR-052.
2. Batalkan transaksi tersebut (FR-058) → stok kembali 8 → 10 (rollback).
3. **Verifikasi BE**: `GET /{tenant}/clinic/transactions/{transaction}/stock-movements` → 2 mutasi: satu `sold_pos` qty 2 `balance_after=8`, satu `rollback` qty 2 `balance_after=10`. Kedua `related_type='transaction'`, `related_id={transaction}`.
4. **Idempoten**: batalkan transaksi yang sama lagi → 409 (already cancelled), tidak ada mutasi rollback ketiga.
5. **Index morph**: verifikasi `EXPLAIN` query reverse lookup pakai index `stock_movements_related_type_related_id_index` (PostgreSQL).

**Expected**: reverse lookup akurat 2 mutasi; rollback idempoten; query pakai index morph.

## Skenario 4 — Guard saldo negatif (FR-015, edge case)

**Tujuan**: mutasi keluar melebihi saldo ditolak.

1. Produk saldo 5.
2. Catat stok keluar manual quantity 10 → submit.
3. **Verifikasi BE**: response 422, `message` "Stok produk tidak mencukupi.", `errors.quantity` terisi.
4. **Verifikasi FE**: `applyServerErrors` map error ke field quantity + toast error.
5. **Verifikasi**: `stock_balance` tetap 5 (tidak berubah), tidak ada jejak mutasi baru (guard sebelum create).
6. **Jalur sold_pos**: transaksi POS qty 10 pada produk saldo 5 → `TransactionService::productLine` 422 "Stok produk tidak mencukupi." (FR-053 pre-emptif); service guard = backstop race.

**Expected**: 422, saldo tidak berubah, tidak ada jejak liar.

## Skenario 5 — Invariant tenant + FK restrict (FR-008/009, SC-006/007)

**Tujuan**: mutasi tidak lintas-tenant; hapus produk dengan riwayat diblokir.

1. **Invariant tenant**: mutasi via `StockService` selalu set `tenant_id = $product->tenant_id` (verifikasi kode + test: produk tenant A, mutasi tidak bisa punya `tenant_id` tenant B).
2. **FK restrict**: produk dengan riwayat mutasi di-upaya hard-delete → `SQLSTATE[23503]` (PostgreSQL) → app 422 "Tidak bisa menghapus produk: masih ada riwayat stok."
3. **Arsip diizinkan**: produk diarsipkan (`status=archived`) → sukses, riwayat mutasi tetap utuh, `stock_balance` tetap dijaga.
4. Jalankan via `phpunit.pgsql.xml` (FK restrict SQLite skip).

**Expected**: tidak ada mutasi lintas-tenant; hard-delete diblokir; arsip tetap diizinkan.

## Skenario 6 — FE riwayat stok pakai DataTable (FR-011, SC-008)

**Tujuan**: halaman riwayat pakai DataTable reusable + kolom transaksi terkait + state kosong.

1. Produk tanpa mutasi → buka riwayat → tampil "Belum ada mutasi stok." (bukan tabel kosong).
2. Produk dengan 5 mutasi (in, out_manual, sold_pos, rollback) → buka riwayat → DataTable:
   - 5 baris terurut kronologis (created_at desc).
   - Kolom: Waktu, Jenes (`type_label`), Jumlah (right-align), Saldo Setelah (right-align), Keterangan, Transaksi (link bila `related_type='transaction'`).
   - Paginasi server-side (`DataTablePagination` + meta).
   - Loading skeleton saat fetch.
3. Klik link transaksi → navigasi ke detail transaksi (`pos/transactions/$id`).
4. Breadcrumb: "Beranda Klinik > {tenant} > Inventaris" — item terakhir non-link.

**Expected**: DataTable reusable, kolom transaksi terkait + link, state kosong manusiawi, paginasi, breadcrumb benar.

## Verifikasi murah (tiap skenario)

- `php -l` tiap file BE/FE berubah.
- `php artisan test --filter=StockMovement` (sqlite, fast) — sebagian guard + saldo.
- `php artisan test -c phpunit.pgsql.xml --filter=StockMovement` — FK restrict + composite index + race (WAJIB sebelum rilis).
- `npx tsc --noEmit --incremental` (apps/web) — FE typecheck.

Command build/dev (`bun run dev`, `php artisan serve`, `vendor/bin/pint`) → user jalankan sendiri.
# Quickstart — Master Produk Klinik (007-product-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Contracts**: [products-api.md](contracts/products-api.md)

Panduan validasi end-to-end runnable. Tidak berisi kode implementasi penuh — detail implementasi di `tasks.md` (fase `/speckit-tasks`).

## Prasyarat

- Docker DB jalan: `docker compose up -d db` (PostgreSQL port 5435).
- Backend siap: `apps/api/.env` terkonfigurasi, `php artisan migrate` terbaru.
- Frontend siap: `apps/web` dependency terinstall.
- Seeded: minimal 1 tenant + 1 user admin klinik (clinic_role=admin).

## Setup (setelah implementasi)

```bash
cd apps/api && php artisan migrate          # jalankan migration FK restrict (R2)
cd apps/api && php artisan test             # seluruh test produk
```

Jangan jalankan `php artisan serve` / `bun run dev` otomatis — jalankan sendiri saat ingin validasi manual.

## Skenario validasi

### 1. CRUD + arsip + saldo default 0 (admin)

1. Login sebagai admin klinik → token.
2. `POST /{tenant}/clinic/products` body `{name:"Serum Vitamin C", unit:"botol", min_threshold:5, price:150000}` (tanpa `stock_balance`) → 201, `stock_balance: 0`, `status: active`, `is_low_stock: true` (0 <= 5).
3. `GET /{tenant}/clinic/products` → data berisi "Serum Vitamin C", arsip tidak muncul (default active, R8).
4. `PUT /{tenant}/clinic/products/{id}` body `{price:175000}` → 200, harga berubah; `stock_balance` tetap 0 (tidak berubah via update).
5. `DELETE /{tenant}/clinic/products/{id}` → 200, `status: archived`, meta "Produk berhasil diarsipkan."
6. `GET /{tenant}/clinic/products?filter[status]=archived` → data berisi produk dengan `status_label` "Diarsipkan".
7. Cek `audit_logs`: ada 3 row (`product.created`, `product.updated`, `product.archived`), narasi mengandung "Serum Vitamin C".

**Expected**: semua langkah sukses; saldo awal 0; activity log naratif tercatat.

### 2. `stock_balance` bukan input (FR-060, FR-063, SC-007)

1. `POST /{tenant}/clinic/products` body `{name:"X", unit:"pcs", min_threshold:0, price:100, stock_balance:99}` → 201; `stock_balance` tetap 0 (field diabaikan/tidak divalidasi).
2. `PUT /{tenant}/clinic/products/{id}` body `{stock_balance:99}` → 200; `stock_balance` tetap 0 (tidak berubah via endpoint update).

**Expected**: tidak ada path request yang mengubah `stock_balance`; saldo hanya via mutasi stok.

### 3. Mutasi stok mengubah saldo via `StockService` (FR-061, FR-062, R7)

1. Buat produk P (`stock_balance: 0`).
2. `POST /{tenant}/clinic/products/{P}/stock-movements` body `{type:"in", quantity:10, note:"restock"}` → 201, `balance_after: 10`.
3. Cek `GET products/{P}` → `stock_balance: 10`.
4. `POST .../stock-movements` body `{type:"out_manual", quantity:3, note:"dipakai treatment"}` → 201, `balance_after: 7`.
5. `GET products/{P}` → `stock_balance: 7`, `is_low_stock` sesuai `min_threshold`.

**Expected**: saldo berubah hanya via mutasi; `balance_after` konsisten dengan saldo.

### 4. Indikator low-stock (FR-065)

1. Produk P `stock_balance: 5`, `min_threshold: 5` → `is_low_stock: true` (equality termasuk).
2. Produk Q `stock_balance: 10`, `min_threshold: 5` → `is_low_stock: false`.
3. Produk R `min_threshold: 0`, `stock_balance: 0` → `is_low_stock: true`.

**Expected**: kondisi `<=` benar termasuk edge equality.

### 5. Hard-delete direferensi diblokir restrict (FR-068, R2)

1. Buat produk P. Catat mutasi stok (`stock_movements` row) → P direferensi.
2. Tinker/artisan: `Product::find($idP)->delete();` → melempar `QueryException` (FK restrict).
3. Buat produk Q + transaction item menunjuk Q → `Product::find($idQ)->delete();` → `QueryException`.
4. Arsipkan P via `DELETE` endpoint → 200 (arsip, bukan hard-delete). Mutasi & transaksi tetap ada, `product_id` valid.

**Expected**: hard-delete diblokir DB; arsip diizinkan dan tidak putus relasi.

### 6. Snapshot immutability transaction_items (FR-069, R5)

1. Buat produk B (name="Lama", price=100000).
2. Buat transaction item menunjuk B → snapshot `name="Lama"`, `unit_price=100000`.
3. Ubah B: `name="Baru"`, `price=200000`. Arsipkan B.
4. Baca transaction item tadi.

**Expected**: `transaction_items.name="Lama"`, `unit_price=100000` — tidak berubah walau master diubah/arsip.

### 7. Tenant isolation (konstitusi III)

1. Tenant A buat produk "Produk A". Tenant B `GET /{tenantB}/clinic/products` → tidak ada "Produk A".
2. Tenant B `GET /{tenantB}/clinic/products/{idA}` → 404 (TenantScope).

**Expected**: tidak ada bocor data lintas tenant.

### 8. Permission (R8 matriks)

1. Login role tanpa izin `product` → `GET products` 403, `POST products` 403, `DELETE products/{id}` 403.

**Expected**: matriks permission ditegakkan via `ProductPolicy`.

### 9. FE halaman master + breadcrumb + row actions + filter

1. Buka `/{tenant}/clinic/products`.
2. Breadcrumb: "Beranda Klinik > Produk" — "Produk" item terakhir (bukan link), "Beranda Klinik" link ke `/$tenant/clinic`.
3. Form "Tambah Produk" tidak punya field "Saldo Stok" (FR-060). Field: Nama, Satuan, Ambang Minimum, Harga.
4. Tabel punya kolom aksi per-row: "Ubah" (buka modal edit prefill) + "Arsipkan" (alert confirm).
5. Faceted filter status di toolbar → pilih "Diarsipkan" → tampilkan arsip; "Semua" → semua.
6. Badge "Stok menipis" muncul pada baris produk `is_low_stock: true`.

**Expected**: breadcrumb benar, form tanpa field saldo, edit + archive berfungsi, filter status bekerja, indikator low-stock tampil.

## Referensi

- Kontrak endpoint: [contracts/products-api.md](contracts/products-api.md)
- Struktur data: [data-model.md](data-model.md)
- Keputusan desain: [research.md](research.md)
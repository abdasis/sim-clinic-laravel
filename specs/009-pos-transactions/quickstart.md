# Quickstart — Transaksi POS & Pembayaran (009-pos-transactions)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Contracts**: [transactions-api.md](contracts/transactions-api.md)

Panduan validasi end-to-end runnable. Tidak berisi kode implementasi penuh — detail implementasi di `tasks.md` (fase `/speckit-tasks`).

## Prasyarat

- Docker DB jalan: `docker compose up -d db` (PostgreSQL port 5435).
- Backend siap: `apps/api/.env` terkonfigurasi, `php artisan migrate` terbaru.
- Frontend siap: `apps/web` dependency terinstall.
- Seeded: minimal 1 tenant + 1 user admin klinik (clinic_role=admin) + 1 cashier. Untuk exclusive-arc/booking: 1 pasien, 1 layanan aktif, 1 produk (stok cukup), 1 booking berstatus `done` + 1 booking `confirmed`.

## Setup (setelah implementasi)

```bash
cd apps/api && php artisan migrate          # 2 migration: paid_amount+soft-delete, FK patient/booking restrict
cd apps/api && php artisan test             # seluruh test transaksi POS
cd apps/web && bun run generate-routes      # regen TanStack route tree (route POS baru)
```

Jangan jalankan `php artisan serve` / `bun run dev` otomatis — jalankan sendiri saat ingin validasi manual.

## Skenario validasi

### 1. Catat transaksi POS + invoice number (FR-041, FR-042, FR-056)

1. Login sebagai admin/cashier → token.
2. `POST /{tenant}/clinic/transactions` body `{patient_id:2, items:[{service_id:3, qty:1}, {product_id:7, qty:2}]}` → 201, `invoice_number` berformat `INV-YYYYMMDD-0001`, `subtotal` = sum item, `paid_amount` 0, `payment_status` `unpaid`, items snapshot nama/harga master.
3. `GET /{tenant}/clinic/transactions/{id}` → data lengkap (items, patient_name).

**Expected**: transaksi tersimpan, invoice number terformat, snapshot utuh.

### 2. Invoice number unik walau concurrent (FR-042, SC-015)

1. Dua request `POST /transactions` bersamaan (tenant+hari sama) — via test parallel atau tinker dua proses.
2. Kedua response → `invoice_number` berbeda berurutan (`...0001`, `...0002`), tidak ada duplikasi.

**Expected**: race-safe, nomor unik berurutan walau concurrent. (PostgreSQL advisory lock; SQLite fallback — verifikasi no-duplikasi via unique constraint.)

### 3. Pembayaran & status 3-state + sisa bayar (FR-047, FR-055)

1. Transaksi subtotal 500.000 berstatus `unpaid`.
2. `POST /{tenant}/clinic/transactions/{id}/payments` body `{method:"cash", amount:200000, paid_at:"2026-08-14T10:00"}` → 200, `payment_status` `partially_paid`, `paid_amount` 200000, `balance_due` 300000, `meta.overpaid` false.
3. `POST .../payments` body `{method:"qris", amount:300000, paid_at:"2026-08-14T10:05"}` → 200, `payment_status` `paid`, `paid_amount` 500000, `balance_due` 0.
4. Cek `audit_logs`: ada row `transaction.payment_recorded` narasi "Mencatat pembayaran transaksi {invoice_number} — status berubah dari 'unpaid' ke 'partially_paid'." lalu "...dari 'partially_paid' ke 'paid'." properties `old`/`new` `paid_amount`+`payment_status`.

**Expected**: status transisi 3-state atomik; sisa bayar terhitung; audit log naratif lama→baru.

### 4. Kelebihan bayar (edge case)

1. Transaksi sisa 100.000. `POST .../payments` body `{method:"cash", amount:150000, ...}` → 200, `payment_status` `paid`, `meta.overpaid` true.

**Expected**: peringatan overpaid; status `paid`; tidak ada saldo otomatis.

### 5. Exclusive-arc + patient required + booking done (FR-049, FR-050, FR-044)

1. `POST /transactions` body `{items:[{qty:1}]}` (item tanpa produk+layanan) → 422 pada `items.0` "Item harus produk atau layanan, tidak keduanya."
2. `POST /transactions` body `{items:[{product_id:7, service_id:3, qty:1}]}` (keduanya) → 422 pada `items.0`.
3. `POST /transactions` body `{items:[{service_id:3, qty:1}]}` (tanpa patient_id) → 422 pada `patient_id` (wajib pasien).
4. `POST /transactions` body `{patient_id:2, booking_id:<id booking confirmed>, items:[...]}` → 422 pada `booking_id` "Hanya booking yang sudah selesai (done) dapat ditautkan."
5. `POST /transactions` body `{patient_id:2, booking_id:<id booking done>, items:[...]}` → 201, `booking_id` terisi.

**Expected**: exclusive-arc + patient required + booking-done ditegakkan.

### 6. Pembatalan + rollback stok + audit (FR-058)

1. Buat transaksi berisi 2 produk (stok terpotong saat create).
2. `POST /{tenant}/clinic/transactions/{id}/cancel` → 200, `cancelled_at` terisi, `is_cancelled` true. Stok produk kembali (cek `products.stock_balance` = saldo sebelum transaksi, ada mutasi `rollback`).
3. `POST .../cancel` lagi (double-cancel) → 422.
4. Cek `audit_logs`: row `transaction.cancelled` narasi "Membatalkan transaksi {invoice_number} — stok produk dikembalikan.".

**Expected**: pembatalan rollback stok; double-cancel ditolak; audit log.

### 7. Soft-delete transaksi (FR-058 soft-delete)

1. `DELETE /{tenant}/clinic/transactions/{id}` → 200, `deleted_at` terisi (soft).
2. `GET /{tenant}/clinic/transactions` → transaksi soft-deleted tidak muncul di daftar aktif.
3. Record tetap ada di DB (`Transaction::withTrashed()->find($id)`) — audit utuh.
4. Cek `audit_logs`: row `transaction.deleted`.

**Expected**: soft-delete sembunyi dari daftar; record fisik tetap untuk audit.

### 8. FK restrictOnDelete (FR-045)

1. Buat transaksi yang menunjuk pasien A + booking B.
2. Tinker: `Patient::find($idA)->delete();` → melempar `QueryException` (FK restrict). Sama untuk `Booking::find($idB)->delete()` (restrict). `cashier` sudah restrict (033000).
3. Nonaktifkan pasien A (soft, spec 006) / booking B status `cancelled` → transaksi tetap ada, FK valid.

**Expected**: hard-delete parent yang direferensi transaksi diblokir DB (PostgreSQL); nonaktif/cancel tidak putus relasi.
**Catatan SQLite**: migration restrict skip SQLite — verifikasi DB-level restrict pada PostgreSQL (R11).

### 9. Permission (R9)

1. Login admin → `GET transactions` 200, `POST transactions` 201, `POST payments` 200, `POST cancel` 200, `DELETE` 200.
2. Login cashier → sama, semua 200/201 (cashier rw).
3. Login doctor → `GET transactions` 403, semua write 403 (doctor tidak punya modul transaction).
4. Login therapist → 403 (sama).

**Expected**: matriks permission ditegakkan (admin+cashier rw, doctor/therapist 403).

### 10. FE kasir POS (FR-077, FR-078, FR-079)

1. Buka `/{tenant}/clinic/pos` → halaman kasir: pilih pasien, (opsional) pilih booking done, tambah item (layanan/produk + qty), ringkasan subtotal. Breadcrumb "Beranda Klinik > Kasir POS".
2. Simpan → toast sukses; redirect/refresh ke riwayat.
3. Buka `/{tenant}/clinic/pos/transactions` → DataTable: invoice_number, pasien, subtotal, paid_amount, sisa bayar, badge `payment_status` 3-state (Belum Lunas/Dibayar Sebagian/Lunas). Faceted filter status. Breadcrumb "Beranda Klinik > Transaksi".
4. Klik baris → `/{tenant}/clinic/pos/transactions/{id}`: detail items + payments history + sisa bayar + badge + aksi "Catat Pembayaran" (dialog: method, amount, paid_at) + "Batalkan" (confirm) + "Cetak Invoice" (buka tab HTML). Breadcrumb "Beranda Klinik > Transaksi > {invoice_number}".
5. Catat pembayaran via dialog → badge update, sisa bayar berkurang.

**Expected**: kasir POS halaman terpisah (bukan modal); DataTable + badge 3-state + sisa bayar; detail + aksi bayar/batal/cetak; breadcrumb benar. Komponen form/datatable reusable dipakai; `FormRepeatableItems` di `components/forms/` untuk line-item dinamis.

### 11. Tenant isolation (FR konstitusi III)

1. Login tenant A → buat transaksi.
2. Login tenant B → `GET /{tenantB}/clinic/transactions` → transaksi tenant A tidak muncul (TenantScope).
3. `GET /{tenantB}/clinic/transactions/{idA}` → 404.

**Expected**: tidak ada bocor lintas-tenant.

## Referensi

- Kontrak endpoint: [contracts/transactions-api.md](contracts/transactions-api.md)
- Struktur data: [data-model.md](data-model.md)
- Keputusan desain: [research.md](research.md)
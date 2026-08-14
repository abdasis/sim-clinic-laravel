# Quickstart — Transaksi POS & Pembayaran (008-transactions-pos)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Contracts**: [transactions-api.md](contracts/transactions-api.md)

Panduan validasi end-to-end runnable. Tidak berisi kode implementasi penuh — detail implementasi di `tasks.md` (fase `/speckit-tasks`).

## Prasyarat

- Docker DB jalan: `docker compose up -d db` (PostgreSQL port 5435).
- Backend siap: `apps/api/.env` terkonfigurasi, `php artisan migrate` terbaru.
- Frontend siap: `apps/web` dependency terinstall.
- Seeded: minimal 1 tenant + 1 user admin/cashier klinik (clinic_role=admin/cashier) + 1 pasien + 1 layanan aktif + 1 produk aktif (dengan stok).
- DB test PostgreSQL: `docker compose exec db createdb -U postgres sim_clinic_laravel_test` (sekali).

## Setup (setelah implementasi)

```bash
cd apps/api && php artisan migrate          # migration spec 008 (paid_amount, softdelete, issued_at, enum, FK restrict, drop invoices)
cd apps/api && php artisan test             # seluruh test transaksi (sqlite)
cd apps/api && php artisan test -c phpunit.pgsql.xml --filter=Transaction   # constraint restrict + enum (WAJIB sebelum rilis)
```

Jangan jalankan `php artisan serve` / `bun run dev` otomatis — jalankan sendiri saat ingin validasi manual.

## Skenario validasi

### 1. Buat transaksi POS + link booking opsional (FR-049/050/033, US1)

1. Login sebagai cashier klinik → token.
2. `POST /{tenant}/clinic/transactions` body `{patient_id:5, booking_id:null, items:[{service_id:3, qty:1}]}` → 201, `payment_status: unpaid`, `paid_amount: "0.00"`, `invoice_number: "INV-YYYYMMDD-0001"`, `issued_at` terisi.
3. `POST .../transactions` body `{patient_id:5, booking_id:<idBookingDone>, items:[{product_id:7, qty:2}]}` → 201, `booking_id` terisi.
4. `GET /{tenant}/clinic/transactions` → data berisi kedua transaksi (soft-deleted tidak muncul).

**Expected**: transaksi tersimpan, invoice_number tergenerasi, issued_at terisi (F0 merge), booking opsional.

### 2. Invoice number unik konkuren (FR-077, SC-002, US2)

1. Hapus semua transaksi tenant hari ini (atau pakai tenant baru).
2. Buat transaksi pertama hari itu → `INV-YYYYMMDD-0001`.
3. Buat transaksi kedua hari itu → `INV-YYYYMMDD-0002` (urutan naik).
4. Simulasikan konkuren: jalankan 2 request `POST .../transactions` bersamaan (test feature pakai fork/async) → masing-masing dapat nomor berurutan berbeda (mis. ...-0003, ...-0004), tidak ada duplikat.
5. Ganti tanggal (test pakai mock `now()`) → transaksi pertama hari baru → `INV-YYYYMMDD2-0001` (reset urutan harian).

**Expected**: nomor unik berurutan walau konkuren; reset per hari.

### 3. Status pembayaran 3-state + sisa bayar (FR-055/079/080, US3)

1. Buat transaksi subtotal 300000 (status `unpaid`, `paid_amount: 0`).
2. `POST .../transactions/{id}/payments` body `{method:"cash", amount:100000, paid_at:now}` → 201, `paid_amount: "100000.00"`, `payment_status: "partially_paid"`, `meta.overpaid: false`.
3. `GET .../transactions/{id}` → sisa bayar = `subtotal - paid_amount` = 200000 (FE hitung dari resource).
4. `POST .../payments` body `{method:"transfer", amount:200000, paid_at:now}` → 201, `paid_amount: "300000.00"`, `payment_status: "paid"`, sisa 0.
5. `POST .../payments` body `{method:"cash", amount:50000, paid_at:now}` pada transaksi lunas → 201, `meta.overpaid: true` + peringatan (tidak 422).

**Expected**: 3-state akurat (unpaid→partially_paid→paid); paid_amount akumulatif; sisa bayar benar; overpaid = peringatan.

### 4. Soft-delete + restrict FK (FR-081/082/083, US4)

1. Buat transaksi T dengan payment.
2. `DELETE /{tenant}/clinic/transactions/{T}` → 200, `deleted_at` terisi, meta "Transaksi berhasil dihapus."
3. `GET .../transactions` → T tidak muncul (soft-deleted exclude). `GET .../transactions/{T}` → 404.
4. Tinker: cek DB → T masih ada (`deleted_at` not null), payment/item tetap utuh untuk audit.
5. Tinker: hard-delete T (`Transaction::withTrashed()->find($T)->forceDelete()`) → `QueryException` (FK restrict `transaction_id` payments/items).
6. Tinker: hapus pasien yang direferensi T → `QueryException` (FK restrict `patient_id`).
7. Tinker: hapus booking yang direferensi T → `QueryException` (FK restrict `booking_id`).

**Expected**: soft-delete diizinkan, data utuh audit; hard-delete + hapus parent direferensi diblokir restrict (pgsql test).

### 5. Exclusive arc item (R9, FR-056)

1. `POST .../transactions` body `{patient_id:5, items:[{product_id:7, service_id:3, qty:1}]}` → 422 (keduanya terisi).
2. `POST .../transactions` body `{patient_id:5, items:[{qty:1}]}` → 422 (keduanya null).
3. Buat transaksi item produk → snapshot `name`+`unit_price`. Ubah master produk (nama/harga) + arsipkan. Baca transaction item → snapshot tetap (R6/FR-056).

**Expected**: item invalid ditolak 422; snapshot immutable.

### 6. Cancel + guard double-cancel (FR-058, R10, US4)

1. Buat transaksi dengan item produk (stok berkurang via sold_pos).
2. `POST .../transactions/{id}/cancel` → 200, `cancelled_at` terisi, stok produk kembali (rollback).
3. `POST .../transactions/{id}/cancel` lagi → 409 (sudah dibatalkan, R10 guard).

**Expected**: cancel rollback stok; double-cancel diblokir.

### 7. F0 merge invoice (R7)

1. Buat transaksi → `issued_at` terisi di transaction (bukan tabel invoices).
2. `GET .../transactions/{id}/invoice` → HTML render, baca `issued_at` dari transaction.
3. Tinker: cek tabel `invoices` → tidak ada (dropped). Model `Invoice`/`InvoicePolicy` hilang.

**Expected**: invoice render dari transaction; tabel invoices di-drop.

### 8. Audit log naratif (FR-084, R13, US5)

1. Buat transaksi → cek `audit_logs`: event `pos.transaction.created`, narasi "Mencatat transaksi {invoice}".
2. Catat pembayaran (unpaid→partially_paid) → `audit_logs`: event `pos.payment.created`, narasi "Mencatat pembayaran {invoice} — status unpaid→partially_paid", properties old_status/new_status/amount.
3. Pelunasan (partially_paid→paid) → narasi "Mencatat pembayaran {invoice} — status partially_paid→paid".
4. Cancel → `pos.transaction.cancelled`. Soft-delete → `pos.transaction.deleted`.

**Expected**: semua aksi ubah-data tercatat naratif dengan transisi status.

### 9. Tenant isolation (konstitusi III)

1. Tenant A buat transaksi. Tenant B `GET /{tenantB}/clinic/transactions` → tidak ada transaksi A.
2. Tenant B `GET .../transactions/{idA}` → 404 (TenantScope).

**Expected**: tidak ada bocor data lintas tenant.

### 10. FE kasir POS + badge 3-state + sisa bayar + breadcrumb (FR-080/087, US1/US3/US5)

1. Buka `/{tenant}/clinic/pos`.
2. Breadcrumb: "Beranda Klinik > Transaksi" — "Transaksi" item terakhir (bukan link), "Beranda Klinik" link ke `/$tenant/clinic`.
3. Select pasien: `FormCombobox` searchable (ketik nama, filter opsi) — bukan NativeSelect mentah.
4. Tambah item (produk/layanan), lihat subtotal. Submit → transaksi tersimpan, validasi zod jalan (item required, qty >0).
5. Riwayat `/{tenant}/clinic/pos/transactions`: tabel dengan faceted filter `payment_status` (3-state: Belum Lunas/Dibayar Sebagian/Lunas), kolom `paid_amount` + sisa bayar diformat Rupiah (`formatCurrency` dari `src/lib/`), `StatusBadge` 3-state (warna berbeda per state).
6. Label "Dibayar Sebagian" tampil (i18n `clinic.payment_status.partially_paid`).
7. Payment panel: badge status 3-state, `paid_amount` vs `subtotal` + sisa bayar.

**Expected**: FormCombobox search pasien; badge 3-state + sisa bayar + formatCurrency konsisten; breadcrumb benar; validasi zod jalan.

## Referensi

- Kontrak endpoint: [contracts/transactions-api.md](contracts/transactions-api.md)
- Struktur data: [data-model.md](data-model.md)
- Keputusan desain: [research.md](research.md)
# Quickstart: Integritas Item Transaksi, Pembayaran Cicilan & Cetak Invoice

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Panduan validasi end-to-end. Bukan implementasi — detail kode di tasks.md.

## Prasyarat

- Docker db jalan: `docker compose up -d db` (port 5435, db/user/pass `sim_clinic_laravel`/`postgres`/`postgres`).
- Backend `apps/api` ter-migrate + serve (port 8000).
- Frontend `apps/web` dev (port 3001).
- Tenant + user kasir ter-seed (dari spec 003/004). Login sebagai kasir.
- Master produk + layanan ada (spec 005/007). Transaksi minimal 1 (spec 008).

## Skenario validasi

### Skenario 1 — Integritas DB exclusive arc (anomali #1)

**Tujuan**: verifikasi CHECK constraint menolak item ambigu di tingkat basis (bukan hanya app).

1. Jalankan test PostgreSQL: `cd apps/api && php artisan test -c phpunit.pgsql.xml --filter=TransactionItemExclusiveArc`
2. **Expected PASS**: test coba insert item `product_id`+`service_id` terisi → DB reject; keduanya null → reject; tepat satu → OK.
3. Alternatif manual: via `php artisan tinker` (PostgreSQL connection), coba `DB::table('transaction_items')->insert([...])` dengan kedua FK terisi → exception `Check Violation`.

**Lolos bila**: tidak ada item ambigu tersimpan walau jalur non-UI.

### Skenario 2 — FK restrict master produk/layanan

**Tujuan**: verifikasi hapus permanen master yang dirujuk item diblokir.

1. Buat transaksi dengan item produk. Jalankan test: `php artisan test -c phpunit.pgsql.xml --filter=TransactionItemForeignKeyRestrict`
2. **Expected PASS**: hapus product yang dirujuk item → DB restrict (`Foreign Key Violation`); ubah product `status=archived` → OK, item tetap utuh.

**Lolos bila**: master direferensi tidak bisa hard-delete; arsip tidak memutus rujukan.

### Skenario 3 — Snapshot immutability (R6, FR-056)

**Tujuan**: verifikasi ubah master tidak ubah item historik.

1. Buat transaksi dengan item layanan "Facial Basic" harga 200000.
2. Ubah harga master layanan jadi 250000 (via UI master atau tinker).
3. Buka detail transaksi → item tetap tampil "Facial Basic" 200000.

**Lolos bila**: item historik tidak berubah walau master diubah. Test: `php artisan test --filter=TransactionSnapshot`.

### Skenario 4 — Tenant invariant (anomali #3)

**Tujuan**: verifikasi child tidak bisa lintas-tenant.

1. Jalankan test: `php artisan test --filter=TransactionItemTenantInvariant`
2. **Expected PASS**: item dibuat via `$transaction->items()->create()` → `tenant_id` == `transaction.tenant_id`. Upaya set `tenant_id` beda via relasi di-override oleh parent.

**Lolos bila**: tidak ada child lintas-tenant.

### Skenario 5 — Cicilan bertahap (US2, SC-003/004/007)

**Tujuan**: verifikasi alur pembayaran cicilan penuh.

1. Login kasir. Buat transaksi subtotal 300000 (atau pakai existing unpaid).
2. Buka halaman detail transaksi (`pos/transactions/{id}`).
3. Verifikasi: subtotal 300000, paid 0, sisa 300000, status `unpaid`, riwayat kosong.
4. Catat pembayaran 100000 (tunai). Verifikasi: paid 100000, sisa 200000, status `partially_paid`, riwayat 1 entry.
5. Catat pembayaran 100000 (QRIS). Verifikasi: paid 200000, sisa 100000, status `partially_paid`, riwayat 2 entry.
6. Catat pelunasan 100000 (transfer). Verifikasi: paid 300000, sisa 0, status `paid`, riwayat 3 entry.
7. Catat pembayaran 50000 lagi (overpaid). Verifikasi: peringatan overpaid muncul.

**Lolos bila**: paid_amount akumulatif, status bergerak unpaid→partially_paid→paid, riwayat lengkap, overpaid diperingatkan. Test: `php artisan test --filter=PaymentCicilan`.

### Skenario 6 — Cetak invoice lengkap (US3, SC-005/008)

**Tujuan**: verifikasi invoice render items + payments dari relasi (R4).

1. Dari halaman detail transaksi (skenario 5, sudah 3 pembayaran), klik "Cetak Invoice".
2. Buka halaman invoice (`pos/invoices/{id}`).
3. Verifikasi: header (klinik, pasien, invoice_number, issued_at), tabel items (nama, qty, harga, subtotal), **section pembayaran** (3 entry: method, jumlah, waktu), total dibayar 300000, sisa 0, total 300000.
4. Klik "Print" → dialog print browser muncul, elemen non-cetak tersembunyi.
5. Alternatif server-side: `GET /api/{tenant}/clinic/transactions/{id}/invoice` → HTML render sama (blade view).

**Lolos bila**: semua item + pembayaran tampil 100% akurat, konten dari relasi (ubah transaksi → invoice ikut). Waktu buka+print < 10 detik.

## Verifikasi command

```bash
# Backend — constraint test (WAJIB PostgreSQL sebelum rilis)
cd apps/api && php artisan test -c phpunit.pgsql.xml --filter="TransactionItem|PaymentCicilan|TransactionSnapshot"

# Backend — cicilan flow (sqlite OK)
cd apps/api && php artisan test --filter="PaymentCicilan"

# Frontend — typecheck
cd apps/web && npx tsc --noEmit --incremental

# Frontend — regenerate route tree setelah tambah route file
cd apps/web && bun run generate-routes
```

**Tidak auto-run** build/dev/migrate tanpa perintah user. Jalankan sendiri.

## Referensi

- [data-model.md](data-model.md) — skema + invariant
- [contracts/api-contracts.md](contracts/api-contracts.md) — endpoint + response + i18n keys
- `docs/erd/transaction_items.md`, `docs/erd/payments.md`, `docs/erd/invoices.md` — sumber kebenaran
- `docs/normalization/README.md` — anomali #1/#3 + denormalisasi intensional
- `docs/normalization/workflow.md` — langkah 11/12/13
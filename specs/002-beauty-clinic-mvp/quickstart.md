# Quickstart Validation: MVP Sistem Klinik Kecantikan

**Spec**: [spec.md](./spec.md) | **Contracts**: [contracts/api-contracts.md](./contracts/api-contracts.md) | **Data Model**: [data-model.md](./data-model.md)

Panduan validasi end-to-end. Tidak duplikasi detail implementasi — lihat kontrak & data model.

## Prerequisites

- Spec 001 **sudah terimplementasi** (tenant, `BelongsToTenant`, `TenantScope`, `ResolveTenant`, auth Sanctum, `lang/id/*`). Lihat [R1 research](./research.md#r1-strategi-dependensi-spec-001-belum-terimplementasi).
- PHP 8.3, Composer, Node/Bun. Laravel 13 (`apps/api`), React 19 (`apps/web`).
- DB: SQLite (dev) atau Postgres/MySQL (produksi). `apps/api/.env` `DB_CONNECTION`.
- Disk `public` ter-link: `php artisan storage:link` (untuk foto before/after, R3).

## Setup

```bash
# Backend (apps/api)
cd apps/api
composer install
php artisan migrate --force          # 13 tabel bisnis baru + kolom clinic_role
php artisan storage:link             # foto rekam medis
php artisan db:seed --class=ClinicDemoSeeder   # 4 staf (4 peran) + pasien + layanan + produk

# Frontend (apps/web)
cd ../web
bun install
# Tidak ada dep baru (R ringkasan dependency). Komponen datatable + ui/form sudah ada.
```

## Run

```bash
# API
php artisan serve                    # http://127.0.0.1:8000

# Web — user jalankan sendiri (CLAUDE.md: jangan auto-run dev server)
bun dev
```

## Validation Scenarios

### V1: Peran klinik & permission (US1, FR-001..004, SC-001/009)

1. Login sebagai admin tenant → buat 4 staf: 1 Admin, 1 Dokter, 1 Terapis, 1 Kasir (`clinic_role`).
2. Login Kasir → coba `GET /{t}/clinic/medical-records` → 403 (FR-044).
3. Login Dokter → akses modul Rekam Medis → 200.
4. Login Kasir → akses modul POS → 200.
5. Login Terapis → akses POS → 403 (R2 matriks).

Pass: setiap peran hanya akses modul yang diizinkan; 100% aksi terlarang ditolak (SC-009).

### V2: Master layanan + produk (US2, US6, FR-010..013, FR-060, FR-066)

1. Admin buat 3 layanan (Facial, Peeling, Laser) dengan harga berbeda.
2. Submit layanan harga negatif → 422 (FR-011).
3. Arsip 1 layanan → tidak muncul saat pilihan baru booking/POS, data lampau utuh (FR-013).
4. Buat produk "Serum A" satuan `botol`, `stock_balance=10`, `min_threshold=5`, harga.

Pass: validasi harga, arsip bukan hapus, produk muncul di POS.

### V3: Pasien + riwayat + duplikat (US3, FR-020..023, SC-002/010)

1. Staf daftarkan pasien baru (nama, tanggal lahir, jenis kelamin, telepon, WA, alamat).
2. Daftarkan pasien kedua dengan **nomor telepon sama** → response `meta.duplicate_warning=true` (FR-023, tidak block).
3. Cari pasien via `?search=` nama/telepon → hasil relevan (FR-021).
4. Setelah ada booking/treatment (V4/V7), `GET /{t}/clinic/patients/{id}/history` → riwayat terurut (FR-022).

Pass: pencarian, peringatan duplikat, riwayat lengkap kronologis (SC-010).

### V4: Booking + jadwal + overlap (US4, FR-030..035, SC-008)

1. Staf buat booking: pasien + layanan + assignee (Dokter) + tanggal/jam → `status=pending` (FR-031).
2. Konfirmasi → `confirmed`. Tandai selesai → `done`.
3. Buat booking kedua untuk **assignee sama, slot beririsan** → response `meta.overlap_warnings` berisi booking pertama, **tetap tersimpan** (FR-035, clarification session).
4. `GET /{t}/clinic/bookings/schedule?from=...&to=...&view=day` → booking aktif tampil per waktu + assignee (FR-032).
5. Coba `done`→`cancelled` → 422 (edge case).

Pass: siklus status, overlap warning non-blocking, jadwal responsif untuk 50 pasien + 200 booking/bulan (SC-008).

### V5: POS + stok + invoice (US5, FR-050..059, SC-003/004/005)

1. Kasir buat transaksi: 1 layanan + 1 produk (qty 2). Subtotal terhitung dari harga master × qty (FR-051). Harga & nama di-snapshot di `transaction_items` (FR-056).
2. Simpan → `stock_balance` produk berkurang 2 (FR-052). Cek `is_low_stock` jika ≤ threshold.
3. Coba transaksi produk qty > stok → 422 (FR-053).
4. Catat pembayaran tunai = subtotal → `payment_status=paid` (FR-055).
5. `GET /{t}/clinic/transactions/{id}/invoice` → HTML print-view detail item + total + metode + identitas pasien+klinik (FR-057).
6. Batalkan transaksi lain (belum lunas) → stok produk dikembalikan via `rollback` (FR-058). Verify `stock_balance` kembali.

Pass: subtotal otomatis, stok real-time akurat 100% (SC-004), rollback tanpa selisih (SC-005), transaksi lengkap <2 menit (SC-003).

### V6: Inventory + pergerakan stok (US6, FR-061..065)

1. Catat stok masuk produk (restock 10) → `stock_balance` naik, `stock_movements` type `in` + `balance_after` (FR-061).
2. Catat stok keluar manual (rusak 1) → `stock_balance` turun, type `out_manual` (FR-062).
3. `GET /{t}/clinic/products/{id}/stock-movements` → semua pergerakan (in, out_manual, sold_pos dari V5, rollback) + waktu (FR-064).
4. Verify saldo = awal + masuk − keluar-manual − terjual-POS (FR-063).
5. Produk saldo < `min_threshold` → `is_low_stock=true` (FR-065).

Pass: saldo real-time konsisten, riwayat lengkap, badge stok menipis.

### V7: Rekam medis SOAP + foto (US7, FR-040..044, SC-007)

1. Dari booking `done` (V4), Dokter isi SOAP (Subjective, Objective, Assessment, Plan) → tersimpan terikat booking (FR-040).
2. Tambah treatment record (acu layanan + catatan) (FR-041).
3. Upload foto before (jpg ≤2MB) + foto after → tersimpan (FR-042). Coba file `.txt` → 422.
4. `GET /{t}/clinic/patients/{id}/treatments` → riwayat treatment + SOAP + foto (FR-043).
5. Login Kasir → coba isi SOAP → 403 (FR-044, V1).

Pass: SOAP + treatment + foto lengkap, <5 menit (SC-007), permission dokter/terapis/admin.

### V8: Laporan (US8, FR-070..075, SC-006)

1. Admin buka laporan omzet rentang hari ini → total = sum transaksi lunas hari itu (FR-070, FR-059).
2. Laporan penjualan treatment → rincian per layanan (qty + revenue) (FR-071).
3. Laporan penjualan produk → rincian per produk (FR-072).
4. Pilih rentang tanpa transaksi → hasil kosong/nol + pesan, bukan error (FR-074).
5. Verifikasi hanya transaksi `paid` dihitung (FR-073).
6. Login Kasir → akses laporan → 403 (FR-075).

Pass: omzet cocok jumlah manual (SC-006), laporan kosong graceful, admin-only.

## Test Commands

```bash
cd apps/api
php artisan test                    # PHPUnit feature+unit (policy, service, stok)
php artisan test --filter=TransactionService   # fokus orkestrasi POS + stok
php -l app/Services/StockService.php            # syntax check (CLAUDE.md izin)
php artisan tinker                  # probe saldo stok / overlap
```

Frontend:
```bash
cd apps/web
bun run test                        # vitest (schedule-grid, photo-uploader, forms)
```

## Expected Outcomes (SC mapping)

- SC-001: 4 peran login, hanya modul diizinkan (V1).
- SC-002: daftar pasien + booking <3 menit (V3+V4).
- SC-003: transaksi POS lengkap <2 menit (V5).
- SC-004: stok akurat 100% — saldo = awal+masuk−keluar−terjual (V5+V6).
- SC-005: rollback transaksi tanpa selisih (V5).
- SC-006: omzet laporan = jumlah manual transaksi lunas (V8).
- SC-007: rekam medis SOAP + foto <5 menit (V7).
- SC-008: jadwal responsif 50 pasien + 200 booking/bulan (V4).
- SC-009: 100% akses terlarang ditolak (V1, V7, V8).
- SC-010: riwayat pasien lengkap kronologis (V3).

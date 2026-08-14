# Feature Specification: Transaksi POS & Pembayaran Klinik

**Feature Branch**: `008-transactions-pos`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "transactions (ammar → zahiira) — setelah #8. invoice_number unique per tenant, generate INV-YYYYMMDD-XXXX. payment_status. booking_id nullable (link dari booking done, FR-033). Index (tenant_id,invoice_number) + (tenant_id,payment_status,created_at). Revisi: soft delete (deleted_at); index (tenant_id, deleted_at). Kolom baru paid_amount decimal(12,2) default 0 not null; issued_at (bila F0 = merge invoices). FK patient_id/cashier_id/booking_id → restrictOnDelete. invoice_number race fix: generateInvoiceNumber() count()+1 → lockForUpdate dalam DB transaction / sequence per tenant per hari. AC: transaksi POS baru + link booking; invoice number unik walau concurrent; soft-delete transaksi; activity log 'Mencatat pembayaran … status {lama}→{baru}'. FE: kasir POS + badge payment_status 3-state + paid_amount vs subtotal + sisa bayar + label i18n clinic.payment_status.partially_paid + breadcrumb. Sumber kebenaran: docs/erd/ + docs/normalization/README.md + docs/normalization/workflow.md."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Kasir Catat Transaksi POS Baru (Priority: P1)

Kasir klinik mencatat penjualan produk dan/atau layanan (treatment) kepada seorang pasien melalui kasir POS. Saat membuat transaksi, kasir memilih pasien, menambahkan line item (produk atau layanan, masing-masing dengan snapshot nama dan harga), dan sistem menghitung subtotal. Bila transaksi berasal dari booking yang berstatus `done`, kasir dapat menautkan transaksi ke booking tersebut secara opsional (FR-033). Sistem menetapkan status pembayaran awal `unpaid` dengan `paid_amount` 0, lalu kasir dapat mencatat pembayaran.

**Why this priority**: Kasir POS adalah inti modul pendapatan klinik (US5). Tanpa pencatatan transaksi, tidak ada omzet, tidak ada invoice, tidak ada laporan keuangan. MVP finansial tidak berfungsi tanpa ini.

**Independent Test**: Bisa diuji dengan membuat satu transaksi dengan satu item layanan untuk satu pasien tanpa menautkan booking, lalu memverifikasi transaksi tersimpan dengan subtotal benar, status `unpaid`, dan `paid_amount` 0 — semuanya tanpa melibatkan modul pembayaran cicilan atau pembatalan.

**Acceptance Scenarios**:

1. **Given** kasir sudah login dan ada pasien serta layanan aktif, **When** kasir memilih pasien, menambahkan item layanan "Facial Basic" harga 200000, lalu menyimpan transaksi, **Then** transaksi tersimpan dengan subtotal 200000, status pembayaran `unpaid`, `paid_amount` 0, dan nomor invoice tergenerasi otomatis.
2. **Given** ada booking berstatus `done` untuk pasien A, **When** kasir membuat transaksi untuk pasien A dan menautkan booking tersebut, **Then** transaksi tersimpan dengan `booking_id` terisi merujuk booking itu.
3. **Given** kasir membuat transaksi tanpa menautkan booking, **When** kasir menyimpan, **Then** transaksi tersimpan dengan `booking_id` kosong (nullable, opsional — FR-033).
4. **Given** kasir menambahkan item produk "Serum Vitamin C" (harga 150000, qty 2), **When** kasir menyimpan transaksi, **Then** subtotal item = 300000 dan stok produk berkurang 2 via mutasi `sold_pos` (FR-052); bila stok tidak mencukupi, transaksi ditolak (FR-053).
5. **Given** kasir membuat transaksi, **When** transaksi disimpan, **Then** nama dan harga setiap item adalah snapshot saat transaksi — mengubah master layanan/produk setelahnya tidak mengubah nilai item historik (R6, FR-056).

---

### User Story 2 - Nomor Invoice Unik walau Concurrent (Priority: P2)

Setiap transaksi mendapat nomor invoice unik per klinik dengan format `INV-YYYYMMDD-XXXX` (XXXX = urutan harian per klinik). Sistem menjamin nomor tetap unik walau dua kasir membuat transaksi pada saat bersamaan — generasi nomor dilakukan dalam transaksi DB dengan penguncian baris sehingga tidak ada dua transaksi yang mendapat nomor sama.

**Why this priority**: Nomor invoice duplikat merusak kepercayaan laporan, pelacakan pembayaran, dan pencetakan invoice. Race condition pada `count()+1` adalah bug diam-diam yang baru muncul saat beban nyata. Penting, tapi baru bermakna setelah transaksi dasar bisa dibuat.

**Independent Test**: Bisa diuji dengan menyimulasikan dua pembuatan transaksi konkuren pada klinik dan tanggal yang sama, lalu memverifikasi keduanya mendapat nomor invoice berurutan yang berbeda (tidak ada duplikat).

**Acceptance Scenarios**:

1. **Given** klinik belum punya transaksi pada tanggal 2026-08-14, **When** kasir membuat transaksi pertama hari itu, **Then** nomor invoice = `INV-20260814-0001`.
2. **Given** klinik sudah punya 3 transaksi pada tanggal 2026-08-14, **When** kasir membuat transaksi ke-4, **Then** nomor invoice = `INV-20260814-0004`.
3. **Given** dua kasir membuat transaksi pada klinik dan tanggal yang sama secara bersamaan, **When** kedua transaksi disimpan, **Then** masing-masing mendapat nomor invoice berurutan berbeda (mis. ...-0005 dan ...-0006) — tidak ada duplikat walau konkuren.
4. **Given** tanggal berganti ke hari berikutnya, **When** kasir membuat transaksi pertama hari baru, **Then** urutan harian reset ke `INV-YYYYMMDD-0001`.

---

### User Story 3 - Status Pembayaran 3-State & Sisa Bayar (Priority: P3)

Kasir melihat status pembayaran transaksi dalam tiga keadaan: `unpaid` (belum dibayar), `partially_paid` (dibayar sebagian), dan `paid` (lunas). Sistem memperbarui `paid_amount` (akumulasi pembayaran) dan menurunkan status otomatis saat pembayaran dicatat: lunas bila `paid_amount >= subtotal`, parsial bila `0 < paid_amount < subtotal`, belum bila 0. Antarmuka menampilkan badge status, perbandingan `paid_amount` vs `subtotal`, dan sisa bayar (`subtotal - paid_amount`) dengan label i18n untuk `partially_paid`.

**Why this priority**: Split payment (cicilan) lazim di klinik — pasien membayar uang muka lalu pelunasan. Tanpa state `partially_paid`, transaksi cicilan tampak menyesatkan (hanya unpaid/paid). Penting untuk akurasi kasir dan laporan omzet.

**Independent Test**: Bisa diuji dengan membuat transaksi subtotal 300000 lalu mencatat pembayaran 100000, memverifikasi status berubah `unpaid → partially_paid`, `paid_amount` 100000, sisa 200000; lalu pembayaran 200000, memverifikasi status `partially_paid → paid`, sisa 0.

**Acceptance Scenarios**:

1. **Given** transaksi baru subtotal 300000, **When** kasir melihat detail transaksi, **Then** badge status = `unpaid`, `paid_amount` 0, sisa bayar 300000.
2. **Given** transaksi subtotal 300000, **When** kasir mencatat pembayaran 100000, **Then** `paid_amount` = 100000, status berubah `partially_paid`, sisa bayar 200000, badge parsial tampil dengan label i18n `clinic.payment_status.partially_paid`.
3. **Given** transaksi `partially_paid` (paid 100000, subtotal 300000), **When** kasir mencatat pembayaran 200000, **Then** `paid_amount` = 300000, status berubah `paid`, sisa bayar 0.
4. **Given** kasir mencatat pembayaran yang melebihi subtotal, **When** pembayaran disimpan, **Then** sistem menampilkan peringatan kelebihan bayar; tidak ada saldo otomatis (edge case — FR-055).
5. **Given** transaksi dengan `paid_amount > 0`, **When** kasir melihat daftar transaksi, **Then** badge status 3-state tampil konsisten (warna/label berbeda per state).

---

### User Story 4 - Soft-Delete Transaksi (Priority: P4)

Admin/kasir dapat menonaktifkan (soft-delete) transaksi yang salah catat tanpa menghapus permanen catatan finansialnya. Transaksi ter-soft-delete tetap tersimpan untuk audit dan laporan, namun tidak muncul di daftar transaksi aktif. Penghapusan permanen diblokir bila transaksi masih direferensi (pembayaran, item). Pasien, kasir, dan booking yang direferensi tidak boleh terhapus begitu transaksi ada (restrict on delete).

**Why this priority**: Integritas data finansial wajib bertahan untuk audit. Soft-delete memungkinkan koreksi tanpa kehilangan jejak. Penting tapi baru relevan setelah transaksi dan pembayaran dasar ada.

**Independent Test**: Bisa diuji dengan men-soft-delete satu transaksi, lalu memverifikasi transaksi tidak muncul di daftar aktif namun tetap ada di database dengan `deleted_at` terisi, dan transaksi yang masih punya pembayaran tidak bisa di-hard-delete.

**Acceptance Scenarios**:

1. **Given** transaksi aktif dicatat salah, **When** kasir men-soft-delete transaksi, **Then** `deleted_at` terisi, transaksi tidak muncul di daftar transaksi aktif, dan data tetap utuh untuk audit.
2. **Given** transaksi ter-soft-delete, **When** admin memeriksa audit log, **Then** catatan transaksi tetap dapat ditelusuri (data finansial tidak hilang).
3. **Given** transaksi masih memiliki pembayaran, **When** admin mencoba menghapus permanen transaksi, **Then** sistem memblokir (restrict on delete FK `transaction_id`).
4. **Given** transaksi merujuk pasien/kasir/booking, **When** admin mencoba menghapus pasien/kasir/booking tersebut, **Then** sistem memblokir (restrict on delete FK `patient_id`/`cashier_id`/`booking_id`) — referensi finansial tidak boleh putus.
5. **Given** transaksi dibatalkan (FR-058), **When** pembatalan diproses, **Then** stok produk dikembalikan via mutasi `rollback` dan `cancelled_at` terisi — pembatalan bukan soft-delete.

---

### User Story 5 - Activity Log Pembayaran & Breadcrumb (Priority: P5)

Setiap aksi ubah-data pada transaksi dan pembayaran tercatat dalam activity log secara naratif. Khusus pencatatan pembayaran, log menyebutkan transisi status: "Mencatat pembayaran {nomor invoice} — status {lama}→{baru}". Halaman kasir POS dan detail transaksi menampilkan breadcrumb yang menunjukkan jalur dari beranda klinik ke halaman aktif.

**Why this priority**: Audit log naratif wajib untuk kepatuhan finansial (konstitusi VI). Breadcrumb konvensi konsistensi UI seluruh halaman dalam. Keduanya penting tapi bukan blocker fungsional inti.

**Independent Test**: Bisa diuji dengan mencatat pembayaran pada transaksi `unpaid` lalu memverifikasi activity log berisi narasi "Mencatat pembayaran … status unpaid→partially_paid"; dan membuka halaman kasir POS, memverifikasi breadcrumb menampilkan jalur induk.

**Acceptance Scenarios**:

1. **Given** transaksi `unpaid` (subtotal 300000), **When** kasir mencatat pembayaran 100000, **Then** activity log tercatat naratif "Mencatat pembayaran {invoice} — status unpaid→partially_paid" dengan siapa (causer), aksi, target, kapan, dan properti old/new.
2. **Given** transaksi `partially_paid`, **When** kasir mencatat pelunasan, **Then** activity log tercatat "Mencatat pembayaran {invoice} — status partially_paid→paid".
3. **Given** kasir membuat transaksi baru, **When** transaksi disimpan, **Then** activity log tercatat naratif pembuatan transaksi (siapa, target, kapan, atribut lengkap).
4. **Given** kasir membuka halaman kasir POS, **When** kasir melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Transaksi" dengan "Transaksi" sebagai item terakhir (bukan tautan) dan "Beranda Klinik" sebagai tautan ke rute induk.
5. **Given** kasir membuka detail transaksi, **When** kasir melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Transaksi > {Nomor Invoice}" dengan item terakhir non-tautan dan item induk dapat diklik kembali.

---

### Edge Cases

- Apa yang terjadi bila dua kasir membuat transaksi konkuren pada klinik/tanggal sama? Nomor invoice di-generate dalam DB transaction dengan penguncian baris (`lockForUpdate`) sehingga unik berurutan — tidak ada duplikat.
- Bagaimana sistem menangani pembayaran yang melebihi subtotal? Peringatan ditampilkan; tidak ada saldo otomatis dikembalikan (FR-055 edge case).
- Apa yang terjadi bila kasir mencatat pembayaran dengan jumlah 0 atau negatif? Ditolak dengan pesan validasi (amount > 0).
- Bagaimana bila stok produk tidak mencukupi saat transaksi POS? Transaksi ditolak (FR-053) — item produk butuh stok tersedia sebelum simpan.
- Apa yang terjadi bila kasir menautkan booking yang belum `done` ke transaksi? Default: booking harus `done` (FR-033) — tautan booking non-`done` ditolak dengan pesan.
- Apa yang terjadi bila transaksi di-soft-delete tetapi masih punya pembayaran? Soft-delete diizinkan (data utuh untuk audit); hard-delete diblokir restrict.
- Bagaimana menampilkan transaksi ter-soft-delete? Tidak muncul di daftar aktif; tetap ada di database dengan `deleted_at` untuk audit forensik.
- Apa yang terjadi bila item transaksi punya `product_id` dan `service_id` keduanya terisi atau keduanya kosong? Ditolak (exclusive arc, anomali #1) — tepat satu terisi.
- Bagaimana bila urutan invoice harian melebihi 9999? Format `XXXX` 4-digit; bila melebihi, nomor tetap unik per klinik per hari (urutan lanjut 5-digit) — `ponytail: sequence per tenant per hari, add pad fix bila >9999 transaksi/hari/tenant`.
- Apa yang terjadi bila booking yang ditautkan di-soft-delete/hapus? Transaksi tetap utuh (restrict on delete `booking_id`); booking tidak bisa dihapus bila transaksi merujuknya.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-049**: Sistem WAJIB menyimpan transaksi POS dengan atribut: pasien (wajib), kasir pembuat (wajib, otomatis dari user login), booking (opsional, nullable), nomor invoice (otomatis), subtotal (otomatis dari item), dan status pembayaran awal `unpaid` dengan `paid_amount` 0.
- **FR-050**: Sistem WAJIB mengaitkan setiap transaksi dengan satu pasien (FK `patient_id` not null) — transaksi tidak boleh tanpa pasien.
- **FR-033**: Sistem WAJIB mengizinkan tautan opsional dari booking berstatus `done` ke transaksi (`booking_id` nullable); booking non-`done` tidak boleh ditautkan.
- **FR-077**: Sistem WAJIB menghasilkan nomor invoice unik per klinik dengan format `INV-YYYYMMDD-XXXX` di mana XXXX = urutan harian per klinik, tergenerasi dalam DB transaction dengan penguncian baris (`lockForUpdate`) untuk menjamin keunikan walau konkuren.
- **FR-078**: Sistem WAJIB mereset urutan harian nomor invoice per klinik setiap ganti tanggal (per tenant per hari).
- **FR-079**: Sistem WAJIB menyimpan `paid_amount` (decimal(12,2), default 0, not null) sebagai akumulasi pembayaran transaksi — denormalized dari `payments` untuk status lunas/parsial tanpa SUM relasi tiap query laporan.
- **FR-055**: Sistem WAJIB mengelola `payment_status` tiga state: `unpaid` (paid_amount = 0), `partially_paid` (0 < paid_amount < subtotal), `paid` (paid_amount >= subtotal). Status dihitung ulang setiap pembayaran dicatat dalam satu DB transaction.
- **FR-080**: Sistem WAJIB menampilkan badge status pembayaran 3-state, perbandingan `paid_amount` vs `subtotal`, dan sisa bayar (`subtotal - paid_amount`) di antarmuka kasir/detail transaksi, dengan label i18n `clinic.payment_status.partially_paid` untuk state parsial.
- **FR-058**: Sistem WAJIB memproses pembatalan transaksi dengan mengisi `cancelled_at` dan mengembalikan stok produk via mutasi `rollback` (`StockService`) — pembatalan bukan soft-delete.
- **FR-081**: Sistem WAJIB menyediakan soft-delete transaksi (`deleted_at`) — transaksi finansial tidak di-hard-delete; data tetap utuh untuk audit dan laporan. Transaksi ter-soft-delete tidak muncul di daftar aktif.
- **FR-082**: Sistem WAJIB memblokir penghapusan permanen transaksi yang masih memiliki pembayaran atau item (restrict on delete FK `transaction_id`).
- **FR-083**: Sistem WAJIB menerapkan restrict on delete pada FK `patient_id`, `cashier_id`, dan `booking_id` transaksi — pasien/kasir/booking yang direferensi transaksi tidak boleh dihapus begitu transaksi ada.
- **FR-052**: Sistem WAJIB mengurangi stok produk saat transaksi POS disimpan via mutasi `sold_pos` (`StockService`); bila stok tidak mencukupi, transaksi ditolak (FR-053).
- **FR-056**: Sistem WAJIB menjaga integritas snapshot — nama (`name`) dan harga (`unit_price`) item transaksi adalah snapshot saat transaksi dibuat; mengubah master layanan/produk setelahnya tidak mengubah item historik (R6).
- **FR-084**: Sistem WAJIB mencatat activity log naratif setiap aksi ubah-data transaksi/pembayaran, termasuk "Mencatat pembayaran {invoice} — status {lama}→{baru}" dengan siapa (causer), aksi, target, kapan, dan properti old/new (status lama & baru, amount).
- **FR-085**: Sistem WAJIB memfilter daftar transaksi per-tenant secara otomatis; satu klinik tidak dapat melihat transaksi klinik lain.
- **FR-086**: Sistem WAJIB mendukung pencarian, pengurutan, dan paginasi server-side pada daftar transaksi aktif (tidak termasuk soft-deleted).
- **FR-087**: Halaman kasir POS dan detail transaksi WAJIB menampilkan breadcrumb yang menunjukkan jalur induk→halaman aktif, sesuai konvensi breadcrumb seluruh halaman dalam.

### Key Entities *(include if feature involves data)*

- **Transaction**: Pencatatan penjualan POS (produk/layanan) kepada pasien. Atribut kunci: pasien, kasir, booking (opsional), `invoice_number` (unik per tenant, format `INV-YYYYMMDD-XXXX`), subtotal, `paid_amount` (denormalized, default 0), `payment_status` (unpaid/partially_paid/paid), `cancelled_at`, `deleted_at` (soft delete). Milik satu tenant. FK `patient_id`/`cashier_id`/`booking_id` restrict on delete. `paid_amount` denormalized dari `payments`, dijaga sinkron oleh `PayTransactionAction` dalam DB transaction.
- **Payment**: Pembayaran transaksi (cicilan/split). Atribut: metode (cash/transfer/qris/debit), amount, `paid_at`. Bisa lebih dari satu per transaksi; `paid_amount` transaksi diakumulasi darinya. FK `transaction_id` cascade on delete (child admin).
- **TransactionItem**: Line item transaksi — produk (XOR layanan, exclusive arc), snapshot `name`+`unit_price` (R6/FR-056), qty, subtotal. FK `product_id`/`service_id` restrict on delete; `transaction_id` cascade.
- **Booking**: Rujukan opsional transaksi dari booking `done` (FR-033). Restriksi: booking non-`done` tidak boleh ditautkan.
- **Activity Log**: Mencatat aksi ubah-data transaksi/pembayaran secara naratif, termasuk transisi status pembayaran "Mencatat pembayaran … status {lama}→{baru}".

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Kasir dapat mencatat transaksi POS baru (pilih pasien + tambah item + simpan) dalam waktu kurang dari 1 menit.
- **SC-002**: 100% transaksi mendapat nomor invoice unik per klinik walau dibuat konkuren — 0 duplikat dalam 50 transaksi simultan per klinik per hari.
- **SC-003**: Status pembayaran akurat 100%: `unpaid`/`partially_paid`/`paid` konsisten dengan `paid_amount` vs `subtotal` setelah setiap pembayaran.
- **SC-004**: Sisa bayar (`subtotal - paid_amount`) ditampilkan akurat 100% pada detail transaksi untuk semua state pembayaran.
- **SC-005**: 100% aksi ubah-data transaksi/pembayaran tercatat dalam activity log naratif yang dapat dibaca manusia, termasuk transisi status "lama→baru".
- **SC-006**: Transaksi ter-soft-delete tidak pernah muncul di daftar transaksi aktif (0 kemunculan) namun tetap ada di database untuk audit.
- **SC-007**: Tidak ada penghapusan permanen transaksi yang masih memiliki pembayaran/item berhasil (0 keberhasilan, 100% diblokir restrict).
- **SC-008**: Tidak ada penghapusan pasien/kasir/booking yang masih direferensi transaksi berhasil (0 keberhasilan, 100% diblokir restrict on delete).
- **SC-009**: Snapshot nama dan harga item transaksi tetap utuh 100% setelah master layanan/produk diubah atau diarsipkan.
- **SC-010**: Daftar transaksi dapat diurutkan dan dicari dengan hasil tampil dalam 1 detik untuk satu klinik dengan hingga 1000 transaksi aktif.
- **SC-011**: Halaman kasir POS dan detail transaksi menampilkan breadcrumb yang benar 100% (jalur induk→aktif, item terakhir non-tautan).

## Assumptions

- Akses ke kasir POS terbatas pada peran klinik dengan izin modul transaksi (admin/cashier sesuai matriks izin klinik); otorisasi mengikuti sistem izin yang sudah ada (Gate `clinic.access` modul `transaction`).
- Otorisasi dan activity log menggunakan paket yang sudah terpasang (spatie/laravel-activitylog untuk audit log naratif via `LogAuditAction`/`activity()`).
- Endpoint API mengikuti pola tenant-scoped yang sudah ada (`/{tenant}/clinic/transactions`) dengan middleware resolve tenant aktif + `BelongsToTenant` trait.
- Layering Controller → Service → Action sudah ada (`TransactionService`, `PayTransactionAction`, `CancelTransactionAction`); spec ini merinci revisi, bukan membangun ulang arsitektur.
- `StockService::adjust()` sudah ada untuk mutasi stok; transaksi POS memakainya untuk `sold_pos` (FR-052) dan `rollback` (FR-058).
- `paid_amount` adalah denormalized kolom; reconcile job opsional ditunda hingga drift terdeteksi (`ponytail:`).
- Keputusan blocking F0 (merge `invoices` ke `transactions` / pertahankan tabel) ditangani di workflow docs/normalization/workflow.md; bila merge, `issued_at` pindah ke `transactions` dan tabel `invoices` di-drop. Spec ini mencakup `issued_at` pada transaksi bila F0 = merge.
- Frontend mengikuti pola halaman master/kasir yang sudah ada (TanStack Start, shadcn `radix-nova`, Tailwind v4) untuk konsistensi struktur, komponen UI, dan breadcrumb.
- Item transaksi exclusive arc (product XOR service, anomali #1) ditegakkan via CHECK constraint + validasi FormRequest — detail teknis di spec langkah 11 (`transaction_items`); spec ini menyatakan kebutuhan integritasnya.
- Pembuatan transaksi dari booking `done` adalah opsional; kasir juga bisa membuat transaksi tanpa booking (walk-in/produk saja).
# Feature Specification: Transaksi POS & Pembayaran

**Feature Branch**: `009-pos-transactions`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "transactions (ammar → zahiira) — setelah #8. invoice_number unique per tenant, generate INV-YYYYMMDD-XXXX. payment_status. booking_id nullable (link dari booking done, FR-033). Index (tenant_id,invoice_number) + (tenant_id,payment_status,created_at). Revisi: soft delete (deleted_at); index (tenant_id, deleted_at). Kolom baru paid_amount decimal(12,2) default 0 not null; issued_at (bila F0 = merge invoices). FK patient_id/cashier_id/booking_id → restrictOnDelete. invoice_number race fix: generateInvoiceNumber() count()+1 → lockForUpdate dalam DB transaction / sequence per tenant per hari. AC: transaksi POS baru + link booking; invoice number unik walau concurrent; soft-delete transaksi; activity log 'Mencatat pembayaran … status {lama}→{baru}'. FE: kasir POS + badge payment_status 3-state + paid_amount vs subtotal + sisa bayar + label i18n clinic.payment_status.partially_paid + breadcrumb. Data model sumber kebenaran di docs/normalization/README.md + docs/erd/."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Catat Transaksi POS & Cetak Invoice (Priority: P1)

Kasir klinik mencatat penjualan layanan dan/atau produk kepada pasien melalui kasir POS. Kasir memilih pasien, menambahkan item (layanan dan/atau produk beserta qty), dan menentukan kasir pembuat. Sistem menghasilkan nomor invoice unik per tenant per hari (`INV-YYYYMMDD-XXXX`), menyimpan transaksi beserta line item (nama dan harga ter-snapshot dari master), serta menampilkan invoice yang dapat dicetak. Bila transaksi berasal dari kunjungan booking yang sudah selesai (`done`), kasir dapat menautkan transaksi ke booking tersebut.

**Why this priority**: Transaksi POS adalah sumber pendapatan klinik dan fondasi seluruh laporan omzet. Tanpa kemampuan mencatat penjualan, tidak ada pembayaran, tidak ada laporan keuangan, dan rekam medis dari booking done tidak dapat ditagih. Ini inti operasional finansial.

**Independent Test**: Dapat diuji dengan membuat satu transaksi baru (pasien + satu item layanan + satu item produk), lalu memverifikasi nomor invoice terformat benar dan unik, subtotal terhitung dari item, dan invoice tampil siap cetak — seluruhnya tanpa melibatkan modul pembayaran parsial atau laporan omzet.

**Acceptance Scenarios**:

1. **Given** kasir sudah login dengan izin modul POS, **When** kasir mengisi transaksi baru dengan pasien, dua item (satu layanan satu produk), qty masing-masing 1, lalu menyimpan, **Then** transaksi tersimpan dengan `invoice_number` berformat `INV-YYYYMMDD-0001`, subtotal sama dengan jumlah subtotal item, `payment_status` `unpaid`, `paid_amount` 0, dan item menyimpan nama serta harga sesuai master saat transaksi dibuat.
2. **Given** ada booking "Facial Glow — dr. Andi" berstatus `done`, **When** kasir membuat transaksi untuk pasien booking tersebut dan menautkannya, **Then** transaksi tersimpan dengan `booking_id` terisi dan booking tetap utuh.
3. **Given** dua kasir membuat transaksi bersamaan pada tenant dan hari yang sama, **When** kedua transaksi disimpan hampir bersamaan, **Then** kedua transaksi mendapat nomor invoice berbeda dan berurutan (`...0001` dan `...0002`) — tidak ada duplikasi `invoice_number`.
4. **Given** transaksi sudah dibuat, **When** kasir mencetak invoice, **Then** konten invoice dirender dari transaksi + item + pembayaran + tenant + pasien (R4), bukan kolom duplikat, dan `issued_at` tercatat saat pertama kali diterbitkan.
5. **Given** admin mencoba menghapus permanen pasien/kasir/booking yang masih direferensi transaksi, **When** penghapusan dijalankan, **Then** sistem memblokir penghapusan (restrict) — transaksi tetap utuh sebagai riwayat finansial.

---

### User Story 2 - Pembayaran, Status Pembayaran & Sisa Bayar (Priority: P2)

Kasir menerima pembayaran atas transaksi — bisa lunas sekaligus atau cicilan/split payment. Setiap pembayaran mencatat metode (tunai/transfer/qris/debit), jumlah, dan waktu diterima. Sistem memperbarui `paid_amount` (akumulasi pembayaran) dan `payment_status` (`unpaid`/`partially_paid`/`paid`) secara atomik bersama pencatatan pembayaran. Kasir dan staf melihat badge status pembayaran 3-state, perbandingan `paid_amount` vs subtotal, dan sisa bayar yang masih harus dibayar.

**Why this priority**: Pembayaran menutup siklus transaksi dan menggerakkan status pembayaran yang dipakai seluruh laporan omzet. Penting, tetapi transaksi POS dasar (P1) harus ada lebih dulu agar ada sesuatu untuk dibayar.

**Independent Test**: Dapat diuji dengan membuat transaksi lalu mencatat dua pembayaran berturut (cicilan), memverifikasi `paid_amount` bertambah, `payment_status` bertransisi `unpaid`→`partially_paid`→`paid`, dan sisa bayar berkurang hingga nol — tanpa melibatkan modul pembatalan atau laporan.

**Acceptance Scenarios**:

1. **Given** transaksi "INV-...-0003" berstatus `unpaid` dengan subtotal 500.000, **When** kasir mencatat pembayaran 200.000 via tunai, **Then** `paid_amount` menjadi 200.000, `payment_status` menjadi `partially_paid`, sisa bayar terhitung 300.000, dan activity log tercatat naratif "Mencatat pembayaran transaksi INV-...-0003 — status berubah dari 'unpaid' ke 'partially_paid'".
2. **Given** transaksi di atas berstatus `partially_paid`, **When** kasir mencatat pembayaran 300.000 via qris, **Then** `paid_amount` menjadi 500.000, `payment_status` menjadi `paid`, sisa bayar 0, dan activity log tercatat naratif "Mencatat pembayaran transaksi INV-...-0003 — status berubah dari 'partially_paid' ke 'paid'".
3. **Given** transaksi berstatus `paid`, **When** staf melihat baris transaksi di daftar, **Then** badge status pembayaran menampilkan tiga state berbeda secara konsisten (`unpaid`/`partially_paid`/`paid`) dengan label i18n (`clinic.payment_status.partially_paid` dll), serta perbandingan `paid_amount` vs subtotal dan sisa bayar.
4. **Given** kasir mencatat pembayaran yang jumlahnya melebihi sisa (kelebihan bayar), **When** pembayaran disimpan, **Then** sistem memberi peringatan kelebihan bayar namun tidak membuat saldo otomatis — `paid_amount` tetap diupdate dan status menjadi `paid`.

---

### User Story 3 - Pembatalan Transaksi & Soft Delete (Priority: P3)

Kasir/supervisor dapat membatalkan transaksi yang salah catat. Pembatalan menandai `cancelled_at`, mengembalikan stok produk yang sudah terpotong saat transaksi dibuat (rollback), dan mempertahankan transaksi sebagai riwayat audit — transaksi finansial tidak pernah dihapus permanen. Transaksi dapat di-soft-delete (sembunyi dari daftar aktif) tanpa menghapus record fisik.

**Why this priority**: Pembatalan menjaga integritas stok dan audit finansial. Penting untuk koreksi operasional, namun baru relevan setelah transaksi dan pembayaran dasar berjalan.

**Independent Test**: Dapat diuji dengan membuat transaksi berisi produk, lalu membatalkannya, memverifikasi `cancelled_at` terisi, stok produk kembali ke saldo sebelum transaksi, dan record transaksi tetap ada (tidak hilang) — tanpa melibatkan laporan omzet.

**Acceptance Scenarios**:

1. **Given** transaksi berisi 2 produk dengan stok sudah terpotong saat dibuat, **When** kasir membatalkan transaksi, **Then** `cancelled_at` terisi, stok produk dikembalikan via mutasi rollback, dan transaksi tetap ada sebagai riwayat (tidak hard-delete).
2. **Given** supervisor ingin menyembunyikan transaksi dari daftar aktif, **When** transaksi di-soft-delete, **Then** transaksi tidak muncul di daftar aktif per tenant namun record fisik tetap ada (query memfilter `deleted_at IS NULL`), dan dapat dipulihkan bila perlu.
3. **Given** transaksi sudah dibatalkan, **When** staf melihat daftar transaksi aktif, **Then** transaksi dibatalkan tidak dihitung sebagai omzet aktif dan ditandai jelas sebagai batal.

---

### User Story 4 - Breadcrumb Navigasi POS (Priority: P4)

Staf klinik menavigasi halaman kasir POS, daftar transaksi, dan detail/invoice dengan breadcrumb yang menunjukkan jalur dari beranda klinik ke halaman aktif.

**Why this priority**: Breadcrumb konvensi konsistensi UI seluruh halaman dalam; nilai orientasi tinggi namun bukan blocker fungsional inti.

**Independent Test**: Dapat diuji dengan membuka halaman kasir POS dan daftar transaksi, lalu memverifikasi breadcrumb menampilkan jalur induk yang dapat diklik kembali ke beranda klinik.

**Acceptance Scenarios**:

1. **Given** kasir berada di halaman kasir POS, **When** kasir melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Kasir POS" dengan "Kasir POS" sebagai item terakhir (non-tautan) dan "Beranda Klinik" sebagai tautan ke rute induk.
2. **Given** kasir membuka detail transaksi tertentu, **When** kasir melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Transaksi > INV-...-0003" dengan item terakhir non-tautan dan "Transaksi" sebagai tautan ke daftar transaksi.

---

### Edge Cases

- Apa yang terjadi bila dua kasir membuat transaksi bersamaan pada tenant/hari yang sama? Generator nomor invoice memakai `lockForUpdate` dalam DB transaction (atau sequence per tenant per hari) sehingga nomor unik dan berurutan — tidak ada duplikasi walau konkuren.
- Apa yang terjadi bila pembayaran melebihi subtotal (kelebihan bayar)? Sistem memberi peringatan; tidak ada saldo otomatis dibuat. `paid_amount` diupdate, status jadi `paid`. Penanganan kelebihan bayar manual di luar MVP.
- Apa yang terjadi bila item transaksi merujuk produk yang stoknya tidak cukup? Validasi stok menolak penyimpanan dengan pesan stok tidak cukup (FR-053).
- Apa yang terjadi bila produk/layanan master diubah atau diarsip setelah transaksi dibuat? Transaksi lama tetap utuh — `transaction_items.name` dan `unit_price` adalah snapshot immutable (R6, FR-056).
- Apa yang terjadi bila kasir menambahkan item tanpa produk maupun layanan? Ditolak — tepat satu dari `product_id`/`service_id` harus terisi (aturan exclusive arc, Anomali #1).
- Bagaimana menangani transaksi yang ditautkan ke booking yang belum `done`? `booking_id` hanya boleh diisi bila booking berstatus `done` (FR-033); booking belum selesai tidak dapat ditautkan.
- Apa yang terjadi bila transaksi dibatalkan setelah pembayaran dicatat? Pembatalan mengembalikan stok; `paid_amount` tetap sebagai catatan (penanganan refund manual di luar MVP — `ponytail: refund workflow add saat butuh`).
- Apa yang terjadi bila soft-delete transaksi yang sudah dibatalkan? Soft-delete hanya menyembunyikan dari daftar aktif (memfilter `deleted_at IS NULL`); record fisik tetap untuk audit.
- Bagaimana `payment_status` diturunkan? `unpaid` saat `paid_amount` = 0, `partially_paid` saat 0 < `paid_amount` < `subtotal`, `paid` saat `paid_amount` >= `subtotal`. Dihitung otomatis dari `paid_amount` vs `subtotal` — bukan input manual.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-041**: Sistem WAJIB menyimpan transaksi POS dengan pasien, kasir pembuat, dan kumpulan line item (layanan dan/atau produk beserta qty). Setiap item menyimpan snapshot nama dan harga dari master saat transaksi dibuat (R6, FR-056).
- **FR-042**: Sistem WAJIB menghasilkan `invoice_number` unik per tenant per hari dengan format `INV-YYYYMMDD-XXXX` (XXXX urut per tenant per hari). Generator WAJIB aman terhadap kondisi race (concurrent insert) — menggunakan `lockForUpdate` dalam DB transaction atau sequence per tenant per hari sehingga tidak ada duplikasi nomor walau dua kasir menyimpan bersamaan.
- **FR-043**: Sistem WAJIB menanggung `subtotal` transaksi sebagai jumlah `transaction_items.subtotal` (`unit_price * qty`), dihitung saat simpan transaksi dalam DB transaction. `subtotal` adalah denormalized yang dijaga sinkron saat pembuatan transaksi.
- **FR-044**: Sistem WAJIB menyediakan kolom `booking_id` nullable pada transaksi untuk menautkan transaksi ke booking yang berstatus `done` (FR-033). Booking belum `done` tidak dapat ditautkan. FK `booking_id` memakai `restrictOnDelete`.
- **FR-045**: Sistem WAJIB memaksa FK `patient_id`, `cashier_id`, dan `booking_id` pada transaksi memakai `restrictOnDelete` — penghapusan permanen pasien/kasir/booking yang masih direferensi transaksi diblokir. Transaksi tetap utuh sebagai riwayat finansial.
- **FR-046**: Sistem WAJIB menyediakan kolom `paid_amount` (decimal, default 0, not null) sebagai denormalized akumulasi `SUM(payments.amount)`, dijaga sinkron oleh aksi pencatatan pembayaran dalam DB transaction, sehingga status lunas/parsial dapat di-query tanpa SUM relasi pada setiap query laporan omzet (FR-070).
- **FR-047**: Sistem WAJIB menurunkan `payment_status` dari `paid_amount` vs `subtotal`: `unpaid` saat `paid_amount` = 0, `partially_paid` saat 0 < `paid_amount` < `subtotal`, `paid` saat `paid_amount` >= `subtotal`. Status diturunkan otomatis, bukan input manual.
- **FR-048**: Sistem WAJIB menerbitkan invoice dengan `issued_at` tercatat saat pertama kali diterbitkan. Konten invoice dirender dari transaksi + item + pembayaran + tenant + pasien (R4) — tidak ada duplikasi kolom data transaksi pada tabel invoice. Kebutuhan tabel `invoices` terpisah dievaluasi vs merge `issued_at` ke `transactions` (YAGNI — default MVP: merge bila tidak ada kebutuhan nomor invoice terpisah/multi-cetak/status cetak).
- **FR-049**: Sistem WAJIB memvalidasi item transaksi: tepat satu dari `product_id`/`service_id` terisi per item (exclusive arc), qty > 0, dan stok produk cukup saat simpan (FR-053).
- **FR-050**: Sistem WAJIB mengaitkan setiap transaksi dengan pasien (`patient_id` not null) — transaksi POS selalu memiliki pasien terdaftar.
- **FR-051**: Sistem WAJIB mencatat pencatatan pembayaran dengan metode (`cash`/`transfer`/`qris`/`debit`), jumlah (>0), dan waktu diterima (`paid_at`). Satu transaksi dapat memiliki lebih dari satu pembayaran (cicilan/split payment).
- **FR-052**: Sistem WAJIB mengurangi stok produk (mutasi `sold_pos`) saat transaksi dengan item produk disimpan, via `StockService` dalam DB transaction yang sama dengan transaksi.
- **FR-053**: Sistem WAJIB memvalidasi ketersediaan stok produk sebelum mengurangi — bila stok tidak cukup, penyimpanan transaksi ditolak dengan pesan stok tidak cukup.
- **FR-054**: Sistem WAJIB membatasi metode pembayaran ke enum `cash`/`transfer`/`qris`/`debit`.
- **FR-055**: Sistem WAJIB memperbarui `paid_amount` (menambah jumlah pembayaran) dan menurunkan `payment_status` (`unpaid`/`partially_paid`/`paid`) secara atomik dalam satu DB transaction bersama pencatatan pembayaran. Status `partially_paid` dikenali saat 0 < `paid_amount` < `subtotal` (split payment).
- **FR-056**: Sistem WAJIB menyimpan snapshot `name` dan `unit_price` pada `transaction_items` saat transaksi dibuat, sehingga transaksi lama tetap utuh walau master produk/layanan diubah atau diarsip (R6).
- **FR-057**: Sistem WAJIB mencatat activity log naratif untuk setiap aksi ubah-data transaksi/pembayaran, termasuk pencatatan pembayaran dengan narasi status lama→baru ("Mencatat pembayaran transaksi {invoice_number} — status berubah dari '{lama}' ke '{baru}'"), pencacatan siapa (causer), aksi, target, dan kapan. `withProperties` menyimpan atribut yang diubah beserta nilai lama dan baru (untuk pembayaran: `old`/`new` `paid_amount` dan `payment_status`).
- **FR-058**: Sistem WAJIB mendukung pembatalan transaksi — menandai `cancelled_at` dan mengembalikan stok produk yang terpotong via mutasi `rollback` (StockService) dalam DB transaction. Sistem WAJIB mendukung soft-delete transaksi (`deleted_at`) — transaksi yang di-soft-delete tidak muncul di daftar aktif per tenant (index `(tenant_id, deleted_at)`) namun record fisik tetap untuk audit. Pembatalan bukan penghapusan; transaksi finansial tidak pernah di-hard-delete — riwayat audit finansial utuh.
- **FR-077**: Sistem WAJIB menyediakan tampilan kasir POS yang memungkinkan kasir memilih pasien, menambah item (layanan/produk + qty), menautkan booking done (opsional), dan menyimpan transaksi. Tampilan menampilkan subtotal, dan setelah pembayaran: badge status pembayaran 3-state (`unpaid`/`partially_paid`/`paid`), perbandingan `paid_amount` vs subtotal, dan sisa bayar.
- **FR-078**: Sistem WAJIB menyediakan label i18n untuk tiga state `payment_status` (`clinic.payment_status.unpaid`/`partially_paid`/`paid`) dalam bahasa Indonesia semi-formal friendly, ditampilkan pada badge dan daftar transaksi.
- **FR-079**: Sistem WAJIB menyediakan breadcrumb pada halaman kasir POS, daftar transaksi, dan detail/invoice yang menampilkan jalur beranda klinik → halaman aktif, dengan item terakhir non-tautan dan item induk sebagai tautan ke rute induk.

### Key Entities *(include if feature involves data)*

- **Transaction**: Penjualan POS treatment & produk. Atribut kunci: pasien, kasir pembuat, booking (opsional, dari booking done), `invoice_number` (unik per tenant per hari, `INV-YYYYMMDD-XXXX`), `subtotal` (denormalized sum item), `paid_amount` (denormalized sum pembayaran, default 0), `payment_status` (`unpaid`/`partially_paid`/`paid`), `cancelled_at`, `deleted_at` (soft delete). Milik satu tenant. FK `patient_id`/`cashier_id`/`booking_id` memakai `restrictOnDelete`; FK `tenant_id` memakai `cascadeOnDelete`. Soft-delete (`deleted_at`); pembatalan via `cancelled_at` (bukan hapus). Index: `(tenant_id, invoice_number)` UNIQUE, `(tenant_id, payment_status, created_at)`, `(tenant_id, deleted_at)`.
- **TransactionItem**: Line item penjualan. Atribut: transaksi, produk (nullable) ATAU layanan (nullable) — exclusive arc (tepat satu), `name` + `unit_price` snapshot immutable (R6, FR-056), qty (>0), `subtotal` (`unit_price * qty`). Milik satu tenant. FK `transaction_id` cascadeOnDelete; FK `product_id`/`service_id` restrictOnDelete.
- **Payment**: Pembayaran transaksi. Atribut: transaksi, metode (`cash`/`transfer`/`qris`/`debit`), jumlah (>0), `paid_at`. Satu transaksi dapat punya banyak pembayaran (cicilan/split). FK `transaction_id` cascadeOnDelete. Pencatatan pembayaran memperbarui `transactions.paid_amount` + `payment_status` atomik.
- **Invoice**: Penerbitan invoice transaksi (1:1 per transaksi). `issued_at` tercatat saat pertama diterbitkan. Konten dirender dari relasi (R4) — bukan kolom duplikat. Kandidat merge `issued_at` ke `transactions` bila tidak ada kebutuhan nomor invoice terpisah/multi-cetak (YAGNI, default MVP: merge).
- **Patient**: Pasien transaksi (`patient_id` not null). Tidak dapat dihapus permanen bila masih direferensi transaksi (restrict).
- **User (Cashier)**: Kasir pembuat transaksi (`cashier_id`). Tidak dapat dihapus permanen bila masih direferensi transaksi (restrict).
- **Booking**: Sumber tautan opsional transaksi (`booking_id`, hanya bila status `done`, FR-033). Tidak dapat dihapus bila masih direferensi transaksi (restrict).
- **Activity Log**: Mencatat aksi ubah-data transaksi/pembayaran secara naratif, termasuk transisi `payment_status` lama→baru dengan `withProperties` menyimpan `paid_amount` dan `payment_status` lama dan baru.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-014**: Kasir dapat mencatat transaksi POS baru (pasien + item + simpan) dalam waktu kurang dari 45 detik per transaksi.
- **SC-015**: 100% nomor invoice unik per tenant per hari walau dibuat bersamaan (concurrent) — tidak ada satupun duplikasi `invoice_number` pada kondisi race.
- **SC-016**: 100% transisi `payment_status` (`unpaid`→`partially_paid`→`paid`) terjadi benar saat `paid_amount` melintasi ambang batas — tidak ada status yang menyesatkan laporan omzet.
- **SC-017**: 100% aksi pencatatan pembayaran tercatat dalam activity log dengan deskripsi naratif status lama→baru yang dapat dibaca manusia ("Mencatat pembayaran … status {lama}→{baru}").
- **SC-018**: 100% upaya menghapus permanen pasien/kasir/booking yang masih direferensi transaksi diblokir (restrict) — tidak ada transaksi yatim karena parent dihapus.
- **SC-019**: 100% transaksi finansial tetap ada sebagai record (soft-delete/pembatalan), tidak ada hard-delete — riwayat audit finansial utuh.
- **SC-020**: 100% pembatalan transaksi mengembalikan stok produk yang terpotong saat transaksi dibuat — saldo stok konsisten pasca-pembatalan.
- **SC-021**: 100% transaksi yang ditautkan ke booking merujuk booking berstatus `done` — tidak ada transaksi ditautkan ke booking belum selesai.
- **SC-022**: Tampilan kasir POS dan daftar transaksi menampilkan badge status pembayaran 3-state, perbandingan `paid_amount` vs subtotal, dan sisa bayar secara konsisten dengan label i18n Indonesia.

## Assumptions

- Akses ke halaman dan endpoint POS/transaksi terbatas pada peran klinik dengan izin modul POS (sesuai matriks izin klinik); otorisasi mengikuti sistem izin yang sudah ada (Gate `clinic.access`, modul `pos`/`transaction`).
- Endpoint API mengikuti pola tenant-scoped yang sudah ada (`/{tenant}/clinic/transactions` + `/{tenant}/clinic/transactions/{id}/payments` dll) dengan middleware resolve tenant + tenant aktif.
- Otorisasi dan activity log menggunakan paket yang sudah terpasang (spatie/laravel-activitylog untuk audit log); role statis klinik tetap memakai enum + Gate matrix sesuai konstitusi pengecualian.
- Data model sumber kebenaran mengikuti `docs/normalization/README.md` dan `docs/erd/transactions.md` + `payments.md` + `transaction_items.md` + `invoices.md`; revisi soft-delete `transactions`, `paid_amount` denormalized, `payment_status` 3-state, FK `restrictOnDelete`, dan race-fix `invoice_number` sudah tercatat di dokumen tersebut.
- `paid_amount` denormalized dari `payments`, dijaga sinkron oleh aksi pencatatan pembayaran dalam DB transaction; `ponytail: reconcile dari SUM(payments) add saat drift terdeteksi`.
- Stok produk di-check (FR-053) dan adjust (FR-052/rollback) via `StockService` yang sudah ada dari fitur #007 product-master; tipe mutasi `sold_pos` dan `rollback` sudah terdefinisi.
- Kelebihan bayar hanya memberi peringatan — tidak ada saldo otomatis atau workflow refund di MVP (`ponytail: refund workflow add saat butuh`).
- Tabel `invoices` kandidat merge `issued_at` ke `transactions` (YAGNI, Anomali normalisasi 1:1) — keputusan default MVP: merge bila tidak ada kebutuhan nomor invoice terpisah/multi-cetak/status cetak; pertahankan tabel bila ada kebutuhan tersebut.
- Exclusive arc `transaction_items` (`product_id`/`service_id`) ditegakkan app-level (FormRequest) di MVP; CHECK constraint DB-level `ponytail: add saat audit integritas data berikutnya` (Anomali #1).
- Frontend mengikuti pola halaman master/POS yang sudah ada untuk konsistensi struktur, komponen UI (shadcn/radix-nova), dan breadcrumb; badge status 3-state memakai token design system yang ada.
- Implementasi backend (ammar) lalu test (zahiira) sesuai alur delegasi project; skill relevan (`/laravel-best-practices`, `/clean-code-principles`) dipakai saat penulisan kode.
# Feature Specification: Integritas Item Transaksi, Pembayaran Cicilan & Cetak Invoice

**Feature Branch**: `011-payments-invoices`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "transaction_items (ammar → zahiira) — setelah #9, #5, #7. Exclusive arc product_id XOR service_id. Snapshot name+unit_price (R6/FR-056). Stok check (FR-053) + adjust (FR-052) via StockService. Revisi: anomali #1 CHECK constraint + app validation. FK restrictOnDelete; tenant-id invariant. Payments (langkah 12) — split payment, method, paid_at, PaymentStatus 3-state + paid_amount sync, tenant invariant. Invoices (langkah 13) — 1 per transaction, render dari relasi (R4), F0 merge. Sumber: docs/erd/ + docs/normalization/README.md + docs/normalization/workflow.md."

## Konteks

Spec ini **melengkapi spec 008 (Transaksi POS)**, bukan duplikat. Spec 008 sudah mencakup kebutuhan konseptual (snapshot, app validation exclusive arc, payment_status 3-state, paid_amount, F0 merge invoices, InvoiceController render) dan implementasi backend inti. Spec 011 menggarap **tiga celah** yang 008 eksplisit tunda ke langkah 11–13 workflow normalisasi:

1. **Integritas data level basis** pada `transaction_items` — penegakan exclusive arc di tingkat basis (anomali #1), FK restrict master, dan invariant tenant (anomali #3). 008 hanya menegakkan exclusive arc di lapis aplikasi; spec ini menutup celah data integrity yang bisa bocor lewat seed/job/bug.
2. **Pengalaman pembayaran cicilan** di sisi pengguna — halaman bayar multi-cicilan dengan ringkasan sisa, riwayat pembayaran, dan peringatan kelebihan bayar. 008 hanya menampilkan badge status; spec ini menyediakan alur aksi pembayaran lengkap.
3. **Cetak invoice** di sisi pengguna — halaman/tombol cetak invoice yang merender konten dari relasi transaksi (R4), bukan kolom duplikat. 008 menyiapkan backend render; spec ini melengkapinya dengan pengalaman cetak pengguna.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Item Transaksi Tepat Satu Produk atau Layanan (Priority: P1)

Kasir menambahkan line item ke transaksi POS. Setiap item harus merujuk tepat satu entitas: sebuah produk ATAU sebuah layanan — tidak boleh keduanya, tidak boleh kosong. Sistem menolak item yang melanggar aturan ini, baik saat diisi lewat antarmuka kasir maupun lewat jalur lain (data import, migrasi, perbaikan data). Nama dan harga item adalah snapshot saat transaksi dibuat; mengubah master produk/layanan setelahnya tidak mengubah item historik. Item produk memerlukan stok cukup dan memicu pengurangan stok; item layanan tidak menyentuh stok.

**Why this priority**: Integritas item transaksi adalah fondasi laporan penjualan treatment (FR-071) dan produk (FR-072). Item dengan kedua referensi kosong/terisi melesetkan laporan dan merusak kepercayaan data finansial. Penegakan hanya di lapis aplikasi rentan dibypass oleh jalur non-UI; penegakan level basis menutup celah diam-diam.

**Independent Test**: Bisa diuji dengan menyisipkan item dengan kedua referensi terisi langsung ke basis data (meniru jalur non-UI) dan memverifikasi penolakan terjadi di tingkat constraint, tanpa melibatkan antarmuka kasir atau modul pembayaran.

**Acceptance Scenarios**:

1. **Given** basis data `transaction_items` ada, **When** ada upaya menyisipkan baris dengan `product_id` dan `service_id` keduanya terisi, **Then** basis data menolak baris tersebut (constraint exclusive arc) — tidak ada item ambigu tersimpan.
2. **Given** basis data `transaction_items` ada, **When** ada upaya menyisipkan baris dengan `product_id` dan `service_id` keduanya kosong, **Then** basis data menolak baris tersebut — setiap item harus punya rujukan.
3. **Given** kasir menambahkan item melalui antarmuka dengan kedua referensi terisi, **When** kasir menyimpan, **Then** sistem menolak dengan pesan jelas (status 422) sebelum menyentuh basis data.
4. **Given** transaksi dengan item layanan "Facial Basic" harga 200000 sudah tersimpan, **When** harga master layanan diubah menjadi 250000, **Then** item historik tetap menampilkan harga 200000 (snapshot immutable, R6, FR-056).
5. **Given** transaksi dengan item produk sudah tersimpan, **When** master produk dihapus permanen, **Then** penghapusan diblokir karena item masih merujuk produk tersebut (FK restrict); arsip (`status=archived`) tetap diizinkan dan item tetap utuh.

---

### User Story 2 - Pembayaran Cicilan Bertahap (Priority: P2)

Pasien membayar transaksi secara bertahap (cicilan/split). Kasir membuka halaman pembayaran transaksi, melihat subtotal, total sudah dibayar, dan sisa bayar. Kasir mencatat pembayaran parsial; status transaksi bergerak dari `unpaid` ke `partially_paid`. Setiap pembayaran tercatat dengan metode (tunai/transfer/QRIS/debit), jumlah, dan waktu diterima. Saat akumulasi pembayaran mencapai atau melebihi subtotal, status berubah menjadi `paid`. Pembayaran yang melebihi sisa memunculkan peringatan kelebihan bayar tanpa saldo otomatis. Kasir dapat melihat riwayat semua pembayaran pada transaksi tersebut.

**Why this priority**: Cicilan lazim di klinik kecantikan — pasien membayar muka lalu melunasi. Tanpa alur pembayaran bertahap yang lengkap, kasir harus mencatat pembayaran tanpa visibilitas sisa dan riwayat, rawan kesalahan dan kebocoran omzet. Penting, tapi baru bermakna setelah transaksi dasar dan integritas item terjamin.

**Independent Test**: Bisa diuji dengan membuat satu transaksi subtotal 300000, lalu mencatat tiga pembayaran parsial (100000, 100000, 100000) dan memverifikasi sisa bayar berkurang, status bergerak `unpaid → partially_paid → paid`, dan semua pembayaran muncul di riwayat — tanpa melibatkan cetak invoice.

**Acceptance Scenarios**:

1. **Given** transaksi baru subtotal 300000 status `unpaid`, **When** kasir membuka halaman pembayaran, **Then** terlihat subtotal 300000, sudah dibayar 0, sisa bayar 300000, dan status `unpaid`.
2. **Given** transaksi subtotal 300000 status `unpaid`, **When** kasir mencatat pembayaran 100000 metode tunai, **Then** sudah dibayar menjadi 100000, sisa 200000, status berubah `partially_paid`.
3. **Given** transaksi `partially_paid` (sudah dibayar 200000 dari 300000), **When** kasir mencatat pelunasan 100000, **Then** sudah dibayar 300000, sisa 0, status berubah `paid`.
4. **Given** transaksi `partially_paid` (sisa 50000), **When** kasir mencatat pembayaran 80000 (melebihi sisa), **Then** sistem menampilkan peringatan kelebihan bayar; pembayaran tetap tercatat namun pengguna diperingatkan.
5. **Given** transaksi punya tiga pembayaran tercatat, **When** kasir membuka riwayat pembayaran, **Then** ketiga pembayaran muncul terurut dengan metode, jumlah, dan waktu masing-masing.

---

### User Story 3 - Cetak Invoice dari Relasi Transaksi (Priority: P3)

Kasir mencetak invoice untuk transaksi yang sudah diterbitkan. Konten invoice — informasi klinik, pasien, nomor invoice, tanggal terbit, daftar item (nama, harga, qty, subtotal), total, dan pembayaran — di-render dari data transaksi dan relasinya, bukan dari kolom duplikat. Satu invoice per transaksi. Halaman cetak dapat diprint (browser print) dan menampilkan breadcrumb yang menempatkan invoice dalam hierarki transaksi.

**Why this priority**: Invoice adalah dokumen finansial yang diberikan ke pasien. Tanpa halaman cetak, kasir tidak bisa menghasilkan dokumen fisik/digital dari transaksi. Konten dari relasi mencegah drift antara invoice dan transaksi. Penting, tapi baru bermakna setelah transaksi dan pembayaran utuh.

**Independent Test**: Bisa diuji dengan membuat transaksi berisi item dan pembayaran, lalu membuka halaman cetak invoice dan memverifikasi semua item, total, dan pembayaran tampil sesuai data transaksi — tanpa melibatkan pembuatan transaksi baru.

**Acceptance Scenarios**:

1. **Given** transaksi sudah diterbitkan (ada tanggal terbit), **When** kasir membuka halaman invoice, **Then** header menampilkan nama klinik, pasien, nomor invoice, dan tanggal terbit.
2. **Given** transaksi punya dua item (layanan + produk), **When** kasir melihat isi invoice, **Then** kedua item tampil dengan nama, harga satuan, qty, dan subtotal sesuai snapshot transaksi.
3. **Given** transaksi punya dua pembayaran cicilan, **When** kasir melihat bagian pembayaran invoice, **Then** kedua pembayaran tampil dengan metode, jumlah, dan waktu; total sudah dibayar dan sisa (jika ada) tercantum.
4. **Given** kasir di halaman invoice, **When** kasir melihat breadcrumb, **Then** terlihat "Beranda Klinik > Transaksi > {Nomor Invoice}" dengan item terakhir non-tautan dan item induk dapat diklik kembali.
5. **Given** transaksi diubah (item/pembayaran tambahan), **When** kasir membuka kembali invoice, **Then** konten invoice selalu mencerminkan kondisi transaksi terbaru — tidak ada data invoice statis yang stagnan (R4, render dari relasi).

---

### Edge Cases

- Apa yang terjadi bila upaya penyisipan item langsung ke basis (seed/job) melanggar exclusive arc? Ditolak di tingkat constraint basis — pertahanan integritas data, bukan hanya UI.
- Bagaimana bila pembayaran dicatat pada transaksi yang sudah `paid`? Sisa sudah 0; pembayaran tambahan memunculkan peringatan kelebihan bayar (FR-055 edge case).
- Apa yang terjadi bila item produk ditambahkan namun stok tidak mencukupi? Transaksi ditolak sebelum item tersimpan (FR-053); tidak ada mutasi stok parsial.
- Bagaimana bila invoice diakses untuk transaksi yang belum diterbitkan (belum ada tanggal terbit)? Invoice belum tersedia — transaksi harus diterbitkan lebih dulu.
- Apa yang terjadi bila child item/pembayaran dibuat lintas tenant dari parent-nya? Ditolak/tidak mungkin karena tenant selalu diwarisi dari parent transaksi (anomali #3 invariant) — tidak ada child lintas-tenant.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem WAJIB menegakkan exclusive arc pada item transaksi di tingkat basis data — tepat satu dari `product_id`/`service_id` terisi; keduanya terisi atau keduanya kosong ditolak (anomali #1 normalisasi, R9).
- **FR-002**: Sistem WAJIB menolak penghapusan permanen master produk/layanan yang masih dirujuk item transaksi (FK restrict); pengarsipan (`status=archived`) tetap diizinkan tanpa memutus rujukan.
- **FR-003**: Sistem WAJIB menjaga invariant tenant pada item transaksi dan pembayaran — tenant child selalu sama dengan tenant parent transaksi; tidak ada child lintas-tenant (anomali #3 normalisasi).
- **FR-004**: Sistem WAJIB menjaga snapshot item transaksi — nama dan harga satuan adalah snapshot saat transaksi dibuat; perubahan master produk/layanan tidak mengubah item historik (R6, FR-056).
- **FR-005**: Sistem WAJIB menyediakan halaman pembayaran transaksi yang menampilkan subtotal, total sudah dibayar (`paid_amount`), sisa bayar, dan status pembayaran 3-state.
- **FR-006**: Sistem WAJIB mengizinkan pencatatan pembayaran parsial (cicilan/split) dengan metode (tunai/transfer/QRIS/debit), jumlah, dan waktu diterima (`paid_at`).
- **FR-007**: Sistem WAJIB memperbarui `paid_amount` dan `payment_status` secara akumulatif setiap pembayaran dicatat — `unpaid` (0), `partially_paid` (0 < dibayar < subtotal), `paid` (dibayar >= subtotal) — dalam satu operasi atomik (FR-055).
- **FR-008**: Sistem WAJIB memperingatkan kasir saat pembayaran melebihi sisa bayar; tidak ada saldo otomatis dikembalikan (FR-055 edge case).
- **FR-009**: Sistem WAJIB menampilkan riwayat semua pembayaran pada transaksi, terurut, dengan metode, jumlah, dan waktu.
- **FR-010**: Sistem WAJIB menyediakan satu invoice per transaksi yang sudah diterbitkan, dengan konten di-render dari relasi transaksi (item, pembayaran, klinik, pasien) — bukan kolom duplikat (R4).
- **FR-011**: Sistem WAJIB menampilkan tanggal terbit invoice (`issued_at`) yang terisi saat transaksi diterbitkan (F0 merge — `issued_at` pada transaksi).
- **FR-012**: Sistem WAJIB menyediakan halaman cetak invoice yang dapat dicetak via browser, menampilkan identitas klinik, pasien, nomor invoice, tanggal terbit, daftar item, total, dan pembayaran.
- **FR-013**: Setiap halaman dalam WAJIB memiliki breadcrumb yang menempatkan halaman dalam hierarki root→aktif, dengan item terakhir non-tautan dan item induk dapat diklik ke rute parent.
- **FR-014**: Setiap aksi data-changing (mencatat pembayaran, menerbitkan invoice, menolak item invalid) WAJIB mencatat activity log naratif yang menyebut siapa, aksi, target, dan perubahan status (FR-084).

### Key Entities *(include if feature involves data)*

- **TransactionItem**: Line item penjualan. Rujukan tepat satu — produk XOR layanan (exclusive arc). Snapshot nama + harga satuan (R6). Qty, subtotal. Milik satu tenant (invariant = tenant transaksi). FK produk/layanan restrict on delete; FK transaksi cascade (child admin). Ditegakkan exclusive arc di basis + aplikasi.
- **Payment**: Pembayaran transaksi (cicilan/split). Metode (tunai/transfer/QRIS/debit), jumlah, waktu diterima (`paid_at`). Banyak pembayaran per transaksi; `paid_amount` denormalized di transaksi dijaga sinkron. Milik satu tenant (invariant). FK transaksi cascade.
- **Invoice (merged)**: Penerbitan invoice. F0 keputusan: MERGE — `issued_at` pada transaksi, tabel invoice dihapus (YAGNI, BCNF pure). Konten invoice dirender dari relasi transaksi (R4), bukan kolom duplikat. Satu per transaksi.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Upaya penyisipan item dengan kedua referensi terisi/kosong lewat jalur non-UI (direct basis insert) ditolak 100% — tidak ada item ambigu tersimpan.
- **SC-002**: Mengubah harga master produk/layanan setelah transaksi tersimpan tidak mengubah harga item historik 100% — snapshot immutable.
- **SC-003**: Status pembayaran akurat 100% setelah kombinasi pembayaran parsial: `unpaid`/`partially_paid`/`paid` konsisten dengan akumulasi `paid_amount` vs subtotal.
- **SC-004**: Sisa bayar ditampilkan akurat 100% pada setiap langkah cicilan — subtotal dikurangi total pembayaran tercatat.
- **SC-005**: Halaman cetak invoice menampilkan semua item dan pembayaran transaksi 100% akurat — konten selalu mencerminkan kondisi transaksi terbaru (render dari relasi, R4).
- **SC-006**: Child item/pembayaran tidak pernah lintas-tenant dari parent transaksi — invariant tenant terjaga 100%.
- **SC-007**: Kasir dapat menyelesaikan alur cicilan penuh (buka halaman → catat parsial → lihat sisa → lunasi → lihat riwayat) dalam waktu kurang dari 1 menit per pembayaran.
- **SC-008**: Kasir dapat membuka dan mencetak invoice transaksi dalam waktu kurang dari 10 detik dari halaman detail transaksi.

## Assumptions

- Spec 008 (Transaksi POS) sudah menyediakan backend inti: model transaksi, `paid_amount` denormalized, `payment_status` 3-state enum, `PayTransactionAction` sync, `InvoiceController` render dari transaksi (F0 merge MERGE), app validation exclusive arc, soft delete, FK restrict patient/booking/cashier. Spec 011 tidak menulis ulang ini, hanya melengkapi celah yang ditunda.
- F0 (keputusan merge `invoices`) sudah diputus = **MERGE** — `issued_at` pada `transactions`, tabel `invoices` dihapus. Spec ini mengikuti keputusan tersebut.
- Master produk (langkah 7), layanan (langkah 5), dan transaksi (langkah 9) sudah tersedia sebagai prasyarat dependency.
- `StockService` sudah ada untuk mutasi stok (`sold_pos`/`rollback`) — spec ini memakainya, tidak membuat baru.
- Sistem autentikasi, otorisasi peran klinik (kasir/admin/dokter/terapis), dan isolasi tenant (middleware `BelongsToTenant`) sudah berjalan dari spec 003/004.
- Audit log via `LogAuditAction` / `activity()` sudah tersedia dari infra L0 — spec ini memakainya, tidak membuat baru.
- Teks UI berbahasa Indonesia semi-formal friendly via sistem terjemahan; semua label user-facing melalui key i18n (identifier English).
- Pencetakan invoice menggunakan mekanisme cetak browser (native print), bukan library PDF eksternal — YAGNI.
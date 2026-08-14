# Feature Specification: Master Produk Klinik

**Feature Branch**: `007-product-master`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "Master produk. stock_balance default 0, min_threshold (FR-065), is_low_stock computed. Arsip via status=archived (FR-066). Revisi: FK stock_movements.product_id/transaction_items.product_id → restrictOnDelete. stock_balance hanya diubah via StockService::adjust() (R7) — verifikasi tidak ada path update langsung. AC: CRUD + arsip + low-stock indicator; hard-delete produk direferensi → diblokir restrict; activity log. FE: halaman master produk + breadcrumb. Data model sumber kebenaran: docs/normalization/README.md + docs/erd/."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Kelola Daftar Produk (Priority: P1)

Admin klinik membuat, melihat, dan memperbarui master produk inventory kliniknya. Setiap produk punya nama, satuan (pcs/botol/ml), harga jual, dan ambang stok menipis (`min_threshold`). Saat membuat produk baru, saldo stok (`stock_balance`) diawali 0 — admin menambah stok kemudian via mutasi masuk, bukan dengan mengisi saldo langsung. Admin dapat mencari dan mengurutkan daftar produk untuk menemukan item dengan cepat.

**Why this priority**: Tanpa master produk, klinik tidak bisa menjual produk di kasir (POS) maupun mengelola stok — ini fondasi untuk transaksi POS dan modul inventory. MVP tidak berfungsi tanpa ini.

**Independent Test**: Bisa diuji dengan membuat satu produk baru, melihatnya muncul di daftar dengan saldo 0, lalu mengubah harga/ambang stoknya — semuanya tanpa melibatkan modul transaksi/stok.

**Acceptance Scenarios**:

1. **Given** admin klinik sudah login, **When** admin mengisi form produk baru dengan nama "Serum Vitamin C", satuan "botol", harga 150000, dan ambang stok 5 lalu menyimpan, **Then** produk tersimpan dengan status aktif, saldo stok 0, dan muncul di daftar produk.
2. **Given** produk "Serum Vitamin C" ada, **When** admin mengubah harga menjadi 175000 lalu menyimpan, **Then** perubahan tersimpan dan nilai harga baru tampil di daftar.
3. **Given** admin membuka daftar produk, **When** admin mencari "serum" dan mengurutkan berdasarkan stok, **Then** hanya produk yang cocok tampil, terurut sesuai pilihan.
4. **Given** admin mengisi form produk dengan harga -1000, **When** admin menyimpan, **Then** sistem menolak dan menampilkan pesan bahwa harga tidak boleh negatif.
5. **Given** produk baru dibuat, **When** admin memeriksa saldo stok produk, **Then** saldo adalah 0 — tidak ada kolom isian saldo stok di form produk; saldo hanya berubah via mutasi stok.

---

### User Story 2 - Indikator Stok Menipis (Priority: P2)

Admin klinik melihat indikator "stok menipis" pada produk yang saldonya telah jatuh di atau di bawah ambang stok (`min_threshold`). Indikator ini terhitung otomatis (`is_low_stock = stock_balance <= min_threshold`) sehingga admin tahu produk mana yang perlu segera diisi ulang tanpa harus memeriksa satu per satu.

**Why this priority**: Peringatan stok menipis mencegah kehabisan produk yang dijual/ digunakan treatment. Penting, tapi baru bermakna setelah daftar produk dan mutasi stok dasar ada.

**Independent Test**: Bisa diuji dengan menyetel `min_threshold` sebuah produk ke nilai di atas saldo saat ini, lalu memverifikasi indikator "stok menipis" muncul pada produk tersebut di daftar.

**Acceptance Scenarios**:

1. **Given** produk "Serum Vitamin C" memiliki saldo stok 3 dan `min_threshold` 5, **When** admin melihat daftar produk, **Then** produk ditandai "stok menipis".
2. **Given** produk memiliki saldo stok 10 dan `min_threshold` 5, **When** admin melihat daftar produk, **Then** produk tidak ditandai "stok menipis".
3. **Given** produk memiliki saldo stok tepat sama dengan `min_threshold` (mis. 5 dan 5), **When** admin melihat daftar, **Then** produk tetap ditandai "stok menipis" (kondisi `<=`).

---

### User Story 3 - Arsip Produk (Priority: P3)

Admin klinik mengarsipkan produk yang tidak lagi dijual/digunakan, alih-alih menghapusnya. Produk terarsip tetap tersimpan beserta riwayat mutasi stoknya, namun tidak muncul saat menambah item produk baru di transaksi POS. Bila produk masih direferensi oleh mutasi stok atau transaction item, penghapusan permanen diblokir.

**Why this priority**: Arsip menjaga integritas data historis (R6) — transaksi lama tetap merujuk produk yang benar dengan snapshot nama dan harga. Penting tapi baru relevan setelah daftar produk dasar ada.

**Independent Test**: Bisa diuji dengan mengarsipkan satu produk aktif, lalu memverifikasi produk tidak muncul di pilihan produk saat transaksi POS baru namun tetap terlihat di daftar (dengan penanda arsip).

**Acceptance Scenarios**:

1. **Given** produk "Serum Vitamin C" berstatus aktif, **When** admin mengarsipkannya, **Then** status berubah menjadi arsip, aktivitas tercatat sebagai "Mengarsipkan produk Serum Vitamin C", dan produk tidak lagi muncul di pilihan produk saat menambah item transaksi POS baru.
2. **Given** produk "Serum Vitamin C" memiliki mutasi stok / transaction item yang masih merujuknya, **When** admin mencoba menghapus permanen produk tersebut, **Then** sistem memblokir penghapusan dengan pesan bahwa produk masih direferensi.
3. **Given** produk terarsip "Serum Vitamin C", **When** admin melihat daftar produk termasuk arsip, **Then** produk tampil dengan penanda arsip, riwayat mutasi stoknya tetap utuh, dan data transaksi lama yang merujuknya tetap menampilkan nama serta harga lama (snapshot).

---

### User Story 4 - Breadcrumb Navigasi Master Produk (Priority: P4)

Admin klinik menavigasi halaman master produk dengan breadcrumb yang menunjukkan jalur dari beranda klinik ke halaman produk, sehingga admin tahu posisi halaman saat ini dan bisa kembali ke induk.

**Why this priority**: Breadcrumb adalah konvensi konsistensi UI seluruh halaman dalam; nilai tinggi untuk orientasi tapi bukan blocker fungsional inti.

**Independent Test**: Bisa diuji dengan membuka halaman produk dan memverifikasi breadcrumb menampilkan jalur induk yang dapat diklik kembali ke beranda klinik.

**Acceptance Scenarios**:

1. **Given** admin berada di halaman daftar produk, **When** admin melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Produk" dengan "Produk" sebagai item terakhir (bukan tautan) dan "Beranda Klinik" sebagai tautan ke rute induk.

---

### Edge Cases

- Apa yang terjadi bila admin mencoba membuat produk dengan nama yang sama (duplikat) dalam satu klinik? Default: diizinkan (tidak ada unique constraint pada name per tenant) — admin bertanggung jawab atas penamaan.
- Bagaimana sistem menangani input harga/saldo/ambang dengan format non-numerik atau nilai negatif? Ditolak dengan pesan validasi.
- Apa yang terjadi bila admin mencoba mengisi `stock_balance` langsung dari form produk? Ditolak — `stock_balance` bukan field input; hanya berubah via mutasi stok (`StockService::adjust()`, R7).
- Apa yang terjadi bila admin mengarsipkan produk yang sedang memiliki saldo stok > 0? Default: diizinkan — saldo tetap dijaga selama produk masih arsip (riwayat mutasi utuh); arsip hanya menyembunyikan dari pilihan baru.
- Bagaimana menampilkan produk terarsip di daftar? Default: tampil dengan badge "Arsip" dan dapat difilter berdasarkan status.
- Apa yang terjadi bila semua produk sebuah klinik terarsip saat admin menambah item produk di transaksi POS? Pilihan produk kosong dengan pesan "Belum ada produk aktif".
- Bagaimana bila `min_threshold` diatur 0? Produk tidak pernah dianggap "stok menipis" kecuali saldonya juga 0 (kondisi `<=`).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-059**: Sistem WAJIB menyimpan produk dengan atribut: nama (wajib), satuan (wajib, mis. pcs/botol/ml), harga jual (wajib, >= 0), ambang stok menipis `min_threshold` (wajib, >= 0, default 0), dan status. Validasi menolak nama/satuan kosong dan nilai negatif.
- **FR-060**: Sistem WAJIB mengawali `stock_balance` produk baru dengan nilai 0. Saldo stok tidak diisi dari form produk; hanya berubah melalui mutasi stok.
- **FR-061**: Sistem WAJIB menambah saldo stok produk melalui mutasi masuk (`in`) yang dicatat sebagai `stock_movement` dengan saldo setelah mutasi (`balance_after`), dalam satu transaksi DB.
- **FR-062**: Sistem WAJIB mengurangi saldo stok produk melalui mutasi keluar manual (`out_manual`) yang dicatat sebagai `stock_movement` dengan keterangan/alasan, dalam satu transaksi DB.
- **FR-063**: Sistem WAJIB menjaga `stock_balance` sebagai satu sumber saldo — perubahan saldo WAJIB hanya melalui `StockService::adjust()`. Tidak boleh ada jalur pembaruan langsung (update field `stock_balance` di luar service) yang mengubah saldo.
- **FR-064**: Sistem WAJIB menyediakan riwayat mutasi stok per produk (stok masuk & keluar), terurut kronologis, dengan tipe mutasi, kuantitas, saldo setelah mutasi, dan keterangan.
- **FR-065**: Sistem WAJIB menghitung indikator `is_low_stock` secara otomatis: `is_low_stock = stock_balance <= min_threshold`. Indikator ditampilkan di daftar produk.
- **FR-066**: Sistem WAJIB menyediakan pengarsipan produk via perubahan status menjadi `archived`, bukan penghapusan. Produk terarsip tetap tersimpan beserta riwayat mutasi untuk referensi historis.
- **FR-067**: Sistem WAJIB menyembunyikan produk berstatus arsip dari pilihan produk saat menambah item produk pada transaksi POS baru.
- **FR-068**: Sistem WAJIB memblokir penghapusan permanen produk yang masih direferensi oleh `stock_movements` atau `transaction_items` (restrict on delete pada FK `product_id`).
- **FR-069**: Sistem WAJIB menjaga integritas snapshot — nama (`name`) dan harga (`unit_price`) produk yang tersimpan pada transaction item lama tetap utuh walau master produk diubah atau diarsipkan. Tidak boleh ada jalur sinkronisasi yang menimpa snapshot dari master.
- **FR-073**: Sistem WAJIB mencatat activity log naratif setiap aksi ubah-data pada produk, termasuk "Mengarsipkan produk {name}" dengan siapa (causer), aksi, target, dan kapan.
- **FR-074**: Sistem WAJIB memfilter daftar produk per-tenant secara otomatis; satu klinik tidak dapat melihat atau mengelola produk klinik lain.
- **FR-075**: Sistem WAJIB mendukung pencarian, pengurutan, dan paginasi server-side pada daftar produk.
- **FR-076**: Halaman master produk WAJIB menampilkan breadcrumb yang menunjukkan jalur induk→halaman aktif, sesuai konvensi breadcrumb seluruh halaman dalam.

### Key Entities *(include if feature involves data)*

- **Product**: Master produk inventory klinik. Atribut kunci: nama, satuan, harga jual, `stock_balance` (saldo, default 0), `min_threshold` (ambang stok menipis), status (aktif/arsip). Milik satu tenant. `stock_balance` adalah kolom denormalized (R7) yang hanya diubah via `StockService::adjust()`; `is_low_stock` computed. Direferensi oleh `stock_movements` dan `transaction_items`.
- **StockMovement**: Catatan mutasi stok produk (masuk/keluar/terjual/batal). Immutable (hanya `created_at`), mencatat `balance_after` untuk audit. Menjadi satu-satunya jalur perubahan `stock_balance`.
- **TransactionItem**: Menyimpan snapshot nama (`name`) dan harga (`unit_price`) produk saat transaksi dibuat — immutable, tidak tersinkron dari master.
- **Activity Log**: Mencatat aksi ubah-data produk secara naratif (siapa, aksi, target, kapan), termasuk arsip produk.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin dapat membuat, melihat, memperbarui, dan mengarsipkan produk dalam waktu kurang dari 30 detik per aksi.
- **SC-002**: Daftar produk dapat diurutkan dan dicari dengan hasil tampil dalam 1 detik untuk satu klinik dengan hingga 500 produk.
- **SC-003**: 100% aksi ubah-data produk (buat, ubah, arsip) tercatat dalam activity log dengan deskripsi naratif yang dapat dibaca manusia.
- **SC-004**: Indikator "stok menipis" muncul secara akurat pada 100% produk yang `stock_balance <= min_threshold`.
- **SC-005**: Produk terarsip tidak pernah muncul di pilihan produk saat transaksi POS baru (0 kemunculan).
- **SC-006**: Tidak ada penghapusan permanen produk yang masih direferensi berhasil (0 keberhasilan, 100% diblokir restrict).
- **SC-007**: Tidak ada jalur pembaruan langsung `stock_balance` di luar `StockService::adjust()` — 100% perubahan saldo melalui mutasi stok tercatat.
- **SC-008**: Snapshot nama dan harga pada transaction item lama tetap utuh 100% setelah master produk diubah atau diarsipkan.

## Assumptions

- Akses ke halaman master produk terbatas pada peran klinik dengan izin kelola produk/inventori (admin/therapist/cashier sesuai matriks izin klinik); otorisasi mengikuti sistem izin yang sudah ada.
- Otorisasi dan activity log menggunakan paket yang sudah terpasang di project (spatie/laravel-permission untuk peran dinamis, spatie/laravel-activitylog untuk audit log).
- Endpoint API mengikuti pola tenant-scoped yang sudah ada (`/{tenant}/clinic/products`) dengan middleware resolve tenant aktif.
- Mutasi stok (masuk/keluar manual) dilakukan via `StockService::adjust()` yang sudah ada; spec ini tidak membangun ulang service tersebut, hanya memastikan tidak ada path update `stock_balance` di luarnya.
- Tidak ada unique constraint pada nama produk per tenant — duplikat nama diizinkan, admin bertanggung jawab atas penamaan.
- Membangun ulang pilihan produk untuk transaksi POS baru hanya menyertakan produk berstatus aktif.
- Frontend mengikuti pola halaman master yang sudah ada (mis. layanan/pasien) untuk konsistensi struktur, komponen UI, dan breadcrumb.
- `stock_balance` adalah denormalized kolom (R7); reconcile job opsional ditunda hingga drift terdeteksi (`ponytail:`).
# Feature Specification: Integritas Mutasi Stok & Riwayat Stok Produk

**Feature Branch**: `012-stock-movements`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "stock_movements (ammar → zahiira) — setelah #7, #9. Semua mutasi via StockService::adjust() dalam DB transaction + row lock (R7). balance_after audit. Immutable (hanya created_at). Index (tenant_id,product_id,created_at). Revisi: ganti related_type/related_id manual → nullableMorphs('related') (kolom + composite index (related_type,related_id)). StockService::adjust create pakai morph map konsisten. FK product_id → restrictOnDelete. Anomali #3 — tenant-id invariant: tenant_id inherit dari product (sudah via StockService). AC: stock in/out → balance_after konsisten + stock_balance update; rollback saat cancel transaksi → stok kembali; reverse lookup per transaksi pakai morph index; activity log 'Menyesuaikan stok {product} — {type} {qty}'. FE: riwayat stok per produk + breadcrumb. Sumber: docs/erd/ + docs/normalization/README.md + docs/normalization/workflow.md."

## Konteks

Spec ini **melengkapi spec 007 (Master Produk) dan spec 008 (Transaksi POS)**, bukan duplikat. Spec 007 sudah mencakup master produk (`stock_balance` default 0, `min_threshold`, `is_low_stock` computed, arsip via `status=archived`, FK restrictOnDelete). Spec 008 sudah memakai `StockService` untuk mutasi `sold_pos` (FR-052) dan `rollback` (FR-058) sebagai bagian alur transaksi. Spec 012 menggarap **tiga celah** yang 007/008 eksplisit tunda ke langkah 14 workflow normalisasi:

1. **Skema audit mutasi stok** — tabel `stock_movements` dengan `balance_after` audit, immutable (hanya `created_at`), index riwayat per produk, dan relasi polimorfik `related` ke transaksi (pengganti kolom `related_type`/`related_id` manual). 007 hanya menjaga `stock_balance` kolom denormalized; spec ini menyediakan jejak mutasi yang dapat diaudit dan di-reverse-lookup per transaksi.
2. **Penegakan integritas tingkat basis** — FK `product_id` restrictOnDelete (blokir hapus produk yang masih punya riwayat mutasi), invariant tenant (`tenant_id` inherit dari produk via `StockService`, anomali #3), dan composite index morph untuk reverse lookup. 007/008 menyebut aturan ini secara konseptual; spec ini menutup celah data integrity.
3. **Pengalaman riwayat stok pengguna** — halaman riwayat stok per produk dengan daftar mutasi (masuk/keluar/penjualan/pengembalian), saldo setelah mutasi, dan breadcrumb. 007/008 tidak menyediakan visibilitas ini.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Setiap Mutasi Stok Meninggalkan Jejak Audit dengan Saldo Konsisten (Priority: P1)

Admin/kasir melakukan penyesuaian stok produk — baik stok masuk (restock), stok keluar manual (rusak/hilang/penyesuaian), penjualan POS, maupun pengembalian saat transaksi dibatalkan. Setiap mutasi meninggalkan jejak audit: jenis mutasi, jumlah, dan saldo setelah mutasi (`balance_after`). Saldo produk (`stock_balance`) selalu konsisten dengan akumulasi jejak mutasi. Tidak ada mutasi yang mengubah saldo tanpa jejak. Jejak bersifat immutable — sekali dicatat tidak dapat diubah/dihapus.

**Why this priority**: Jejak mutasi adalah sumber kebenaran audit stok (R7). Tanpa jejak, `stock_balance` denormalized tidak dapat diverifikasi, dan anomali saldo tidak dapat ditelusuri. Penjualan POS dan pengembalian sudah memakai `StockService` sejak spec 008, tapi tanpa skema jejak yang utuh, reverse lookup per transaksi dan rekonsiliasi saat drift tidak mungkin. Integritas ini fondasi dari seluruh modul inventaris.

**Independent Test**: Bisa diuji dengan memanggil penyesuaian stok masuk/keluar beberapa kali pada satu produk, lalu memverifikasi setiap mutasi punya `balance_after` yang konsisten dengan saldo sebelumnya ± jumlah, dan `stock_balance` produk sama dengan saldo mutasi terakhir — tanpa melibatkan antarmuka pengguna atau modul transaksi.

**Acceptance Scenarios**:

1. **Given** produk "Serum Vitamin C" saldo awal 0, **When** admin mencatat stok masuk 10 unit, **Then** mutasi tersimpan dengan `type=in`, `quantity=10`, `balance_after=10`, dan `stock_balance` produk menjadi 10.
2. **Given** produk saldo 10, **When** admin mencatat stok keluar manual 3 unit (rusak), **Then** mutasi tersimpan dengan `type=out_manual`, `quantity=3`, `balance_after=7`, dan `stock_balance` menjadi 7.
3. **Given** produk saldo 7, **When** transaksi POS menjual 2 unit, **Then** mutasi tersimpan dengan `type=sold_pos`, `quantity=2`, `balance_after=5`, terhubung ke transaksi terkait (`related`), dan `stock_balance` menjadi 5.
4. **Given** produk saldo 5 setelah penjualan 2 unit, **When** transaksi tersebut dibatalkan, **Then** mutasi `type=rollback` tersimpan, `quantity=2`, `balance_after=7` (stok dikembalikan), terhubung ke transaksi yang dibatalkan, dan `stock_balance` kembali 7.
5. **Given** satu jejak mutasi sudah tersimpan, **When** ada upaya mengubah atau menghapus jejak tersebut, **Then** upaya ditolak — jejak immutable (tidak ada kolom `updated_at`, tidak ada path update/delete).

---

### User Story 2 - Penyesuaian Stok Aman dari Race Condition (Priority: P2)

Dua mutasi stok terjadi nyaris bersamaan pada produk yang sama (mis. dua kasir menjual produk yang sama, atau admin restock bersamaan dengan penjualan POS). Setiap mutasi dihitung dari saldo yang benar saat itu; tidak ada mutasi yang menimpa saldo mutasi lain. Saldo akhir konsisten dengan urutan mutasi yang sebenarnya terjadi. Mutasi diproses dalam transaksi basis data dengan row lock pada produk.

**Why this priority**: `stock_balance` denormalized adalah satu sumber saldo (R7). Tanpa row lock + transaksi atomik, mutasi konkuren bisa saling menimpa dan menghasilkan saldo salah — kehilangan stok atau kelebihan stok. Penting, tapi baru bermakna setelah jejak audit dasar (US1) utuh.

**Independent Test**: Bisa diuji dengan memicu dua penyesuaian stok konkuren pada produk yang sama dan memverifikasi `stock_balance` akhir = saldo awal + total semua mutasi, serta setiap mutasi punya `balance_after` yang konsisten dengan urutan terjadinya — tanpa melibatkan antarmuka pengguna.

**Acceptance Scenarios**:

1. **Given** produk saldo 10, **When** dua mutasi keluar masing-masing 3 unit terjadi bersamaan, **Then** `stock_balance` akhir = 4 (10 − 3 − 3), dan dua jejak mutasi masing-masing mencatat `balance_after` yang berurutan konsisten (7 lalu 4, atau sebaliknya sesuai urutan lock).
2. **Given** produk saldo 5, **When** admin restock 10 unit bersamaan dengan penjualan POS 2 unit, **Then** `stock_balance` akhir = 13 (5 + 10 − 2); tidak ada mutasi yang hilang atau menimpa.
3. **Given** dua mutasi konkuren terjadi, **When** transaksi pertama gagal setelah lock diambil, **Then** saldo tidak berubah akibat mutasi gagal; mutasi kedua tetap memproses dari saldo benar — tidak ada saldo parah yang tersimpan.

---

### User Story 3 - Riwayat Stok Per Produk dan Reverse Lookup Per Transaksi (Priority: P3)

Admin membuka halaman riwayat stok sebuah produk dan melihat daftar semua mutasi (masuk, keluar manual, penjualan POS, pengembalian) terurut kronologis, masing-masing dengan jenis, jumlah, saldo setelah mutasi, keterangan, dan transaksi terkait (bila ada). Selain itu, sistem dapat menelusuri mundur semua mutasi yang berhubungan dengan satu transaksi tertentu (penjualan + pengembalian) untuk keperluan audit/rekonsiliasi. Halaman menampilkan breadcrumb yang menempatkan riwayat dalam hierarki produk.

**Why this priority**: Visibilitas riwayat stok penting untuk audit dan rekonsiliasi inventaris. Reverse lookup per transaksi memungkinkan menelusuri pengaruh satu transaksi terhadap stok (penjualan lalu pembatalan). Penting, tapi baru bermakna setelah jejak audit (US1) dan keamanan konkuren (US2) utuh.

**Independent Test**: Bisa diuji dengan membuat beberapa mutasi pada satu produk (termasuk yang terhubung transaksi), lalu memverifikasi daftar riwayat per produk terurut lengkap, dan reverse lookup per transaksi mengembalikan tepat mutasi yang berhubungan dengan transaksi tersebut — tanpa melibatkan pembuatan transaksi baru.

**Acceptance Scenarios**:

1. **Given** produk punya lima mutasi (masuk, keluar manual, dua penjualan POS, satu pengembalian), **When** admin membuka riwayat stok produk, **Then** kelima mutasi tampil terurut kronologis dengan jenis, jumlah, `balance_after`, keterangan, dan transaksi terkait (bila ada).
2. **Given** transaksi POS tertentu telah menjual 2 unit lalu dibatalkan (mengembalikan 2 unit), **When** sistem melakukan reverse lookup mutasi untuk transaksi tersebut, **Then** tepat dua mutasi ditemukan (satu `sold_pos`, satu `rollback`) yang merujuk transaksi itu, via index morph `related`.
3. **Given** admin di halaman riwayat stok produk, **When** admin melihat breadcrumb, **Then** terlihat "Beranda Klinik > Produk > {Nama Produk} > Riwayat Stok" dengan item terakhir non-tautan dan item induk dapat diklik kembali.
4. **Given** produk tidak punya mutasi sama sekali, **When** admin membuka riwayat stok produk, **Then** tampil state kosok yang manusiawi ("Belum ada mutasi stok") — bukan error atau tabel kosong tanpa konteks.
5. **Given** mutasi dengan `type=sold_pos` terhubung transaksi, **When** admin melihat baris mutasi tersebut di riwayat, **Then** transaksi terkait dapat dilihat/merujuk ke detail transaksi (tautan atau identitas), sehingga jejak stok dapat ditelusuri ke transaksi sumbernya.

---

### Edge Cases

- Apa yang terjadi bila penyesuaian stok keluar melebihi saldo saat ini? Saldo tidak boleh negatif — mutasi ditolak sebelum menyentuh basis (saldo hasil < 0), atau bila diizinkan secara eksplisit (penyesuaian negatif terkontrol), `balance_after` tetap dicatat apa adanya. Kebijakan default MVP: tolak mutasi yang menghasilkan saldo negatif.
- Bagaimana bila produk dengan riwayat mutasi dihapus permanen? Diblokir karena FK `product_id` restrictOnDelete — arsip (`status=archived`) tetap diizinkan dan riwayat mutasi tetap utuh.
- Apa yang terjadi bila mutasi dibuat lintas tenant dari produk-nya? Tidak mungkin — `tenant_id` mutasi selalu diwarisi dari produk via `StockService` (anomali #3 invariant); tidak ada mutasi lintas-tenant.
- Bagaimana bila transaksi dibatalkan dua kali? Pembatalan transaksi hanya menghasilkan satu mutasi `rollback`; mutasi rollback idempoten terhadap transaksi (pembatalan berulang tidak membuat duplikat mutasi).
- Apa yang terjadi bila `balance_after` jejak divergen dari `stock_balance` produk (drift)? `stock_balance` adalah sumber saldo operasional; rekonsiliasi otomatis tertunda (`ponytail`, add saat drift terdeteksi) — MVP andalkan konsistensi yang dijaga `StockService` dalam transaksi.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem WAJIB mencatat setiap mutasi stok produk sebagai jejak audit dengan jenis (`in`/`out_manual`/`sold_pos`/`rollback`), jumlah, dan saldo setelah mutasi (`balance_after`) (R7).
- **FR-002**: Sistem WAJIB memproses setiap mutasi stok dalam transaksi basis data dengan row lock pada produk, sehingga mutasi konkuren tidak saling menimpa saldo (R7).
- **FR-003**: Sistem WAJIB memperbarui `stock_balance` produk secara konsisten dengan `balance_after` jejak mutasi terakhir, dalam transaksi yang sama dengan pencatatan jejak.
- **FR-004**: Sistem WAJIB menjaga jejak mutasi immutable — sekali dicatat tidak dapat diubah atau dihapus; tabel hanya memiliki `created_at`, tidak ada `updated_at` atau path update/delete.
- **FR-005**: Sistem WAJIB menghubungkan mutasi `sold_pos` dan `rollback` ke transaksi terkait melalui relasi polimorfik `related` (mengganti kolom manual `related_type`/`related_id`), dengan morph map konsisten.
- **FR-006**: Sistem WAJIB menyediakan composite index pada `(related_type, related_id)` untuk reverse lookup mutasi per transaksi secara efisien.
- **FR-007**: Sistem WAJIB menyediakan index `(tenant_id, product_id, created_at)` untuk query riwayat stok per produk (FR-064).
- **FR-008**: Sistem WAJIB menolak penghapusan permanen produk yang masih memiliki riwayat mutasi (FK `product_id` restrictOnDelete); pengarsipan (`status=archived`) tetap diizinkan tanpa memutus riwayat.
- **FR-009**: Sistem WAJIB menjaga invariant tenant pada mutasi stok — `tenant_id` mutasi selalu sama dengan `tenant_id` produk (anomali #3 normalisasi); tidak ada mutasi lintas-tenant.
- **FR-010**: Sistem WAJIB mengembalikan stok produk saat transaksi dibatalkan melalui mutasi `rollback` yang terhubung ke transaksi (FR-058); pembatalan berulang tidak membuat duplikat mutasi.
- **FR-011**: Sistem WAJIB menyediakan halaman riwayat stok per produk yang menampilkan daftar mutasi terurut kronologis dengan jenis, jumlah, `balance_after`, keterangan, dan transaksi terkait (FR-064).
- **FR-012**: Sistem WAJIB menyediakan kemampuan reverse lookup semua mutasi yang berhubungan dengan satu transaksi via index morph `related`.
- **FR-013**: Setiap halaman dalam WAJIB memiliki breadcrumb yang menempatkan halaman dalam hierarki root→aktif, dengan item terakhir non-tautan dan item induk dapat diklik ke rute parent.
- **FR-014**: Setiap aksi data-changing (mencatat stok masuk/keluar, penjualan POS, pengembalian pembatalan) WAJIB mencatat activity log naratif "Menyesuaikan stok {product} — {type} {qty}" yang menyebut siapa (causer), aksi, target, kapan, dan properti mutasi (FR-084).
- **FR-015**: Sistem WAJIB menolak mutasi stok keluar yang menghasilkan saldo negatif kecuali secara eksplisit diizinkan; kebijakan default MVP menolak saldo negatif untuk menjaga integritas inventaris.

### Key Entities *(include if feature involves data)*

- **StockMovement**: Jejak audit mutasi stok produk. Jenis (`in`/`out_manual`/`sold_pos`/`rollback`), jumlah (positif; arah saldo ditentukan jenis), `balance_after` (saldo setelah mutasi), keterangan, relasi polimorfik `related` (Transaction untuk `sold_pos`/`rollback`). Immutable (hanya `created_at`). Index `(tenant_id, product_id, created_at)` untuk riwayat per produk, `(related_type, related_id)` untuk reverse lookup per transaksi. Milik satu tenant (invariant = tenant produk). FK produk restrict on delete.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Setiap mutasi stok menghasilkan jejak dengan `balance_after` konsisten dengan saldo sebelumnya ± jumlah, 100% kasus — tidak ada jejak dengan saldo salah.
- **SC-002**: `stock_balance` produk selalu sama dengan `balance_after` jejak mutasi terakhir, 100% kasus — tidak ada drift antara saldo kolom dan jejak.
- **SC-003**: Mutasi stok konkuren pada produk yang sama menghasilkan saldo akhir yang benar (saldo awal + total semua mutasi), 100% kasus — tidak ada mutasi hilang atau tertimpa.
- **SC-004**: Jejak mutasi tidak dapat diubah atau dihapus setelah dicatat, 100% upaya — immutability terjaga.
- **SC-005**: Reverse lookup mutasi per transaksi mengembalikan tepat mutasi yang berhubungan dengan transaksi tersebut, 100% akurat — tidak ada mutasi liar atau hilang.
- **SC-006**: Mutasi stok tidak pernah lintas-tenant dari produk-nya — invariant tenant terjaga 100%.
- **SC-007**: Penghapusan permanen produk dengan riwayat mutasi diblokir 100% — arsip tetap diizinkan dengan riwayat utuh.
- **SC-008**: Admin dapat membuka riwayat stok produk dan menemukan mutasi spesifik dalam waktu kurang dari 5 detik untuk produk dengan hingga 100 mutasi.

## Assumptions

- Spec 007 (Master Produk) sudah menyediakan model produk, `stock_balance` denormalized default 0, `min_threshold`, `is_low_stock` computed, arsip via `status=archived`, FK restrictOnDelete dari `stock_movements`. Spec 012 tidak menulis ulang ini, hanya melengkapi skema jejak mutasi.
- Spec 008 (Transaksi POS) sudah memakai `StockService::adjust()` untuk mutasi `sold_pos` (FR-052) dan `rollback` (FR-058) sebagai bagian alur transaksi. Spec 012 menyediakan skema `stock_movements` yang menjadi tujuan mutasi tersebut.
- Master produk (langkah 7) dan transaksi (langkah 9) sudah tersedia sebagai prasyarat dependency.
- `StockService` sudah ada dan mengelola `stock_balance` + transaksi + row lock; spec ini memastikan `StockService::adjust()` mencatat jejak dengan morph map konsisten, tidak membuat service baru.
- Sistem autentikasi, otorisasi peran klinik (admin/kasir/dokter/terapis), dan isolasi tenant (middleware `BelongsToTenant`, trait + global scope) sudah berjalan dari spec 003/004.
- Audit log via `LogAuditAction` / `activity()` sudah tersedia dari infra L0 — spec ini memakainya, tidak membuat baru.
- Teks UI berbahasa Indonesia semi-formal friendly via sistem terjemahan; semua label user-facing melalui key i18n (identifier English).
- Rekonsiliasi otomatis `stock_balance` vs jejak saat drift terdeteksi tertunda (`ponytail`, add saat butuh) — MVP andalkan konsistensi transaksi `StockService`.
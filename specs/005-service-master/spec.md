# Feature Specification: Master Layanan Klinik

**Feature Branch**: `005-service-master`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "Master layanan. price >= 0 (FR-011). Arsip via status=archived, bukan hapus (FR-013). Index (tenant_id, status). Revisi: FK bookings.service_id/treatment_records.service_id/transaction_items.service_id → restrictOnDelete. Snapshot name+unit_price/service_name tetap utuh walau arsip (R6) — verifikasi tidak ada path sync snapshot ke master. AC: CRUD + arsip; arsip tidak muncul di pilihan booking baru; hard-delete layanan direferensi → diblokir restrict; activity log 'Mengarsipkan layanan {name}'. FE: halaman master layanan + breadcrumb."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Kelola Daftar Layanan (Priority: P1)

Admin klinik membuat, melihat, dan memperbarui daftar layanan/treatment yang ditawarkan kliniknya. Setiap layanan punya nama, deskripsi, dan harga. Harga tidak boleh negatif. Admin dapat mencari dan mengurutkan daftar layanan untuk menemukan item dengan cepat.

**Why this priority**: Tanpa daftar layanan, klinik tidak bisa menawarkan treatment — ini fondasi untuk booking, transaksi POS, dan rekam medis. MVP tidak berfungsi tanpa ini.

**Independent Test**: Bisa diuji dengan membuat satu layanan baru, melihatnya muncul di daftar, lalu mengubah harganya — semuanya tanpa melibatkan modul booking/transaksi.

**Acceptance Scenarios**:

1. **Given** admin klinik sudah login, **When** admin mengisi form layanan baru dengan nama "Facial Glow" dan harga 350000 lalu menyimpan, **Then** layanan tersimpan dengan status aktif dan muncul di daftar layanan.
2. **Given** layanan "Facial Glow" ada, **When** admin mengubah harga menjadi 400000 lalu menyimpan, **Then** perubahan tersimpan dan nilai harga baru tampil di daftar.
3. **Given** admin membuka daftar layanan, **When** admin mencari "facial" dan mengurutkan berdasarkan harga, **Then** hanya layanan yang cocok tampil, terurut sesuai pilihan.
4. **Given** admin mengisi form layanan dengan harga -1000, **When** admin menyimpan, **Then** sistem menolak dan menampilkan pesan bahwa harga tidak boleh negatif.

---

### User Story 2 - Arsip Layanan (Priority: P2)

Admin klinik mengarsipkan layanan yang tidak lagi ditawarkan, alih-alih menghapusnya. Layanan terarsip tetap tersimpan untuk riwayat, namun tidak muncul saat membuat booking baru. Bila layanan masih direferensi oleh booking/transaksi/treatment, penghapusan permanen diblokir.

**Why this priority**: Arsip menjaga integritas data historis (R6) — booking dan transaksi lama tetap merujuk layanan yang benar. Penting tapi baru relevan setelah daftar layanan dasar ada.

**Independent Test**: Bisa diuji dengan mengarsipkan satu layanan aktif, lalu memverifikasi layanan tidak muncul di pilihan booking baru namun tetap terlihat di daftar (dengan penanda arsip).

**Acceptance Scenarios**:

1. **Given** layanan "Facial Glow" berstatus aktif, **When** admin mengarsipkannya, **Then** status berubah menjadi arsip, aktivitas tercatat sebagai "Mengarsipkan layanan Facial Glow", dan layanan tidak lagi muncul di pilihan layanan saat membuat booking baru.
2. **Given** layanan "Facial Glow" memiliki booking yang masih merujuknya, **When** admin mencoba menghapus permanen layanan tersebut, **Then** sistem memblokir penghapusan dengan pesan bahwa layanan masih direferensi.
3. **Given** layanan terarsip "Facial Glow", **When** admin melihat daftar layanan termasuk arsip, **Then** layanan tampil dengan penanda arsip dan data transaksi/treatment lama yang merujuknya tetap menampilkan nama serta harga lama (snapshot).

---

### User Story 3 - Breadcrumb Navigasi Master Layanan (Priority: P3)

Admin klinik menavigasi halaman master layanan dengan breadcrumb yang menunjukkan jalur dari beranda klinik ke halaman layanan, sehingga admin tahu posisi halaman saat ini dan bisa kembali ke induk.

**Why this priority**: Breadcrumb adalah konvensi konsistensi UI seluruh halaman dalam; nilai tinggi untuk orientasi tapi bukan blocker fungsional inti.

**Independent Test**: Bisa diuji dengan membuka halaman layanan dan memverifikasi breadcrumb menampilkan jalur induk yang dapat diklik kembali ke beranda klinik.

**Acceptance Scenarios**:

1. **Given** admin berada di halaman daftar layanan, **When** admin melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Layanan" dengan "Layanan" sebagai item terakhir (bukan tautan) dan "Beranda Klinik" sebagai tautan ke rute induk.

---

### Edge Cases

- Apa yang terjadi bila admin mencoba membuat layanan dengan nama yang sama (duplikat) dalam satu klinik? Default: diizinkan (tidak ada unique constraint pada name per tenant) — admin bertanggung jawab atas penamaan.
- Bagaimana sistem menangani input harga dengan format non-numerik atau lebih dari 2 desimal? Ditolak dengan pesan validasi.
- Apa yang terjadi bila admin mengarsipkan layanan yang sedang digunakan oleh booking aktif (status belum selesai)? Default: diizinkan — booking yang sudah ada tetap merujuk layanan; hanya pilihan layanan baru yang disembunyikan.
- Bagaimana menampilkan layanan terarsip di daftar? Default: tampil dengan badge "Arsip" dan dapat difilter berdasarkan status.
- Apa yang terjadi bila semua layanan sebuah klinik terarsip saat admin membuat booking baru? Pilihan layanan kosong dengan pesan "Belum ada layanan aktif".

## Requirements *(mandatory)*

### Functional Requirements

- **FR-011**: Sistem WAJIB menyimpan layanan dengan harga tidak negatif (>= 0). Harga dengan nilai negatif ditolak.
- **FR-012**: Perubahan harga layanan WAJIB berlaku untuk booking dan transaksi baru; booking/transaksi lama tetap memakai harga saat transaksi dibuat (snapshot).
- **FR-013**: Sistem WAJIB menyediakan pengarsipan layanan via perubahan status menjadi `archived`, bukan penghapusan. Layanan terarsip tetap tersimpan untuk referensi historis.
- **FR-014**: Sistem WAJIB menyembunyikan layanan berstatus arsip dari pilihan layanan saat membuat booking baru.
- **FR-015**: Sistem WAJIB memblokir penghapusan permanen layanan yang masih direferensi oleh booking, treatment record, atau transaction item (restrict on delete).
- **FR-016**: Sistem WAJIB menjaga integritas snapshot — nama dan harga layanan yang tersimpan pada booking/transaksi/treatment lama tetap utuh walau master layanan diubah atau diarsipkan. Tidak boleh ada jalur sinkronisasi yang menimpa snapshot dari master.
- **FR-017**: Sistem WAJIB mencatat activity log naratif setiap aksi ubah-data pada layanan, termasuk "Mengarsipkan layanan {name}" dengan siapa (causer), aksi, target, dan kapan.
- **FR-018**: Sistem WAJIB memfilter daftar layanan per-tenant secara otomatis; satu klinik tidak dapat melihat atau mengelola layanan klinik lain.
- **FR-019**: Sistem WAJIB mendukung pencarian, pengurutan, dan paginasi server-side pada daftar layanan.
- **FR-020**: Halaman master layanan WAJIB menampilkan breadcrumb yang menunjukkan jalur induk→halaman aktif, sesuai konvensi breadcrumb seluruh halaman dalam.

### Key Entities *(include if feature involves data)*

- **Service**: Master layanan/treatment klinik. Atribut kunci: nama, deskripsi, harga, status (aktif/arsip). Milik satu tenant. Direferensi oleh booking (layanan utama), treatment record, dan transaction item.
- **Booking**: Merujuk satu layanan sebagai layanan utama. Bila layanan diarsip, booking yang sudah ada tetap valid; pilihan layanan baru disembunyikan.
- **TransactionItem**: Menyimpan snapshot nama (`name`) dan harga (`unit_price`) layanan saat transaksi dibuat — immutable, tidak tersinkron dari master.
- **TreatmentRecord**: Menyimpan snapshot nama layanan (`service_name`) — immutable, tidak tersinkron dari master.
- **Activity Log**: Mencatat aksi ubah-data layanan secara naratif (siapa, aksi, target, kapan).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin dapat membuat, melihat, memperbarui, dan mengarsipkan layanan dalam waktu kurang dari 30 detik per aksi.
- **SC-002**: Daftar layanan dapat diurutkan dan dicari dengan hasil tampil dalam 1 detik untuk satu klinik dengan hingga 200 layanan.
- **SC-003**: 100% aksi ubah-data layanan (buat, ubah, arsip) tercatat dalam activity log dengan deskripsi naratif yang dapat dibaca manusia.
- **SC-004**: Layanan terarsip tidak pernah muncul di pilihan layanan saat membuat booking baru (0 kemunculan).
- **SC-005**: Tidak ada penghapusan permanen layanan yang masih direferensi berhasil (0 keberhasilan, 100% diblokir).
- **SC-006**: Snapshot nama dan harga pada transaksi/treatment lama tetap utuh 100% setelah master layanan diubah atau diarsipkan.

## Assumptions

- Akses ke halaman master layanan terbatas pada peran klinik dengan izin kelola layanan (admin/dokter sesuai matriks izin klinik); otorisasi mengikuti sistem izin yang sudah ada.
- Otorisasi dan activity log menggunakan paket yang sudah terpasang di project (spatie/laravel-permission untuk peran dinamis, spatie/laravel-activitylog untuk audit log).
- Endpoint API mengikuti pola tenant-scoped yang sudah ada (`/{tenant}/clinic/services`) dengan middleware resolve tenant aktif.
- Tidak ada unique constraint pada nama layanan per tenant — duplikat nama diizinkan, admin bertanggung jawab atas penamaan.
- Membangun ulang pilihan layanan untuk booking baru hanya menyertakan layanan berstatus aktif.
- Frontend mengikuti pola halaman master yang sudah ada (mis. produk/inventori) untuk konsistensi struktur, komponen UI, dan breadcrumb.
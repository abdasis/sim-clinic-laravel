# Feature Specification: Master Pasien Klinik

**Feature Branch**: `006-patient-master`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "patients (ammar → zahiira). phone tidak unique (peringatan duplikat FR-023). Index (tenant_id, phone). Revisi: soft delete (deleted_at); index (tenant_id, deleted_at). Tambah aksi nonaktifkan (soft delete) — route saat ini except(['destroy']), mungkin perlu destroy soft-delete. FK dari bookings/medical_records/transactions → restrictOnDelete. AC: CRUD + duplikat phone warning; soft-delete pasien → riwayat tetap utuh + tidak muncul di list aktif; hard-delete diblokir restrict; activity log 'Menonaktifkan pasien {name}'. FE: halaman master pasien + riwayat + breadcrumb. Data model sumber kebenaran: docs/normalization/README.md + docs/erd/."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Kelola Data Pasien (Priority: P1)

Admin klinik (atau peran dengan izin kelola pasien) membuat, melihat, dan memperbarui data pasien kliniknya. Setiap pasien punya nama, tanggal lahir, jenis kelamin, nomor telepon, WhatsApp, alamat, dan catatan. Nomor telepon tidak harus unik — bila admin memasukkan nomor yang sudah dipakai pasien lain di klinik yang sama, sistem tetap menyimpan namun memberi peringatan duplikat agar admin sadar dan bisa meninjau ulang. Admin dapat mencari dan mengurutkan daftar pasien untuk menemukan rekam dengan cepat.

**Why this priority**: Tanpa data pasien, klinik tidak bisa membuat booking, transaksi POS, maupun rekam medis — pasien adalah fondasi hampir seluruh modul klinik. MVP tidak berfungsi tanpa ini.

**Independent Test**: Bisa diuji dengan membuat satu pasien baru, melihatnya muncul di daftar, lalu memperbarui nomor teleponnya — semuanya tanpa melibatkan modul booking/transaksi/rekam medis.

**Acceptance Scenarios**:

1. **Given** admin klinik sudah login, **When** admin mengisi form pasien baru dengan nama "Siti Aminah" dan telepon "08123456789" lalu menyimpan, **Then** pasien tersimpan, muncul di daftar pasien aktif, dan tersimpan.
2. **Given** pasien "Siti Aminah" ada, **When** admin mengubah alamatnya lalu menyimpan, **Then** perubahan tersimpan dan nilai alamat baru tampil di detail pasien.
3. **Given** admin membuka daftar pasien, **When** admin mencari "siti" dan mengurutkan berdasarkan nama, **Then** hanya pasien yang cocok tampil, terurut sesuai pilihan.
4. **Given** sudah ada pasien dengan telepon "08123456789", **When** admin membuat pasien baru dengan telepon yang sama lalu menyimpan, **Then** pasien tetap tersimpan (tidak ditolak) namun sistem mengembalikan peringatan duplikat yang menunjuk pasien lama dengan nomor tersebut (FR-023).

---

### User Story 2 - Nonaktifkan Pasien & Riwayat Tetap Utuh (Priority: P2)

Admin klinik menonaktifkan pasien yang tidak lagi aktif (alih-alih menghapus permanen). Pasien nonaktif tetap tersimpan untuk riwayat — seluruh booking, rekam medis, dan transaksi yang merujuk pasien tersebut tetap utuh dan dapat dilihat. Pasien nonaktif tidak muncul di daftar pasien aktif. Bila pasien masih direferensi oleh booking/rekam medis/transaksi, penghapusan permanen diblokir.

**Why this priority**: Nonaktifkan menjaga integritas data historis — kunjungan dan rekam medis pasien lama tetap bisa ditelusuri (FR-022). Penting, tapi baru relevan setelah daftar pasien dasar ada.

**Independent Test**: Bisa diuji dengan menonaktifkan satu pasien aktif yang punya booking lalu memverifikasi: pasien tidak muncul di daftar aktif, namun halaman riwayatnya tetap menampilkan seluruh booking/rekam medis/transaksi lampau.

**Acceptance Scenarios**:

1. **Given** pasien "Siti Aminah" berstatus aktif, **When** admin menonaktifkannya, **Then** pasien ditandai nonaktif (soft delete), aktivitas tercatat sebagai "Menonaktifkan pasien Siti Aminah", dan pasien tidak lagi muncul di daftar pasien aktif.
2. **Given** pasien "Siti Aminah" sudah dinonaktifkan dan memiliki booking/rekam medis/transaksi, **When** admin membuka riwayat pasien tersebut, **Then** seluruh riwayat kunjungan, rekam medis, dan transaksi lampau tetap tampil lengkap (tidak hilang).
3. **Given** pasien "Siti Aminah" masih direferensi booking/transaksi/rekam medis, **When** admin mencoba menghapus permanen pasien tersebut, **Then** sistem memblokir penghapusan dengan pesan bahwa pasien masih direferensi (restrict on delete).

---

### User Story 3 - Riwayat Kunjungan Pasien (Priority: P2)

Admin/dokter melihat riwayat kunjungan seorang pasien dalam satu tampilan terurut, mencakup booking, rekam medis/treatment, dan transaksi yang terkait. Ini memungkinkan peninjauan riwayat klinis pasien tanpa berpindah antar modul.

**Why this priority**: Riwayat pasien (FR-022) adalah kebutuhan inti operasional klinik — mengetahui treatment lampau sebelum menangani pasien. Prioritas sama dengan nonaktifkan karena sama-sama bergantung pada data pasien dasar.

**Independent Test**: Bisa diuji dengan membuat satu pasien yang punya minimal satu booking, lalu memverifikasi halaman riwayat menampilkan booking tersebut terurut kronologis.

**Acceptance Scenarios**:

1. **Given** pasien "Siti Aminah" memiliki beberapa booking dan rekam medis, **When** admin membuka halaman riwayat pasien, **Then** seluruh booking dan treatment ditampilkan dalam satu daftar terurut kronologis, masing-masing menampilkan tanggal, layanan, status, dan nama petugas.
2. **Given** pasien "Siti Aminah" sudah dinonaktifkan, **When** admin membuka halaman riwayatnya, **Then** riwayat tetap dapat diakses dan lengkap — nonaktif tidak menghapus akses riwayat.

---

### User Story 4 - Breadcrumb Navigasi Master Pasien (Priority: P3)

Admin klinik menavigasi halaman master pasien dan riwayat pasien dengan breadcrumb yang menunjukkan jalur dari beranda klinik ke halaman aktif, sehingga admin tahu posisinya dan bisa kembali ke induk.

**Why this priority**: Breadcrumb adalah konvensi konsistensi UI seluruh halaman dalam; nilai tinggi untuk orientasi tapi bukan blocker fungsional inti.

**Independent Test**: Bisa diuji dengan membuka halaman daftar pasien dan halaman riwayat seorang pasien, lalu memverifikasi breadcrumb menampilkan jalur induk yang dapat diklik kembali ke beranda klinik.

**Acceptance Scenarios**:

1. **Given** admin berada di halaman daftar pasien, **When** admin melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Pasien" dengan "Pasien" sebagai item terakhir (bukan tautan) dan "Beranda Klinik" sebagai tautan ke rute induk.
2. **Given** admin berada di halaman riwayat pasien "Siti Aminah", **When** admin melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Pasien > Siti Aminah > Riwayat" dengan "Riwayat" sebagai item terakhir (bukan tautan) dan "Pasien" serta "Siti Aminah" sebagai tautan ke rute masing-masing.

---

### Edge Cases

- Apa yang terjadi bila admin membuat pasien dengan nomor telepon yang sama persis dengan pasien lain di klinik yang sama? Default: diizinkan (tidak ada unique constraint pada `phone`), sistem mengembalikan `duplicate_warning` + `duplicate_patient_id` (FR-023) agar admin dapat meninjau — bukan memblokir.
- Bagaimana sistem menangani nomor telepon dengan format non-standar atau spasi? Diterima apa adanya sebagai string (maks 50 karakter); normalisasi format bukan scope MVP.
- Apa yang terjadi bila admin menonaktifkan pasien yang masih punya booking aktif (status belum selesai)? Default: diizinkan — soft delete tidak menghapus referensi; booking tetap merujuk pasien. Pasien hanya hilang dari daftar aktif.
- Bagaimana menampilkan pasien nonaktif? Default: tidak muncul di daftar aktif; dapat diakses melalui riwayat atau penanda status terpisah bila dibutuhkan (out of scope MVP kecuali diminta).
- Apa yang terjadi bila admin mencoba membuat booking/transaksi untuk pasien yang sudah dinonaktifkan? Default: daftar pilihan pasien untuk entri baru hanya menampilkan pasien aktif; pasien nonaktif tidak muncul di pilihan.
- Bagaimana menangani pasien lintas-tenant dengan telepon yang sama? Default: aman — deteksi duplikat dan isolasi daftar pasien bersifat per-tenant; telepon sama di klinik lain tidak memicu peringatan.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-020**: Sistem WAJIB menyimpan data pasien dengan atribut: nama (wajib), tanggal lahir, jenis kelamin, telepon (wajib), WhatsApp, alamat, dan catatan. Validasi menolak nama/telepon kosong dan tanggal lahir di masa depan.
- **FR-021**: Sistem WAJIB mendeteksi nomor telepon ganda dalam tenant yang sama dan mengembalikan peringatan duplikat (`duplicate_warning` + `duplicate_patient_id`) saat membuat/memperbarui pasien, TANPA memblokir penyimpanan (FR-023).
- **FR-022**: Sistem WAJIB menyediakan tampilan riwayat kunjungan pasien yang mengagregasi booking, rekam medis/treatment, dan transaksi terkait pasien, terurut kronologis. Riwayat tetap dapat diakses walau pasien sudah dinonaktifkan.
- **FR-023**: Nomor telepon pasien TIDAK boleh dibatasi unique constraint — duplikat diizinkan dengan peringatan (bukan block), agar admin tetap dapat mencatat pasien walau berbagi nomor.
- **FR-024**: Admin klinik WAJIB dapat memperbarui data kontak pasien (telepon, WhatsApp, alamat, catatan). Perubahan tidak mengubah data historis transaksi/rekam medis lampau.
- **FR-025**: Sistem WAJIB menyediakan aksi nonaktifkan pasien via soft delete (`deleted_at`), bukan penghapusan permanen. Pasien nonaktif tetap tersimpan untuk referensi historis.
- **FR-026**: Sistem WAJIB menyembunyikan pasien nonaktif dari daftar pasien aktif (query `whereNull('deleted_at')`).
- **FR-027**: Sistem WAJIB memblokir penghapusan permanen pasien yang masih direferensi oleh booking, rekam medis, atau transaksi (restrict on delete pada FK `patient_id`).
- **FR-028**: Sistem WAJIB menjaga integritas riwayat — menonaktifkan pasien tidak menghapus booking, rekam medis, treatment, transaksi, maupun snapshot yang merujuk pasien tersebut.
- **FR-029**: Sistem WAJIB mencatat activity log naratif setiap aksi ubah-data pada pasien, termasuk "Menonaktifkan pasien {name}" dengan siapa (causer), aksi, target, dan kapan.
- **FR-030**: Sistem WAJIB memfilter daftar pasien per-tenant secara otomatis; satu klinik tidak dapat melihat atau mengelola pasien klinik lain. Deteksi duplikat bersifat per-tenant.
- **FR-031**: Sistem WAJIB mendukung pencarian, pengurutan, dan paginasi server-side pada daftar pasien aktif.
- **FR-032**: Halaman master pasien dan halaman riwayat pasien WAJIB menampilkan breadcrumb yang menunjukkan jalur induk→halaman aktif, sesuai konvensi breadcrumb seluruh halaman dalam.

### Key Entities *(include if feature involves data)*

- **Patient**: Data pasien klinik. Atribut kunci: nama, tanggal lahir, jenis kelamin, telepon (tidak unique — peringatan duplikat), WhatsApp, alamat, catatan, status nonaktif (soft delete). Milik satu tenant. Direferensi oleh booking, rekam medis, dan transaksi.
- **Booking**: Merujuk satu pasien (`patient_id`). Bila pasien dinonaktifkan, booking yang sudah ada tetap valid; pilihan pasien untuk entri baru disembunyikan.
- **MedicalRecord**: Menyimpan `patient_id` denormalized dari booking untuk query riwayat per pasien tanpa join. Tetap utuh saat pasien dinonaktifkan.
- **TreatmentRecord**: Bagian dari rekam medis; menyimpan snapshot `service_name`. Tetap utuh saat pasien dinonaktifkan.
- **Transaction**: Merujuk satu pasien (`patient_id`). Tetap utuh saat pasien dinonaktifkan; snapshot nama/harga item tidak tersinkron dari master.
- **Activity Log**: Mencatat aksi ubah-data pasien secara naratif (siapa, aksi, target, kapan), termasuk "Menonaktifkan pasien {name}".

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin dapat membuat, melihat, memperbarui, dan menonaktifkan pasien dalam waktu kurang dari 30 detik per aksi.
- **SC-002**: Daftar pasien aktif dapat diurutkan dan dicari dengan hasil tampil dalam 1 detik untuk satu klinik dengan hingga 500 pasien.
- **SC-003**: 100% aksi ubah-data pasien (buat, ubah, nonaktifkan) tercatat dalam activity log dengan deskripsi naratif yang dapat dibaca manusia.
- **SC-004**: Pasien nonaktif tidak pernah muncul di daftar pasien aktif maupun di pilihan pasien untuk entri baru (0 kemunculan).
- **SC-005**: Tidak ada penghapusan permanen pasien yang masih direferensi berhasil (0 keberhasilan, 100% diblokir restrict).
- **SC-006**: 100% riwayat booking, rekam medis, treatment, dan transaksi pasien tetap utuh dan dapat diakses setelah pasien dinonaktifkan.
- **SC-007**: 100% kasus telepon ganda dalam tenant yang sama menghasilkan peringatan duplikat tanpa memblokir penyimpanan.

## Assumptions

- Akses ke halaman master pasien terbatas pada peran klinik dengan izin kelola pasien (admin/kasir/dokter sesuai matriks izin klinik); otorisasi mengikuti sistem izin yang sudah ada.
- Otorisasi dan activity log menggunakan paket yang sudah terpasang di project (spatie/laravel-permission untuk peran dinamis; audit log native `LogAuditAction` sesuai arsitektur saat ini, target spatie/laravel-activitylog bila sudah dimigrasi).
- Endpoint API mengikuti pola tenant-scoped yang sudah ada (`/{tenant}/clinic/patients`) dengan middleware resolve tenant aktif. Rute `destroy` saat ini dikecualikan (`except(['destroy'])`) — perlu ditambahkan untuk aksi soft-delete/nonaktifkan.
- Tidak ada unique constraint pada `phone` per tenant — duplikat diizinkan dengan peringatan (FR-023), bukan diblokir.
- Daftar pilihan pasien untuk entri booking/transaksi baru hanya menyertakan pasien aktif (`deleted_at IS NULL`).
- Frontend mengikuti pola halaman master yang sudah ada (mis. layanan, produk/inventori) untuk konsistensi struktur, komponen UI, dan breadcrumb. Halaman riwayat pasien sudah tersedia (`/$tenant/clinic/patients/:id`) dan tinggal disesuaikan/dilengkapi.
- FK `patient_id` pada `bookings`, `medical_records`, dan `transactions` sudah `restrictOnDelete` per ERD; revisi ini memastikan konfigurasi konsisten.
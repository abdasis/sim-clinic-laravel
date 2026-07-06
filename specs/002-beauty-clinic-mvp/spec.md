# Feature Specification: MVP Sistem Klinik Kecantikan

**Feature Branch**: `002-beauty-clinic-mvp`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "MVP dari fitur berikut ini Fitur Utama Sistem Klinik Kecantikan: 1. Manajemen Pasien (Data pasien, Riwayat kunjungan). 2. Booking & Jadwal (Booking pasien, Jadwal booking, Status booking). 3. Rekam Medis (SOAP, Riwayat treatment, Foto before & after). 4. Manajemen Layanan (Master treatment, Harga layanan). 5. Kasir/POS (Penjualan treatment & produk, Pembayaran, Invoice). 6. Inventory (Data produk, Stok masuk & keluar). 7. Laporan (Omzet, Penjualan treatment, Penjualan produk). 8. Manajemen Pengguna (Login, Role & Permission: Admin, Dokter, Terapis, Kasir)."

## Clarifications

### Session 2026-07-06

- Q: Bagaimana penanganan tumpang tindih slot waktu penanggung jawab saat booking — block, peringatan, atau tanpa validasi? → A: Peringatan saja. Sistem mendeteksi bentrok slot penanggung jawab (Dokter/Terapis) dan menampilkan peringatan, tetapi tidak memblokir penyimpanan — keputusan akhir di tangan staf.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Manajemen Pengguna & 4 Peran Klinik (Priority: P1)

Admin klinik mengelola akun staf (Dokter, Terapis, Kasir, dan sesama Admin) di tenant-nya, masing-masing dengan peran spesifik. Sistem membatasi akses ke modul sesuai peran: Admin mengakses semua modul, Dokter fokus pada rekam medis & booking, Terapis pada treatment & booking, Kasir pada POS & transaksi. Login dasar dan keanggotaan tenant sudah disediakan oleh spec 001; user story ini menambahkan 4 peran klinik spesifik beserta permission-nya.

**Why this priority**: Akses kontrol adalah pintu masuk seluruh modul. Tanpa peran yang tepat, staf tidak bisa menjalankan operasional klinik dengan aman. Peran menjadi acuan permission di semua modul lain (P2–P3), sehingga harus ditetapkan pertama.

**Independent Test**: Buat 4 akun staf (1 Admin, 1 Dokter, 1 Terapis, 1 Kasir) di satu tenant, login masing-masing, lalu verifikasi setiap peran hanya bisa mengakses modul yang diperbolehkan dan ditolak di modul lain.

**Acceptance Scenarios**:

1. **Given** admin klinik terautentikasi, **When** admin membuat akun staf baru dan menetapkan peran "Kasir", **Then** akun staf terbentuk dengan peran Kasir dan dapat login ke tenant tersebut.
2. **Given** staf dengan peran Kasir login, **When** staf mencoba mengakses modul Rekam Medis, **Then** akses ditolak dengan pesan yang jelas.
3. **Given** staf dengan peran Dokter login, **When** staf membuka modul Rekam Medis, **Then** staf dapat melihat dan mengisi SOAP pasien.
4. **Given** admin klinik ingin mengubah peran staf dari "Terapis" menjadi "Kasir", **When** perubahan disimpan, **Then** permission staf diperbarui sesuai peran baru.
5. **Given** admin klinik menonaktifkan akun staf, **When** staf tersebut mencoba login, **Then** login ditolak.
6. **Given** tenant memiliki satu admin aktif, **When** admin mencoba menonaktifkan dirinya sendiri, **Then** aksi ditolak agar tenant tidak kehilangan admin terakhir (mengikuti spec 001).

---

### User Story 2 - Manajemen Layanan/Treatment & Harga (Priority: P1)

Admin klinik mendefinisikan master layanan/treatment (mis. Facial, Chemical Peeling, Laser) lengkap dengan harga, sehingga layanan dapat dipakai saat booking, rekam medis, dan transaksi kasir. Layanan ini menjadi data acuan bersama di seluruh modul operasional.

**Why this priority**: Layanan adalah data master yang dirujuk oleh booking, rekam medis, dan POS. Tanpa master layanan, ketiga modul tidak bisa berjalan. Setara P1 dengan data pasien dan peran karena merupakan fondasi data.

**Independent Test**: Buat 3 layanan dengan harga berbeda, lalu verifikasi ketiganya muncul sebagai pilihan saat membuat booking dan transaksi kasir.

**Acceptance Scenarios**:

1. **Given** admin klinik terautentikasi, **When** admin membuat layanan baru dengan nama, deskripsi singkat, dan harga, **Then** layanan tersimpan dan aktif (bisa dipilih di modul lain).
2. **Given** layanan aktif, **When** admin mengubah harga layanan, **Then** harga baru berlaku untuk transaksi berikutnya; transaksi lampau tetap mencatat harga saat transaksi.
3. **Given** layanan tidak lagi ditawarkan, **When** admin menonaktifkan layanan (arsip), **Then** layanan tidak muncul sebagai pilihan baru di booking/POS, tetapi data lampau tetap utuh.
4. **Given** admin membuat layanan dengan nama kosong atau harga negatif/tidak valid, **When** sistem memvalidasi, **Then** input ditolak dengan pesan validasi spesifik.

---

### User Story 3 - Manajemen Pasien & Riwayat Kunjungan (Priority: P1)

Staf klinik (Admin, Dokter, Kasir) mendaftarkan pasien baru dengan data identitas dan kontak, lalu dapat melihat riwayat kunjungan pasien tersebut secara terurut. Data pasien menjadi acuan untuk booking, rekam medis, dan transaksi.

**Why this priority**: Pasien adalah subjek utama seluruh layanan klinik. Tanpa data pasien, booking, rekam medis, dan transaksi tidak punya sasaran. Riwayat kunjungan adalah agregasi read-only dari modul booking/treatment — muncul otomatis begitu data operasional ada.

**Independent Test**: Daftarkan satu pasien baru, lalu verifikasi pasien muncul di daftar dan dapat dipilih saat membuat booking maupun transaksi kasir.

**Acceptance Scenarios**:

1. **Given** staf berperan Admin/Dokter/Kasir terautentikasi, **When** staf mendaftarkan pasien baru dengan nama, tanggal lahir/jenis kelamin, dan kontak (telepon/wa), **Then** pasien tersimpan dan dapat dicari di kemudian hari.
2. **Given** pasien sudah memiliki booking/treatment lampau, **When** staf membuka profil pasien, **Then** riwayat kunjungan pasien tampil terurut (tanggal, layanan, status, dokter/terapis penanggung jawab).
3. **Given** staf mencari pasien berdasarkan nama atau nomor telepon, **When** pencarian dijalankan, **Then** hasil yang relevan muncul dengan cepat.
4. **Given** admin ingin memperbarui kontak pasien, **When** data diubah dan disimpan, **Then** perubahan berlaku untuk seluruh modul yang merujuk pasien tersebut.
5. **Given** staf mendaftarkan pasien dengan nomor telepon yang sudah terdaftar pada pasien lain di tenant yang sama, **When** sistem memvalidasi, **Then** sistem memberi peringatan duplikat untuk mencegah double-entry (pencegahan, bukan block mutlak — staf bisa konfirmasi lanjut).

---

### User Story 4 - Booking & Jadwal (Priority: P2)

Staf klinik (Admin, Dokter, Terapis, Kasir) membuat janji temu pasien untuk layanan tertentu pada tanggal/jam tertentu dengan penanggung jawab (Dokter atau Terapis). Sistem menampilkan jadwal harian/mingguan klinik dan mengelola status booking (pending, confirmed, done, cancelled). Status booking yang "done" menjadi pintu masuk pembuatan rekam medis dan transaksi kasir.

**Why this priority**: Booking adalah operasional inti klinik yang menghubungkan pasien, layanan, dan penanggung jawab. P2 karena bergantung pada adanya data pasien (P1) dan master layanan (P1). Tanpa booking, alur kunjungan tidak terstruktur.

**Independent Test**: Buat satu booking untuk pasien + layanan + terapis di slot waktu tertentu, ubah status menjadi "confirmed" lalu "done", dan verifikasi status berubah serta booking muncul di jadwal.

**Acceptance Scenarios**:

1. **Given** pasien dan layanan sudah ada, **When** staf membuat booking dengan pasien, layanan, tanggal/jam, dan penanggung jawab (Dokter/Terapis), **Then** booking tersimpan dengan status "pending".
2. **Given** booking dengan status "pending", **When** staf mengonfirmasi, **Then** status berubah menjadi "confirmed" dan muncul di jadwal.
3. **Given** layanan selesai dilaksanakan, **When** staf menandai booking sebagai "done", **Then** status berubah menjadi "done" dan booking dapat dipakai sebagai dasar rekam medis maupun transaksi kasir.
4. **Given** pasien membatalkan janji, **When** staf mengubah status menjadi "cancelled", **Then** booking tetap tercatat dalam riwayat dengan status cancelled dan slot waktu menjadi tersedia kembali.
5. **Given** staf membuka tampilan jadwal harian/mingguan, **When** staf melihat jadwal, **Then** seluruh booking aktif tampil terurut per waktu dan penanggung jawab.
6. **Given** staf membuat booking di tanggal/jam yang sama dengan booking lain untuk penanggung jawab (Dokter/Terapis) yang sama, **When** sistem mendeteksi tumpang tindih slot, **Then** sistem menampilkan peringatan bentrok tetapi tetap mengizinkan staf menyimpan booking (keputusan akhir di tangan staf).

---

### User Story 5 - Kasir/POS — Penjualan, Pembayaran & Invoice (Priority: P2)

Kasir membuat transaksi penjualan yang berisi layanan/treatment dan/atau produk, melakukan pencatatan pembayaran, dan menerbitkan invoice/struk untuk pasien. Penjualan produk pada transaksi akan mengurangi stok produk terkait. Sistem mencatat seluruh transaksi sebagai sumber data laporan.

**Why this priority**: POS adalah sumber pendapatan tercatat dan data transaksi utama untuk laporan. P2 karena bergantung pada pasien (P1) dan master layanan/produk (P1). Tanpa POS, klinik tidak punya catatan penjualan terstruktur.

**Independent Test**: Buat satu transaksi dengan 1 layanan + 1 produk untuk seorang pasien, catat pembayaran lunas, lalu verifikasi invoice terbit, stok produk berkurang, dan transaksi masuk data laporan.

**Acceptance Scenarios**:

1. **Given** kasir terautentikasi, **When** kasir membuat transaksi baru dengan pasien, minimal 1 item (layanan atau produk), dan kuantitas, **Then** subtotal terhitung otomatis dari harga master dikali kuantitas.
2. **Given** transaksi berisi produk, **When** transaksi disimpan, **Then** stok produk terkait berkurang sesuai kuantitas terjual.
3. **Given** transaksi siap dibayar, **When** kasir mencatat pembayaran lunas dengan satu metode pembayaran (tunai/transfer/QRIS/debit), **Then** status transaksi menjadi "lunas" dan invoice dapat dicetak/ditampilkan.
4. **Given** transaksi sudah lunas, **When** kasir menerbitkan invoice, **Then** invoice menampilkan detail item, harga, total, metode pembayaran, dan identitas pasien+klinik.
5. **Given** produk dalam transaksi memiliki stok tidak mencukupi, **When** kasir mencoba menyimpan transaksi, **Then** transaksi ditolak dengan pesan stok tidak cukup.
6. **Given** transaksi selesai, **When** kasir/admin membuka daftar transaksi, **Then** transaksi terbaru muncul dengan status, total, dan pasien terkait.

---

### User Story 6 - Inventory Produk & Stok (Priority: P2)

Admin klinik mendefinisikan produk yang dijual/dipakai klinik (mis. skincare, serum, obat topikal) dan mencatat pergerakan stok masuk (restock) maupun keluar (selain via POS, mis. pemakaian internal/rusak). Sistem menjaga saldo stok real-time per produk.

**Why this priority**: Stok produk diperlukan agar POS produk berjalan akurat. P2 karena bergantung pada definisi produk (bagian dari user story ini) dan menjadi sumber pengecekan stok untuk POS.

**Independent Test**: Buat satu produk, catat stok masuk 10 unit, lakukan 1 penjualan 2 unit via POS, catat stok keluar manual 1 unit (rusak), lalu verifikasi saldo stok = 7.

**Acceptance Scenarios**:

1. **Given** admin klinik terautentikasi, **When** admin membuat produk baru dengan nama, satuan, dan stok awal, **Then** produk tersimpan dengan saldo stok awal.
2. **Given** produk sudah ada, **When** admin mencatat stok masuk (restock) dengan kuantitas dan keterangan, **Then** saldo stok bertambah dan pergerakan tercatat.
3. **Given** produk rusak/dipakai internal, **When** admin mencatat stok keluar manual dengan alasan, **Then** saldo stok berkurang dan pergerakan tercatat (terpisah dari penjualan POS).
4. **Given** staf membuka detail produk, **When** staf melihat riwayat stok, **Then** seluruh pergerakan (masuk, keluar-manual, terjual-POS) tampil dengan waktu dan keterangan.
5. **Given** produk saldo mendekati habis, **When** saldo di bawah ambang minimum, **Then** produk ditandai "stok menipis" agar staf tahu perlu restock.

---

### User Story 7 - Rekam Medis: SOAP, Treatment & Foto Before/After (Priority: P3)

Dokter/Terapis mengisi rekam medis kunjungan pasien dalam format SOAP (Subjective, Objective, Assessment, Plan), mencatat treatment yang dilakukan, dan mengunggah foto before/after sebagai dokumentasi visual hasil treatment. Rekam medis terikat ke booking/kunjungan tertentu.

**Why this priority**: Rekam medis adalah nilai tambah klinis (bukan transaksional). P3 karena bergantung pada pasien (P1), layanan (P1), dan booking (P2) — harus ada kunjungan dulu sebelum rekam medis diisi.

**Independent Test**: Dari satu booking berstatus "done", buat rekam medis dengan SOAP lengkap + 2 foto (before & after), lalu verifikasi rekam medis tersimpan dan muncul di riwayat treatment pasien.

**Acceptance Scenarios**:

1. **Given** dokter/terapis terautentikasi dan booking berstatus "done", **When** dokter mengisi SOAP (Subjective, Objective, Assessment, Plan), **Then** rekam medis tersimpan terikat ke kunjungan tersebut.
2. **Given** rekam medis sedang dibuat, **When** dokter menambahkan treatment yang dilakukan (mengacu master layanan) beserta catatan, **Then** treatment tercatat dalam rekam medis dan muncul di riwayat treatment pasien.
3. **Given** treatment butuh dokumentasi visual, **When** dokter/terapis mengunggah foto before dan after, **Then** foto tersimpan terikat ke rekam medis dan dapat dilihat kembali.
4. **Given** rekam medis sudah ada, **When** dokter membuka profil pasien dan melihat riwayat treatment, **Then** daftar treatment lampau beserta SOAP dan foto tampil terurut.
5. **Given** staf berperan Kasir, **When** staf mencoba mengisi SOAP, **Then** akses ditolak (hanya Dokter/Terapis/Admin yang boleh).

---

### User Story 8 - Laporan: Omzet, Penjualan Treatment & Produk (Priority: P3)

Admin/pemilik klinik melihat laporan periodik: total omzet, rincian penjualan per treatment, dan rincian penjualan per produk dalam rentang tanggal tertentu. Laporan dibangun dari data transaksi POS yang sudah ada.

**Why this priority**: Laporan adalah alat pengambilan keputusan bisnis setelah transaksi tercatat. P3 karena bergantung pada data transaksi POS (P2) — harus ada penjualan dulu sebelum laporan bermakna.

**Independent Test**: Setelah beberapa transaksi POS tercatat, buka laporan omzet untuk hari ini, lalu verifikasi total cocok dengan jumlah seluruh transaksi lunas hari itu.

**Acceptance Scenarios**:

1. **Given** admin terautentikasi dan ada transaksi POS lunas, **When** admin membuka laporan omzet dengan rentang tanggal tertentu, **Then** total omzet tampil sebagai jumlah seluruh transaksi lunas dalam rentang tersebut.
2. **Given** transaksi berisi layanan/treatment, **When** admin membuka laporan penjualan treatment, **Then** muncul rincian per layanan: kuantitas terjual dan total pendapatan per layanan dalam rentang tanggal.
3. **Given** transaksi berisi produk, **When** admin membuka laporan penjualan produk, **Then** muncul rincian per produk: kuantitas terjual dan total pendapatan per produk dalam rentang tanggal.
4. **Given** admin memilih rentang tanggal tanpa transaksi, **When** laporan dimuat, **Then** laporan tampil kosong/nol dengan pesan bahwa tidak ada data (bukan error).
5. **Given** ada transaksi yang belum lunas, **When** laporan omzet dihitung, **Then** hanya transaksi berstatus lunas yang masuk hitungan omzet.

---

### Edge Cases

- Apa yang terjadi ketika harga master layanan diubah setelah ada transaksi lampau? Transaksi lampau tetap memakai harga saat transaksi dicatat (harga historik); harga baru berlaku untuk transaksi berikutnya.
- Bagaimana sistem menangani pembatalan booking yang sudah berstatus "done"? Booking "done" tidak boleh dibatalkan langsung; pembatalan pasca-selesai butuh alur koreksi terpisah (di luar scope MVP — hanya catatan).
- Apa yang terjadi ketika produk dihapus dari master sedang masih dirujuk transaksi lampau? Produk tidak boleh dihapus permanen; diarsip/nonaktifkan agar transaksi lampau tetap menampilkan nama produk.
- Bagaimana jika pasien melakukan booking tetapi belum pernah terdaftar? Staf harus mendaftarkan pasien dulu sebelum membuat booking — tidak ada booking untuk pasien anonim di MVP.
- Apa yang terjadi saat pembayaran dicatat tetapi jumlah tidak sesuai total? Jika kurang dari total, transaksi berstatus "belum lunas"; jika lebih, sistem memberi peringatan kelebihan bayar untuk dikonfirmasi kasir (tidak ada saldo otomatis di MVP).
- Bagaimana menangani retur/pengembalian produk yang sudah terjual? Retur di luar scope MVP; koreksi via catatan stok keluar/adjustment manual.
- Apa yang terjadi bila staf tetap menyimpan booking meski ada peringatan tumpang tindih slot penanggung jawab? Booking tersimpan; peringatan hanya bersifat informatif, tanggung jawab jadwal ada pada staf yang membuat booking.
- Bagaimana jika foto before/after diunggah dengan format/ukuran tidak didukung? Sistem menolak unggahan dengan pesan format/ukuran yang diterima (mis. jpg/png, ukuran maksimum).
- Apa yang terjadi ketika stok produk dikurangi via POS tetapi transaksi kemudian dibatalkan? Pembatalan transaksi mengembalikan stok produk yang sebelumnya dikurangi (rollback stok).
- Bagaimana laporan menangani transaksi lintas hari dengan zona waktu berbeda? Rentang tanggal laporan mengikuti zona waktu lokal tenant (bukan UTC mentah).
- Apa yang terjadi jika admin menonaktifkan akun staf yang sedang punya sesi aktif? Sesi aktif diakhiri pada permintaan berikutnya mengikuti spec 001.
- Bagaimana jika satu booking mencakup banyak layanan? Di MVP satu booking = satu layanan utama; banyak layanan dalam satu kunjungan ditangani via banyak booking atau transaksi POS multi-item (pilihan implementasi di fase plan).

## Requirements *(mandatory)*

### Functional Requirements

#### Manajemen Pengguna & Peran

- **FR-001**: Sistem HARUS mendukung 4 peran klinik: Admin, Dokter, Terapis, Kasir (memperluas peran admin/anggota dari spec 001).
- **FR-002**: Sistem HARUS membatasi akses ke setiap modul berdasarkan peran: Admin = semua modul; Dokter = pasien (read/write), booking, rekam medis, layanan (read); Terapis = pasien (read), booking, rekam medis, layanan (read); Kasir = pasien (read/write dasar), POS, invoice.
- **FR-003**: Admin klinik HARUS dapat membuat, mengubah peran, dan menonaktifkan akun staf di tenant-nya.
- **FR-004**: Sistem HARUS menolak aksi staf ke modul yang tidak diizinkan untuk perannya dengan pesan yang jelas.
- **FR-005**: Sistem HARUS mempertahankan aturan "admin terakhir tidak boleh dinonaktifkan" dari spec 001.

#### Manajemen Layanan/Treatment

- **FR-010**: Admin HARUS dapat membuat master layanan dengan nama, deskripsi, dan harga.
- **FR-011**: Sistem HARUS memvalidasi harga layanan tidak boleh negatif dan nama tidak boleh kosong.
- **FR-012**: Admin HARUS dapat mengubah harga layanan; perubahan berlaku untuk transaksi berikutnya tanpa mengubah harga historik transaksi lampau.
- **FR-013**: Admin HARUS dapat menonaktifkan/mengarsipkan layanan; layanan nonaktif tidak muncul sebagai pilihan baru tetapi tetap terlihat di data lampau.

#### Manajemen Pasien

- **FR-020**: Staf (Admin/Dokter/Kasir) HARUS dapat mendaftarkan pasien baru dengan nama, tanggal lahir/jenis kelamin, dan kontak (telepon/wa).
- **FR-021**: Sistem HARUS menyediakan pencarian pasien berdasarkan nama atau nomor telepon.
- **FR-022**: Sistem HARUS menampilkan riwayat kunjungan pasien (agregasi read-only dari booking/treatment) yang terurut kronologis.
- **FR-023**: Sistem HARUS memperingatkan kemungkinan duplikat pasien saat nomor telepon sama dengan pasien lain di tenant yang sama.
- **FR-024**: Admin HARUS dapat memperbarui data kontak pasien.

#### Booking & Jadwal

- **FR-030**: Staf (Admin/Dokter/Terapis/Kasir) HARUS dapat membuat booking dengan pasien, layanan, tanggal/jam, dan penanggung jawab (Dokter/Terapis).
- **FR-031**: Sistem HARUS menyimpan status booking dengan siklus: pending → confirmed → done, atau → cancelled.
- **FR-032**: Sistem HARUS menampilkan jadwal harian/mingguan yang menampilkan booking aktif terurut per waktu dan penanggung jawab.
- **FR-033**: Booking berstatus "done" HARUS menjadi dasar yang dapat dirujuk oleh rekam medis dan transaksi kasir.
- **FR-034**: Sistem HARUS mencatat waktu pembuatan dan perubahan status setiap booking untuk keperluan audit sederhana.
- **FR-035**: Sistem HARUS mendeteksi tumpang tindih slot waktu untuk penanggung jawab (Dokter/Terapis) yang sama dan menampilkan peringatan kepada staf, tetapi TIDAK boleh memblokir penyimpanan booking — keputusan melanjutkan tetap di tangan staf.

#### Rekam Medis

- **FR-040**: Dokter/Terapis HARUS dapat mengisi rekam medis SOAP (Subjective, Objective, Assessment, Plan) terikat ke satu kunjungan/booking.
- **FR-041**: Rekam medis HARUS dapat mencatat treatment yang dilakukan dengan mengacu master layanan beserta catatan klinis.
- **FR-042**: Dokter/Terapis HARUS dapat mengunggah foto before/after terikat ke rekam medis, dengan validasi format (jpg/png) dan ukuran maksimum.
- **FR-043**: Sistem HARUS menampilkan riwayat treatment pasien beserta SOAP dan foto pada profil pasien.
- **FR-044**: Sistem HARUS membatasi pengisian rekam medis hanya untuk peran Dokter, Terapis, dan Admin.

#### Kasir/POS

- **FR-050**: Kasir HARUS dapat membuat transaksi yang berisi satu atau lebih item (layanan dan/atau produk) dengan kuantitas.
- **FR-051**: Sistem HARUS menghitung subtotal transaksi otomatis dari harga master dikali kuantitas saat transaksi dibuat.
- **FR-052**: Penjualan produk pada transaksi HARUS mengurangi stok produk terkait secara real-time.
- **FR-053**: Sistem HARUS menolak transaksi yang berisi produk dengan stok tidak mencukupi.
- **FR-054**: Kasir HARUS dapat mencatat pembayaran dengan minimal satu metode (tunai, transfer, QRIS, debit).
- **FR-055**: Sistem HARUS mengubah status transaksi menjadi "lunas" ketika jumlah dibayar sama dengan total.
- **FR-056**: Sistem HARUS menyimpan harga historik item pada saat transaksi, terlepas dari perubahan harga master selanjutnya.
- **FR-057**: Sistem HARUS dapat menerbitkan invoice/struk yang menampilkan detail item, harga, total, metode pembayaran, dan identitas pasien+klinik.
- **FR-058**: Pembatalan transaksi HARUS mengembalikan stok produk yang sebelumnya dikurangi.
- **FR-059**: Hanya transaksi berstatus lunas yang HARUS dihitung sebagai omzet dalam laporan.

#### Inventory

- **FR-060**: Admin HARUS dapat membuat produk baru dengan nama, satuan, dan stok awal.
- **FR-061**: Admin HARUS dapat mencatat stok masuk (restock) dengan kuantitas dan keterangan.
- **FR-062**: Admin HARUS dapat mencatat stok keluar manual (rusak/pemakaian internal) dengan alasan.
- **FR-063**: Sistem HARUS menjaga saldo stok real-time sebagai hasil dari: stok awal + masuk − keluar-manual − terjual-POS.
- **FR-064**: Sistem HARUS menampilkan riwayat pergerakan stok per produk (masuk, keluar-manual, terjual-POS) dengan waktu.
- **FR-065**: Sistem HARUS menandai produk dengan saldo di bawah ambang minimum sebagai "stok menipis".
- **FR-066**: Produk tidak boleh dihapus permanen bila dirujuk transaksi lampau; gunakan nonaktif/arsip.

#### Laporan

- **FR-070**: Sistem HARUS menyediakan laporan omzet (total pendapatan dari transaksi lunas) dengan filter rentang tanggal.
- **FR-071**: Sistem HARUS menyediakan laporan penjualan treatment: rincian kuantitas dan pendapatan per layanan dalam rentang tanggal.
- **FR-072**: Sistem HARUS menyediakan laporan penjualan produk: rincian kuantitas dan pendapatan per produk dalam rentang tanggal.
- **FR-073**: Laporan HARUS hanya menghitung transaksi berstatus lunas.
- **FR-074**: Laporan dengan rentang tanpa data HARUS menampilkan hasil kosong/nol dengan pesan, bukan error.
- **FR-075**: Hanya Admin (dan pemilik tenant bila berlaku) yang HARUS dapat mengakses modul laporan.

### Key Entities *(include if feature involves data involved)*

- **Staf Klinik (User dengan Peran)**: Akun pengguna dalam tenant (mengikuti spec 001) yang diberi salah satu peran klinik: Admin, Dokter, Terapis, atau Kasir. Peran menentukan modul yang dapat diakses.
- **Layanan/Treatment**: Data master layanan klinik. Atributi kunci: nama, deskripsi, harga, status (aktif/arsip). Dirujuk oleh booking, rekam medis, dan transaksi POS.
- **Pasien**: Subjek layanan klinik. Atributi kunci: nama, tanggal lahir, jenis kelamin, kontak (telepon/wa). Berelasi ke banyak booking, rekam medis, dan transaksi dalam satu tenant.
- **Booking**: Janji temu pasien untuk layanan tertentu. Atributi kunci: pasien, layanan, tanggal/jam, penanggung jawab (Dokter/Terapis), status (pending/confirmed/done/cancelled). Terikat ke tenant (spec 001).
- **Rekam Medis (SOAP)**: Catatan klinis per kunjungan. Atributi kunci: Subjective, Objective, Assessment, Plan, terikat ke booking/pasien, treatment dilakukan, dan foto before/after.
- **Treatment Record**: Pencatatan treatment aktual yang dilakukan pada kunjungan, mengacu master layanan. Sumber riwayat treatment pasien.
- **Foto Before/After**: Dokumentasi visual hasil treatment. Atributi kunci: tipe (before/after), file gambar, terikat ke rekam medis.
- **Produk**: Barang yang dijual/dipakai klinik. Atributi kunci: nama, satuan, saldo stok, ambang minimum, status (aktif/arsip). Dirujuk oleh transaksi POS.
- **Pergerakan Stok (Stock Movement)**: Catatan perubahan stok produk. Atributi kunci: produk, jenis (masuk/keluar-manual/terjual-POS), kuantitas, keterangan, waktu.
- **Transaksi (Penjualan)**: Catatan penjualan yang berisi item layanan/produk. Atributi kunci: pasien, daftar item+kuantitas+harga historik, subtotal, status pembayaran (belum lunas/lunas), metode pembayaran, waktu.
- **Pembayaran**: Pencatatan pembayaran transaksi. Atributi kunci: transaksi, metode (tunai/transfer/QRIS/debit), jumlah, waktu.
- **Invoice**: Dokumen transaksi yang diterbitkan untuk pasien. Atributi kunci: nomor, detail item, total, metode pembayaran, identitas pasien+klinik.
- Seluruh entitas bisnis di atas adalah **Tenant-Scopeable Data** (mengikuti spec 001): setiap record terikat ke tepat satu tenant, tidak boleh lintas-tenant.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Staf dengan peran apa pun dapat login dan hanya melihat modul yang diizinkan untuk perannya dalam satu percobaan (0 akses tidak sah ke modul terlarang).
- **SC-002**: Staf dapat mendaftarkan pasien baru dan membuat booking untuk pasien tersebut dalam total waktu kurang dari 3 menit.
- **SC-003**: Kasir dapat menyelesaikan satu transaksi penjualan (buat transaksi + catat pembayaran + terbitkan invoice) dalam waktu kurang dari 2 menit.
- **SC-004**: Penjualan produk di POS mengurangi stok real-time dengan akurasi 100% — saldo stok selalu sama dengan perhitungan (awal + masuk − keluar-manual − terjual-POS).
- **SC-005**: Pembatalan transaksi mengembalikan stok produk ke saldo semula tanpa selisih.
- **SC-006**: Laporan omzet menampilkan total yang persis sama dengan jumlah manual seluruh transaksi lunas dalam rentang tanggal yang dipilih.
- **SC-007**: Dokter/Terapis dapat mengisi rekam medis SOAP lengkap beserta foto before/after untuk satu kunjungan dalam waktu kurang dari 5 menit.
- **SC-008**: Sistem menampilkan jadwal harian klinik dengan respons yang terasa cepat untuk tenant dengan 50 pasien aktif dan 200 booking dalam sebulan.
- **SC-009**: 100% akses staf ke modul/rekord di luar perannya ditolak dengan pesan yang jelas.
- **SC-010**: Riwayat kunjungan pasien menampilkan seluruh booking/treatment pasien secara lengkap dan terurut kronologis tanpa data hilang.

## Assumptions

- Spesifikasi ini dibangun di atas spec 001 (multi-tenant single database): tenant, identifikasi tenant via path, isolasi data, autentikasi dasar (login/logout/reset password), dan keanggotaan user sudah tersedia. Spec ini menambahkan 4 peran klinik spesifik beserta permission modulnya.
- Seluruh data bisnis (pasien, booking, rekam medis, layanan, produk, transaksi) terikat ke tenant aktif dan mengikuti aturan isolasi spec 001 — tidak ada modul bisnis yang boleh lintas-tenant.
- Satu user hanya terikat ke satu peran klinik dalam satu tenant (peran ganda simultan di luar scope MVP).
- Login dasar, manajemen sesi, dan reset password sudah ditangani spec 001; user story manajemen pengguna di sini fokus pada 4 peran klinik + permission per modul.
- Rentang tanggal laporan mengikuti zona waktu lokal tenant.
- Metode pembayaran MVP: tunai, transfer, QRIS, debit (kartu). Integrasi gateway pembayaran otomatis (konfirmasi pembayaran real-time dari bank/QRIS) di luar scope MVP — kasir mencatat pembayaran secara manual.
- Retur barang, hutang piutang pasien, dan multi-currency di luar scope MVP.
- Pajak (PPN) belum termasuk dalam perhitungan invoice MVP; bisa ditambahkan sebagai konfigurasi di fase berikutnya.
- Foto before/after disimpan sebagai file gambar (jpg/png) dengan batas ukuran; manajemen storage besar/CDN di luar scope MVP.
- Notifikasi otomatis (SMS/WA konfirmasi booking, struk via WA) di luar scope MVP; bisa ditambahkan kemudian.
- Staf dapat mendaftarkan pasien tanpa persetujuan terpisah; verifikasi identitas pasien via telepon diasumsikan dilakukan offline.
- Harga historik transaksi disimpan saat transaksi dibuat, sehingga perubahan harga master tidak mempengaruhi laporan/lampau.
- Master layanan dan master produk bersifat per-tenant (tiap klinik mengelola master sendiri); tidak ada master global lintas-tenant untuk layanan/produk pada MVP.

# Feature Specification: Multi-Tenant Single Database

**Feature Branch**: `001-multi-tenant-single-db`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "setup multi tenant single database, tanpa sub-domain. refer context7"

## Clarifications

### Session 2026-07-06

- Q: Apa kebijakan Password minimum untuk registrasi tenant & user? → A: Min 8 karakter, campur huruf + angka.
- Q: Bagaimana guard admin terakhir di tenant? → A: Tolak penonaktifan/hapus admin terakhir.
- Q: Apa yang terjadi saat akses root `/` tanpa slug tenant? → A: Tampilkan landing page; klik login di header alihkan ke central tenant.
- Q: Apakah audit log aksi kritis dibutuhkan di v1? → A: Ya, log aksi kritis saja: registrasi tenant, login, manajemen user, ubah status tenant.
- Q: Berapa target skala awal tenant & user per tenant? → A: 100 tenant, 50 user per tenant.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Tenant Self-Registration (Priority: P1)

Calon pengguna mendaftarkan tenant (klinik) baru sendiri melalui halaman registrasi publik dengan mengisi Nama Perusahaan, Nomor Telepon, Email, dan Password. Sistem membuat tenant dan sekaligus akun admin pertama untuk tenant tersebut menggunakan Email + Password yang diinput. Tenant langsung aktif dan dapat diakses.

**Why this priority**: Tenant adalah fondasi seluruh fitur multi-tenant. Tanpa kemampuan membuat tenant, isolasi data tidak mungkin. MVP minimum = register tenant + admin tenant pertama.

**Independent Test**: Dapat diuji dengan mengisi form registrasi publik, lalu verifikasi tenant baru terbentuk, akun admin pertama dapat login, dan konteks tenant aktif.

**Acceptance Scenarios**:

1. **Given** calon pengguna mengakses halaman registrasi publik, **When** mengisi Nama Perusahaan, Nomor Telepon, Email, dan Password yang valid, **Then** tenant baru terbentuk dengan status aktif dan akun admin pertama terbuat dari Email + Password tersebut.
2. **Given** tenant baru terdaftar, **When** admin pertama melakukan login dengan Email + Password yang dipakai saat registrasi, **Then** login berhasil dan masuk ke konteks tenant yang baru dibuat.
3. **Given** registrasi dengan Email yang sudah dipakai tenant lain, **When** sistem memvalidasi, **Then** registrasi ditolak dengan pesan bahwa Email sudah terpakai.
4. **Given** registrasi dengan Nama Perusahaan yang menghasilkan slug yang sudah dipakai tenant lain, **When** sistem memvalidasi, **Then** registrasi ditolak dengan pesan yang jelas.
5. **Given** registrasi dengan Nomor Telepon format tidak valid atau Password lemah, **When** sistem memvalidasi, **Then** registrasi ditolak dengan pesan validasi yang spesifik.

---

### User Story 2 - Tenant Identification via URL Path (Priority: P1)

Pengguna mengakses aplikasi melalui path yang mengandung identitas tenant (mis. `/{tenant}/...`). Sistem mengenali tenant dari path dan mengaktifkan konteksnya untuk seluruh permintaan dalam sesi tersebut.

**Why this priority**: Identifikasi tenant adalah pintu masuk isolasi data. Tanpa resolver yang benar, data lintas-tenant bisa bocor. Setara P1 dengan onboarding karena keduanya saling bergantung.

**Independent Test**: Akses URL dengan slug tenant yang valid → data yang ditampilkan hanya milik tenant tersebut. Akses URL dengan slug tidak valid → error yang jelas.

**Acceptance Scenarios**:

1. **Given** tenant "klinik-sehat" aktif, **When** pengguna mengakses `/klinik-sehat/...`, **Then** konteks tenant aktif dan hanya data tenant tersebut yang terlihat.
2. **Given** URL berisi slug tenant tidak dikenal, **When** permintaan diproses, **Then** sistem menampilkan error identifikasi tenant, bukan data kosong yang menyesatkan.
3. **Given** pengguna berpindah dari `/klinik-a/...` ke `/klinik-b/...`, **When** permintaan kedua diproses, **Then** konteks aktif beralih ke tenant B sepenuhnya, tidak ada data tenant A yang terbawa.

---

### User Story 3 - Data Isolation antar Tenant (Priority: P2)

Setiap tenant memiliki data yang sepenuhnya terisolasi. Pengguna dalam satu tenant tidak dapat melihat, mengubah, atau menghapus data milik tenant lain, bahkan melalui manipulasi permintaan.

**Why this priority**: Isolasi adalah inti multi-tenant. Setelah tenant dan identifikasi berjalan (P1), jaminan isolasi melindungi integritas data. P2 karena bergantung pada P1.

**Independent Test**: Buat data di tenant A, switch ke tenant B, verifikasi data tenant A tidak muncul. Coba akses langsung resource tenant A dari konteks tenant B → ditolak.

**Acceptance Scenarios**:

1. **Given** data pasien dibuat dalam konteks tenant A, **When** konteks di-switch ke tenant B, **Then** data pasien tenant A tidak terlihat dalam daftar tenant B.
2. **Given** pengguna tenant B mencoba mengakses resource milik tenant A melalui ID langsung, **When** permintaan diproses, **Then** akses ditolak (resource dianggap tidak ada).
3. **Given** aplikasi berjalan dengan dua tenant aktif bersamaan, **When** operasi dilakukan paralel, **Then** tidak ada kebocoran data lintas-tenant.

---

### User Story 4 - Multi-User per Tenant (Priority: P2)

Satu tenant dapat memiliki banyak user (staf klinik) selain admin pertama. Admin tenant mengundang anggota baru ke tenant-nya, dan setiap user hanya dapat mengakses data tenant tempat ia terdaftar.

**Why this priority**: Klinik dioperasikan banyak staf; satu admin tidak cukup. P2 karena bergantung pada tenant + admin pertama (P1) untuk ada konteks tempat user diundang.

**Independent Test**: Admin tenant mengundang user baru via Email, user menerima undangan, set password, login, lalu verifikasi hanya melihat data tenant tersebut.

**Acceptance Scenarios**:

1. **Given** admin tenant terautentikasi, **When** admin mengundang user baru via Email, **Then** undangan terkirim dan user baru terdaftar dalam tenant tersebut (status pending).
2. **Given** user menerima undangan, **When** user menyelesaikan setup (set password), **Then** akun aktif dan user dapat login ke tenant tersebut.
3. **Given** user tenant A login, **When** user mengakses data, **Then** hanya melihat data tenant A — tidak ada akses ke tenant lain meski tenant lain ada di platform.
4. **Given** admin tenant mengundang Email yang sudah jadi user di tenant tersebut, **When** sistem memvalidasi, **Then** undangan ditolak dengan pesan yang jelas.
5. **Given** admin tenant ingin mengeluarkan anggota, **When** admin menonaktifkan/hapus keanggotaan user, **Then** user tersebut tidak dapat lagi login ke tenant.

---

### User Story 5 - Manajemen Tenant (Priority: P3)

Admin platform dapat melihat daftar semua tenant, mengubah status (aktif/non-aktif), dan memperbarui informasi dasar tenant.

**Why this priority**: Operasional platform setelah tenant ada. P3 karena tanpa P1–P2 manajemen tidak ada artinya.

**Independent Test**: Daftar semua tenant dari konteks platform (bukan tenant tertentu), ubah status satu tenant, verifikasi perubahan berlaku.

**Acceptance Scenarios**:

1. **Given** admin platform terautentikasi, **When** admin melihat daftar tenant, **Then** semua tenant platform terdaftar dengan status masing-masing.
2. **Given** tenant aktif, **When** admin menonaktifkan tenant, **Then** tenant tidak dapat diakses lagi oleh penggunanya hingga diaktifkan kembali.

---

### Edge Cases

- Apa yang terjadi ketika permintaan masuk tanpa identitas tenant di path (mis. akses root `/`)? Sistem menampilkan landing page publik; klik login di header mengalihkan ke central tenant.
- Bagaimana sistem menangani tenant yang dinonaktifkan saat ada sesi aktif? Sesi yang sedang berjalan harus diakhiri dengan pesan yang jelas.
- Apa yang terjadi ketika slug tenant mengandung karakter non-URL-safe? Validasi harus menolak slug yang tidak aman saat pembuatan.
- Bagaimana jika data global (mis. master data bawaan platform) perlu dibagi lintas tenant? Data yang ditandai sebagai global tetap dapat diakses dari semua konteks tenant tanpa terisolasi.
- Apa yang terjadi ketika admin platform mencoba membuat tenant dengan nama/slug kosong? Validasi menolak dengan pesan eksplisit.
- Apa yang terjadi saat registrasi jika Nama Perusahaan menghasilkan slug yang bentrok dengan tenant yang sudah non-aktif? Sistem harus menangani konflik slug tanpa membiarkan dua tenant memakai slug sama.
- Bagaimana sistem menangani registrasi dengan Email valid tapi Nomor Telepon format negara berbeda? Validasi nomor telepon harus jelas tentang format yang diterima (lokal/internasional).
- Apa yang terjadi jika registrasi berhasil tapi pembuatan akun admin pertama gagal di tengah jalan? Operasi harus atomik — tenant dan akun admin dibuat bersamaan, atau keduanya gagal (tidak ada tenant tanpa admin).
- Bagaimana pengguna memulihkan akses jika lupa Password admin tenant pertama? Alur reset password via Email harus tersedia (di-scope terpisah dari spec ini, tetapi dependency disebut di Assumptions).
- Apa yang terjadi jika user diundang ke tenant A lalu diundang lagi ke tenant B? Dalam scope v1 satu Email hanya satu tenant; undangan kedua ditolak atau menimpa (pilihan implementasi di fase plan).
- Bagaimana jika admin tenant satu-satunya di-nonaktifkan? Sistem menolak penonaktifan/hapus admin terakhir agar tenant tidak terkunci.
- Apa yang terjadi pada data milik user yang dikeluarkan dari tenant? Data yang dibuat user tetap milik tenant (tidak ikut terhapus); akses user saja yang dicabut.
- Bagaimana undangan yang sudah dikirim tapi belum dikonfirmasi? Undangan harus punya masa kedaluwarsa atau bisa dibatalkan admin.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem HARUS menyimpan data seluruh tenant dalam satu database yang sama, dengan kolom tenant sebagai pembeda.
- **FR-002**: Sistem HARUS mengidentifikasi tenant aktif dari segmen path URL (mis. `/{tenant-slug}/...`), tanpa menggunakan subdomain.
- **FR-003**: Sistem HARUS menerapkan isolasi data otomatis sehingga setiap model terikat ke tenant hanya bisa dibaca, diubah, atau dihapus dalam konteks tenant yang sama.
- **FR-004**: Sistem HARUS memvalidasi slug tenant unik pada saat pembuatan dan menolak duplikat.
- **FR-005**: Sistem HARUS menolak slug yang mengandung karakter yang tidak aman untuk URL.
- **FR-006**: Admin platform HARUS dapat melihat daftar dan mengubah status aktif/non-aktif tenant.
- **FR-007**: Sistem HARUS menampilkan error identifikasi yang jelas ketika slug tenant di path tidak dikenal.
- **FR-013**: Sistem HARUS menyediakan halaman registrasi publik untuk tenant baru dengan field Nama Perusahaan, Nomor Telepon, Email, dan Password.
- **FR-014**: Sistem HARUS membuat akun admin pertama untuk tenant baru secara otomatis dari Email + Password yang diinput saat registrasi.
- **FR-015**: Sistem HARUS memvalidasi Email unik lintas seluruh tenant (satu Email tidak boleh jadi admin di dua tenant).
- **FR-016**: Sistem HARUS memvalidasi format Nomor Telepon dan kekuatan Password minimum saat registrasi: Password min 8 karakter dengan campuran huruf + angka.
- **FR-017**: Sistem HARUS mengaktifkan tenant baru segera setelah registrasi berhasil tanpa perlu persetujuan manual.
- **FR-018**: Satu tenant HARUS dapat memiliki banyak user, masing-masing terikat ke tepat satu tenant.
- **FR-019**: Admin tenant HARUS dapat mengundang anggota baru ke tenant-nya via Email.
- **FR-020**: User yang diundang HARUS menyelesaikan setup akun (set password) sebelum bisa login.
- **FR-021**: User HARUS hanya dapat mengakses data tenant tempat ia terdaftar — tidak ada akses ke tenant lain.
- **FR-022**: Sistem HARUS menolak undangan ke Email yang sudah jadi user aktif di tenant yang sama.
- **FR-023**: Admin tenant HARUS dapat menonaktifkan atau menghapus keanggotaan user dari tenant-nya.
- **FR-024**: Sistem HARUS menerapkan peran minimum (admin tenant + anggota biasa) untuk membatasi aksi manajemen ke admin tenant.
- **FR-025**: Sistem HARUS menolak penonaktifan atau penghapusan admin terakhir suatu tenant.
- **FR-026**: Sistem HARUS menampilkan landing page publik di akses root `/` (tanpa slug tenant).
- **FR-027**: Tombol login di header landing page HARUS mengalihkan ke central tenant (titik masuk autentikasi platform).
- **FR-028**: Sistem HARUS mencatat audit log untuk aksi kritis: registrasi tenant, login user, manajemen user (undang/hapus/ubah peran), dan ubah status tenant.
- **FR-008**: Sistem HARUS mengizinkan data tertentu ditandai sebagai global (tidak terisolasi per tenant) untuk kebutuhan master data bersama.
- **FR-009**: Sistem HARUS mengakhiri akses ke tenant yang dinonaktifkan dan menampilkan pesan yang jelas kepada pengguna.
- **FR-010**: Sistem HARUS menjaga konteks tenant konsisten sepanjang satu permintaan, tanpa kebocoran konteks antar permintaan.
- **FR-011**: Tenant HARUS dapat diaktifkan/nonaktifkan tanpa menghapus datanya secara permanen.
- **FR-012**: Sistem HARUS mencegah akses ke resource tenant lain melalui manipulasi ID langsung (resource dianggap tidak ada).

### Key Entities *(include if feature involves data involved)*

- **Tenant**: Entitas yang merepresentasikan satu klinik/organisasi pengguna platform. Atributi kunci: nama perusahaan, slug (unik, URL-safe, diturunkan dari nama), nomor telepon, status (aktif/non-aktif). Menjadi acuan isolasi seluruh data lain.
- **Central Tenant**: Titik masuk autentikasi platform (bukan klinik). Landing page publik di root `/` dan tombol login mengarah ke sini. Tempat user login sebelum diarahkan ke tenant yang terdaftar.
- **Tenant Admin (User)**: Akun pengguna pertama suatu tenant, dibuat otomatis saat registrasi tenant. Atributi kunci: email (unik lintas tenant), password, peran admin. Berelasi ke tepat satu tenant.
- **Tenant User (Anggota)**: Akun pengguna tambahan dalam tenant, diundang admin tenant. Atributi kunci: email (unik per tenant), password, peran (admin/anggota), status (pending/aktif/nonaktif). Berelasi ke tepat satu tenant; satu user tidak dapat terdaftar di lebih dari satu tenant dalam scope v1.
- **Tenant-Scopeable Data**: Seluruh data bisnis (pasien, kunjungan, dsb.) yang memiliki relasi ke Tenant. Setiap record terikat ke tepat satu tenant; tidak boleh ada record tanpa tenant kecuali data global yang ditandai khusus.
- **Audit Log**: Catatan aksi kritis (registrasi tenant, login, manajemen user, ubah status tenant). Atributi kunci: aksi, aktor (user), tenant terkait, waktu. Dapat diakses admin platform untuk investigasi.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Calon pengguna dapat menyelesaikan registrasi tenant baru (isi form + dapat login) dalam waktu kurang dari 2 menit.
- **SC-002**: Pengguna hanya melihat data milik tenant aktifnya sendiri — 0 kebocoran data lintas-tenant dalam pengujian isolasi.
- **SC-003**: Sistem mengenali tenant dari path URL dalam waktu respons tidak lebih lambat dari aplikasi non-tenant yang setara (tidak ada penalti performa yang dirasakan pengguna).
- **SC-004**: 100% permintaan dengan slug tenant tidak valid ditolak dengan pesan yang jelas, tanpa mengekspos detail internal sistem.
- **SC-005**: Tenant yang dinonaktifkan tidak dapat diakses oleh penggunanya dalam 1 permintaan setelah nonaktif diterapkan.
- **SC-006**: Dua tenant aktif bersamaan dapat dioperasikan paralel tanpa saling mengganggu data atau konteks.
- **SC-007**: Admin tenant dapat mengundang anggota baru dan anggota tersebut dapat login dalam waktu kurang dari 3 menit sejak undangan dikirim.
- **SC-008**: Sistem mendukung minimum 100 tenant dengan 50 user per tenant tanpa degradasi performa yang dirasakan pengguna.

## Assumptions

- Aplikasi adalah platform klinik multi-tenant yang diakses via web, bukan mobile native.
- Autentikasi pengguna dasar (session/JWT, login, logout, reset password) dianggap ada atau dibangun terpisah; spec ini fokus pada registrasi tenant + akun admin pertama dan isolasi data.
- Registrasi tenant bersifat self-service publik: siapa saja bisa mendaftar tanpa persetujuan manual platform. Validasi Email (verifikasi kepemilikan) bisa ditambahkan tapi di luar scope v1 kecuali ada permintaan.
- Identifikasi tenant menggunakan segmen path URL (mis. `/{tenant-slug}/...`), bukan subdomain, sesuai keinginan eksplisit user.
- Slug tenant diturunkan otomatis dari Nama Perusahaan; jika bentrok, sistem menolak atau menambahkan sufiks unik (pilihan implementasi di fase plan).
- Database bersama (shared single database) digunakan; tidak ada database terpisah per tenant.
- Master data tertentu yang sama untuk seluruh tenant (mis. daftar spesialis bawaan) boleh tetap global.
- Satu Email tidak boleh menjadi admin di lebih dari satu tenant (asumsi FR-015); pengguna yang ingin ikut tenant lain perlu akun terpisah atau fitur undang anggota (di luar scope v1).
- Dalam scope v1, satu user terikat ke tepat satu tenant; multi-tenant simultan per pengguna (satu Email akses beberapa tenant) di luar scope v1.
- Undangan anggota via Email diasumsikan self-service oleh admin tenant tanpa persetujuan platform; verifikasi kepemilikan Email undangan bisa ditambahkan tapi di luar scope v1.
- Peran minimum: admin tenant + anggota biasa. Peran/permission lebih granular di luar scope v1.
- Dukungan penghapusan permanen tenant (beserta seluruh datanya) di luar scope v1; nonaktifkan cukup untuk MVP.
# Feature Specification: Platform Infrastructure — Tenants & Audit Log

**Feature Branch**: `003-platform-infra`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "Infra platform — Langkah 1: tenants (root multi-tenant, central seed, slug unique, status active/inactive, registrasi tenant + login central, activity log). Langkah 2: audit_logs via spatie/laravel-activitylog, custom table audit_logs, morph causer/subject, wrapper LogAuditAction. Data model sumber kebenaran: docs/normalization/README.md + docs/erd/."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Platform Admin Melihat Dashboard Central (Priority: P1)

Seorang admin platform masuk ke sistem melalui halaman login platform (bukan login klinik tenant). Setelah berhasil login, ia diarahkan ke dashboard central yang menampilkan ringkasan tenant yang dikelola, dengan breadcrumb yang merefleksikan hierarki root→dashboard. Sesi ini tercatat sebagai aktivitas audit.

**Why this priority**: Login central adalah titik masuk tunggal untuk administrasi seluruh platform multi-tenant. Tanpa ini, tidak ada cara mengelola tenant. Infra paling mendasar — semua fitur lain bergantung pada tenant yang valid dan admin yang terautentikasi.

**Independent Test**: Dapat diuji penuh dengan: seed central tenant → buka halaman login platform → login sebagai platform_admin → mendarat di dashboard central dengan breadcrumb → verifikasi record audit login tercipta. Menghasilkan akses administrasi platform yang berfungsi.

**Acceptance Scenarios**:

1. **Given** central tenant telah di-seed (slug=`central`) dan satu user platform_admin ada di central tenant, **When** admin membuka halaman login platform dan memasukkan kredensial valid, **Then** admin terautentikasi dan diarahkan ke dashboard central.
2. **Given** admin belum login, **When** admin membuka URL dashboard central langsung, **Then** admin diarahkan kembali ke halaman login platform.
3. **Given** admin berhasil login, **When** dashboard central dirender, **Then** breadcrumb menampilkan root→Dashboard (item terakhir = halaman aktif, bukan link).
4. **Given** admin berhasil login, **When** sesi dibuat, **Then** sebuah record audit untuk aksi login tercatat dengan causer = user tersebut.

---

### User Story 2 - Pendaftar Mendaftarkan Tenant Baru (Priority: P1)

Seorang calon pemilik klinik mendaftarkan organisasinya ke platform melalui registrasi tenant. Sistem membuat tenant baru dengan slug unik yang diturunkan dari nama, status awal `active`, dan user admin pertama (tenant_admin) untuk tenant tersebut. Registrasi tercatat di audit log sebagai aksi anonim (causer nullable).

**Why this priority**: Onboarding tenant baru adalah inti bisnis SaaS multi-tenant. Tanpa kemampuan mendaftarkan tenant, platform tidak bisa berkembang. Sama kritis dengan login karena keduanya menyusun infra dasar.

**Independent Test**: Dapat diuji penuh dengan: POST registrasi tenant dengan nama/phone/kredensial admin → verifikasi tenant baru dibuat dengan slug URL-safe yang unik → verifikasi user tenant_admin tercipta di tenant tersebut → verifikasi audit log `tenant.registered` tercatat. Menghasilkan tenant baru yang langsung dapat diakses adminnya.

**Acceptance Scenarios**:

1. **Given** belum ada tenant dengan slug tertentu, **When** pendaftar submit registrasi dengan nama valid, **Then** tenant baru dibuat, slug unik yang diturunkan dari nama diset, dan user tenant_admin pertama dibuat terikat ke tenant tersebut.
2. **Given** nama yang dimasukkan menghasilkan karakter non-URL-safe, **When** pendaftar submit, **Then** slug diturunkan menjadi URL-safe (karakter invalid ditolak/dinormalisasi) dan tetap unik.
3. **Given** slug hasil derivasi sudah dipakai tenant lain, **When** pendaftar submit, **Then** registrasi ditolak dengan pesan yang jelas (tidak ada dua tenant dengan slug sama).
4. **Given** registrasi tenant berhasil, **When** tenant dan user dibuat, **Then** record audit `tenant.registered` tercatat dengan properties berisi tenant_id dan subject = tenant baru (causer nullable karena aksi publik).

---

### User Story 3 - Platform Admin Mengubah Status Tenant (Priority: P2)

Admin platform dapat menonaktifkan tenant (status `active`→`inactive`) sehingga akses ke tenant ditolak, atau mengaktifkan kembali. Data tenant tidak dihapus — hanya akses yang dinonaktifkan. Perubahan status tercatat di audit log beserta status lama dan baru.

**Why this priority**: Kontrol lifecycle tenant (suspend/restore) penting untuk tata kelola SaaS tapi tidak menghambat onboarding atau login dasar. P2 karena butuh P1 (login central) sebagai prasyarat akses admin.

**Independent Test**: Dapat diuji penuh dengan: login platform_admin → toggle status tenant dari active ke inactive → verifikasi request ke rute tenant tersebut sekarang ditolak (akses diblokir) → verifikasi audit log mencatat perubahan dengan status lama `active` dan baru `inactive`. Menghasilkan kemampuan suspend/restore tenant.

**Acceptance Scenarios**:

1. **Given** tenant berstatus `active`, **When** admin platform menonaktifkannya, **Then** status berubah ke `inactive` dan request berikutnya yang menyasar tenant tersebut ditolak (akses diblokir, bukan dihapus).
2. **Given** tenant berstatus `inactive`, **When** admin platform mengaktifkan kembali, **Then** status kembali `active` dan akses tenant dipulihkan.
3. **Given** admin mengubah status tenant, **When** perubahan disimpan, **Then** record audit tercatat dengan properties menyimpan status lama dan status baru, serta causer = admin platform.

---

### User Story 4 - Sistem Mencatat Semua Aksi Kritis via Audit Log (Priority: P1)

Setiap aksi yang mengubah data atau berkaitan dengan keamanan (registrasi tenant, login, manajemen user, ubah status tenant, serta aksi klinik di fitur mendatang) tercatat di audit log secara otomatis melalui satu titik (`LogAuditAction`). Audit log tidak pernah hilang walau aktor atau target dihapus (relasi morph, bukan FK).

**Why this priority**: Audit log adalah infra yang dipasang sekali dan dipakai semua fitur setelahnya. P1 karena tanpa infra ini, Langkah 1 (yang mensyaratkan "activity log tercatat") tidak punya fondasi yang reusable.

**Independent Test**: Dapat diuji penuh dengan: jalankan aksi yang memanggil `LogAuditAction` (mis. login, registrasi tenant) → verifikasi record audit dibuat dengan causer/subject yang benar → query `Activity::where('properties->tenant_id', …)` mengembalikan record tenant terkait → hapus/hard-delete subject → verifikasi record audit tetap ada (morph tidak putus). Menghasilkan sistem audit yang dapat di-query per tenant dan tahan terhadap penghapusan aktor/target.

**Acceptance Scenarios**:

1. **Given** aksi kritis terjadi (mis. login user), **When** aksi selesai, **Then** record audit tercipta dengan action code deskriptif (mis. `user.login`), causer = pelaku, subject = target bila ada, dan properties berisi context (tenant_id, ip_address bila relevan).
2. **Given** sejumlah aksi telah terjadi di beberapa tenant, **When** seseorang query audit log untuk satu tenant via `properties->tenant_id`, **Then** hanya record milik tenant tersebut yang dikembalikan.
3. **Given** record audit merujuk subject yang kemudian di-soft-delete maupun hard-delete, **When** record audit diakses, **Then** record tetap ada dan tidak rusak (causer/subject adalah morph, resolve mengembalikan null bila target hilang — audit tidak boleh hilang saat aktor/target dihapus).
4. **Given** aksi anonim (mis. registrasi publik tenant tanpa user login), **When** aksi dicatat, **Then** record audit tercipta dengan causer nullable tanpa error.

---

### Edge Cases

- Slug derivasi tabrakan dengan slug `central` (reserved) → registrasi ditolak; `central` hanya dari seed.
- Slug berisi karakter non-URL-safe (spasi, huruf kapital, simbol) → dinormalisasi/ditolak menjadi URL-safe.
- Tenant di-nonaktifkan saat ada sesi aktif user tenant → sesi diakhiri/diblokir sesuai FR isolasi (FR-009).
- Registrasi tenant gagal di tengah (mis. user admin gagal dibuat setelah tenant dibuat) → seluruh operasi dibatalkan (atomik); tidak ada tenant tanpa admin.
- Audit log untuk aksi dengan subject besar/poliomorfik → tidak ada FK DB, jadi tidak ada constraint integrity yang gagal.
- Query `properties->tenant_id` pada volume besar tanpa index → lambat; index JSON path ditunda (`ponytail: add saat lambat`).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem MUST menyediakan satu tenant pusat (central) dengan slug `central` yang di-seed sebagai titik masuk autentikasi platform.
- **FR-002**: Sistem MUST memungkinkan pendaftar mendaftarkan tenant baru via registrasi publik, yang sekaligus membuat user admin pertama (tenant_admin) terikat ke tenant tersebut.
- **FR-003**: Sistem MUST menurunkan slug tenant dari nama secara otomatis dan menjamin slug URL-safe.
- **FR-004**: Sistem MUST menolak slug yang non-URL-safe dan menjamin keunikan slug di seluruh tenant (constraint DB).
- **FR-005**: Sistem MUST menolak pembuatan tenant dengan slug yang sudah dipakai, termasuk slug reserved `central`.
- **FR-006**: Sistem MUST mendukung status tenant `active` dan `inactive` dengan transisi dua arah oleh admin platform.
- **FR-007**: Sistem MUST memblokir akses ke tenant berstatus `inactive` (request ditolak) tanpa menghapus data tenant.
- **FR-008**: Sistem MUST menyediakan halaman login platform (terpisah dari login tenant) untuk admin platform.
- **FR-009**: Sistem MUST mengakhiri/memblokir sesi aktif tenant saat tenant dinonaktifkan.
- **FR-010**: Sistem MUST menampilkan dashboard central bagi admin platform yang terautentikasi, dengan breadcrumb yang merefleksikan hierarki root→aktif.
- **FR-011**: Sistem MUST mempertahankan data tenant saat dinonaktifkan (nonaktif ≠ hapus); penghapusan permanen tenant di luar scope v1.
- **FR-012**: Setiap FK `tenant_id` pada child tabel MUST menggunakan cascade-on-delete sehingga menghapus tenant menghapus seluruh datanya (pengecualian aturan umum; operasi hapus tenant di luar scope v1).
- **FR-013**: Sistem MUST mencatat setiap aksi kritis (registrasi tenant, login, manajemen user, ubah status tenant, aksi klinik) di audit log melalui satu titik (`LogAuditAction`).
- **FR-014**: Audit log MUST menyimpan causer (pelaku, nullable untuk aksi anonim), subject (target, nullable), action code deskriptif, dan context (termasuk `tenant_id`, ip_address bila relevan) di field properties.
- **FR-015**: Audit log MUST dapat di-query per tenant via `properties->tenant_id`.
- **FR-016**: Audit log MUST immutable — tidak pernah dihapus; relasi causer/subject bersifat morph (bukan FK) sehingga penghapusan aktor/target tidak memutus record audit.
- **FR-017**: Audit log MUST dapat dicatat untuk aksi anonim (causer nullable) tanpa error.
- **FR-018**: Sistem MUST menggunakan `spatie/laravel-activitylog` dengan custom model `App\Models\Activity` pada custom table `audit_logs` sebagai backend audit log, dengan `LogAuditAction` sebagai wrapper yang menjaga signature konsisten lintas pemanggil.
- **FR-019**: `tenant_id` pada audit log MUST disimpan di `properties->tenant_id` (bukan kolom eksplisit); index JSON path ditunda hingga terbukti lambat.

### Key Entities *(include if feature involves data)*

- **Tenant**: Organisasi/klinik yang jadi unit isolasi seluruh data. Atribut kunci: name, slug (URL-safe, unik), phone, status (active/inactive). Relasi: hasMany users dan semua entitas tenant-scopeable via `tenant_id`. Central tenant = baris khusus slug `central` (titik masuk platform). State: active↔inactive; hapus permanen di luar scope v1.
- **User (platform_admin)**: User pada central tenant dengan role `platform_admin`; pelaku aksi administratif lintas tenant. Ter-autentikasi via login platform.
- **User (tenant_admin)**: User admin pertama yang dibuat saat registrasi tenant; terikat ke tenant baru.
- **AuditLog (Activity)**: Record aksi kritis. Atribut kunci: log_name (namespace modul), description (action code), subject (morph target), causer (morph actor, nullable), properties (JSON: tenant_id, ip_address, status lama/baru, slug, dst.), created_at. Immutable, morph (bukan FK), queryable per tenant via `properties->tenant_id`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin platform dapat menyelesaikan login central dan melihat dashboard dalam waktu kurang dari 5 detik pada koneksi stabil.
- **SC-002**: Pendaftar dapat menyelesaikan registrasi tenant baru (termasuk pembuatan admin pertama) dalam satu submit, kurang dari 10 detik.
- **SC-003**: 100% slug tenant yang dihasilkan valid URL-safe dan unik; tidak ada dua tenant dengan slug sama yang dapat dibuat.
- **SC-004**: 100% aksi kritis yang terdefinisi (registrasi, login, ubah status tenant, manajemen user) meninggalkan record audit yang dapat di-query per tenant.
- **SC-005**: Audit log tetap utuh 100% ketika aktor atau target dihapus (soft atau hard) — tidak ada record audit yang hilang akibat penghapusan entitas terkait.
- **SC-006**: Tenant yang dinonaktifkan menolak 100% akses berikutnya, sementara datanya tetap utuh untuk diaktifkan kembali.

## Assumptions

- Central tenant dan minimal satu user platform_admin disediakan via seeder (bukan via UI registrasi publik); kredensialnya dikelola admin sistem saat setup awal.
- Slug `central` reserved dan tidak dapat dipakai tenant baru melalui registrasi.
- Registrasi tenant bersifat publik (tanpa login) untuk onboarding; causer audit nullable pada aksi ini.
- Autentikasi menggunakan token SPA (Sanctum) yang sudah ada di codebase; fitur ini tidak mengganti mekanisme autentikasi.
- Audit log infra (spatie + custom Activity + LogAuditAction wrapper) dipasang sekali pada langkah ini dan menjadi backend tunggal untuk semua fitur mendatang; tidak ada implementasi audit paralel native yang dipertahankan setelah migrasi.
- `tenant_id` disimpan di `properties->tenant_id` (bukan kolom eksplisit) sesuai target desain `docs/erd/audit_logs.md`; index JSON path ditunda (`ponytail`) hingga skala terbukti membutuhkan.
- Data model sumber kebenaran: `docs/normalization/README.md` dan `docs/erd/{tenants,audit_logs}.md`; FK `tenant_id` child tetap cascade-on-delete sesuai revisi (kecuali hapus tenant = di luar scope v1).
- Penghapusan permanen tenant di luar scope v1; hanya transisi active↔inactive yang didukung.
- Teks UI (label login, breadcrumb, dashboard) dalam bahasa Indonesia semi-formal friendly via sistem terjemahan yang sudah ada; tidak ada string hardcode.
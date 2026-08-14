# Feature Specification: Users & Invitations

**Feature Branch**: `004-users-invitations`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "Langkah 3 — users (ammar → zahiira): role (platform_admin/tenant_admin/member) + clinic_role (admin/doctor/therapist/cashier) + status. Admin pertama saat registrasi tenant. Email unique global (FR-015). Revisi: soft delete (deleted_at); index (tenant_id, deleted_at). RemoveUserAction ganti hard-delete → soft delete + status=inactive. FK dari bookings.assignee_id/medical_records.author_id/transactions.cashier_id → restrictOnDelete. AC: login tenant + undang/hapus/ubah peran staf; admin terakhir tidak bisa dinonaktifkan (FR-005/025); staf ter-soft-delete tidak muncul di list aktif; activity log naratif 'Menonaktifkan staf {name} — peran {role}'. FE: halaman login tenant, manajemen staf + undangan + breadcrumb. Langkah 4 — invitations (ammar → zahiira): Undang anggota; token unique; status pending/accepted/cancelled/expired. Accept → buat user. AC: tolak undang email sudah user aktif di tenant sama (FR-022); batalkan/expire; activity log. Data model sumber kebenaran: docs/normalization/README.md + docs/erd/."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Staf Klinik Masuk ke Tenant (Priority: P1)

Seorang staf klinik (admin/dokter/terapis/kasir) masuk ke sistem melalui halaman login tenant — URL menyematkan slug tenant, bukan login platform central. Setelah kredensial valid, ia terautentikasi dan diarahkan ke shell klinik dengan sidebar peran-sesuai dan breadcrumb. Sesi login tercatat di audit log.

**Why this priority**: Login tenant adalah pintu masuk seluruh operasi klinik. Tanpa ini, staf tidak bisa mengakses modul klinik (booking, POS, rekam medis). Infra paling mendasar untuk semua fitur klinik setelahnya; sama kritisnya dengan login central pada Langkah 1.

**Independent Test**: Dapat diuji penuh dengan: seed tenant + satu user aktif → buka halaman login tenant (URL `/{tenant}/login`) → submit kredensial valid → verifikasi token terbit dan pengguna mendarat di shell klinik → verifikasi audit log `user.login` tercatat dengan causer = user. Menghasilkan akses klinik yang berfungsi per tenant.

**Acceptance Scenarios**:

1. **Given** tenant aktif dengan satu user `active`, **When** staf membuka login tenant dan memasukkan kredensial valid, **Then** staf terautentikasi, token terbit, dan diarahkan ke shell klinik dengan sidebar sesuai `clinic_role`.
2. **Given** kredensial salah, **When** staf submit, **Then** login ditolak dengan pesan ramah tanpa membocorkan email mana yang ada di tenant.
3. **Given** user berstatus `inactive`/ter-soft-delete, **When** staf submit kredensial yang seharusnya benar, **Then** login ditolak.
4. **Given** tenant berstatus `inactive`, **When** staf mencoba login ke tenant tersebut, **Then** akses ditolak (ditangani middleware tenant) sebelum autentikasi dievaluasi.
5. **Given** login berhasil, **When** sesi dibuat, **Then** record audit `user.login` tercatat dengan causer = user dan properties berisi `tenant_id`.

---

### User Story 2 - Admin Tenant Mengelola Staf (Priority: P1)

Admin klinik (clinic_role `admin`) melihat daftar staf aktif tenant-nya, mengubah peran klinik staf, dan menonaktifkan staf yang keluar. Staf yang dinonaktifkan tidak muncul di daftar aktif, namun data yang pernah ia buat (booking, rekam medis, transaksi) tetap milik tenant dan tidak rusak. Setiap perubahan dicatat di audit log secara naratif.

**Why this priority**: Manajemen keanggotaan adalah operasi harian admin klinik. Tanpa ini, tenant tidak bisa menambah/mengatur staf selain admin pertama. P1 karena bersama login menyusun fondasi operasional klinik.

**Independent Test**: Dapat diuji penuh dengan: login admin → buka daftar staf → ubah peran satu staf → nonaktifkan staf lain → verifikasi daftar aktif tidak menampilkan staf yang dinonaktifkan → verifikasi data buatan staf tersebut tetap ada → verifikasi audit log naratif "Menonaktifkan staf {name} — peran {role}". Menghasilkan kemampuan kelola siklus hidup keanggotaan.

**Acceptance Scenarios**:

1. **Given** admin klinik login, **When** admin membuka daftar staf, **Then** hanya user aktif (status `active`, tidak ter-soft-delete) di tenant tersebut yang ditampilkan.
2. **Given** admin memilih staf aktif, **When** admin mengubah `clinic_role` staf, **Then** peran berubah dan audit log naratif mencatat perubahan (mis. "Memperbarui peran staf {name} — {lama} menjadi {baru}").
3. **Given** admin menonaktifkan staf (bukan admin terakhir), **When** aksi dijalankan, **Then** user diberi `status=inactive` + di-soft-delete (`deleted_at`) — bukan dihapus permanen — dan tidak muncul di daftar aktif.
4. **Given** staf yang dinonaktifkan telah membuat booking/rekam medis/transaksi, **When** staf dinonaktifkan, **Then** record tersebut tetap ada dan tetap menunjuk ke user (FK `restrictOnDelete` mencegah hard-delete; data tidak yatim).
5. **Given** admin menonaktifkan staf, **When** aksi selesai, **Then** audit log naratif tercatat: "Menonaktifkan staf {name} — peran {role}".
6. **Given** hanya tersisa satu admin aktif di tenant, **When** admin mencoba menonaktifkan dirinya/admin terakhir, **Then** aksi ditolak (FR-005/FR-025 — tidak boleh tenant tanpa admin aktif).

---

### User Story 3 - Admin Tenant Mengundang Anggota Baru (Priority: P2)

Admin klinik mengundang anggota baru via email. Sistem membuat undangan ber-token unik berstatus `pending` dengan masa kedaluwarsa. Penerima undangan membuka URL accept, menyetujui, lalu akun user dibuat terikat ke tenant. Email yang sudah menjadi user aktif di tenant yang sama tidak bisa diundang lagi.

**Why this priority**: Undangan adalah jalur onboarding staf baru selain admin pertama. P2 karena butuh P1 (login admin + daftar staf) sebagai prasyarat: hanya admin yang sudah terautentikasi yang bisa mengundang.

**Independent Test**: Dapat diuji penuh dengan: login admin → buat undangan untuk email baru → verifikasi undangan `pending` ber-token unik tercipta → simulasikan accept dengan token valid → verifikasi user aktif tercipta di tenant → verifikasi undangan jadi `accepted` → coba undang email yang sudah user aktif di tenant sama → verifikasi ditolak. Menghasilkan jalur undang-terima staf yang lengkap.

**Acceptance Scenarios**:

1. **Given** admin login dan email belum jadi user aktif di tenant, **When** admin mengundang email dengan peran klinik, **Then** undangan `pending` tercipta dengan token unik dan `expires_at`.
2. **Given** email sudah jadi user aktif di tenant yang sama, **When** admin mencoba mengundangnya, **Then** undangan ditolak (FR-022 — tidak ada undangan ganda untuk anggota aktif).
3. **Given** undangan `pending` dengan token valid, **When** penerima accept dan menyetel kredensial, **Then** user baru dibuat (status aktif/pending-set-password sesuai desain), terikat ke tenant dengan `clinic_role` sesuai undangan, dan undangan jadi `accepted`.
4. **Given** undangan telah melewati `expires_at`, **When** penerima mencoba accept, **Then** undangan dianggap `expired` dan accept ditolak.
5. **Given** admin membatalkan undangan `pending`, **When** aksi dijalankan, **Then** undangan jadi `cancelled` dan token tidak bisa dipakai accept.
6. **Given** aksi undang/accept/batal/expire terjadi, **When** aksi selesai, **Then** audit log naratif tercatat (mis. "Mengundang {email} sebagai {role}", "Menerima undangan — anggota {name} bergabung").

---

### Edge Cases

- Admin terakhir menonaktifkan dirinya sendiri → ditolak (FR-005/FR-025); tenant wajib punya minimal satu admin aktif.
- Staf menonaktifkan dirinya lalu mencoba login → ditolak karena status `inactive`/ter-soft-delete.
- Email yang sudah jadi user di tenant lain tetap bisa diundang di tenant ini selama bukan user aktif di tenant yang sama (FR-022 scope per-tenant); email tetap unik global sebagai kredensial login (FR-015 — satu email satu tenant di v1).
- Undangan ke email yang sudah punya undangan `pending` di tenant yang sama → ditolak atau menutup undangan lama (tidak ada dua undangan aktif untuk email+tenant yang sama).
- Token undangan ditebak/dimanipulasi → token bersifat unik dan acak; accept dengan token tidak valid/expired/cancelled ditolak.
- Soft-delete user yang masih punya FK terikat (booking/rekam medis/transaksi) → FK `restrictOnDelete` mencegah hard-delete; soft-delete tetap diizinkan karena tidak memutus FK (baris tetap ada).
- Registrasi tenant (Langkah 1) membuat admin pertama → fitur ini hanya mengelola staf setelah admin pertama ada; tidak ada tenant tanpa admin aktif.
- User `platform_admin` tidak punya `clinic_role` → tidak muncul di daftar staf klinik (daftar staf = user tenant dengan clinic_role), namun tetap bisa login central.
- Ubah `clinic_role` ke peran yang tidak punya hak akses modul tertentu → izin sidebar/halaman disesuaikan saat reload.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem MUST menyediakan halaman login tenant (URL menyematkan slug tenant) yang terpisah dari login platform central, sebagai pintu masuk staf klinik.
- **FR-002**: Sistem MUST mengautentikasi staf via kredensial email+password dan menerbitkan token sesi hanya bagi user dengan status `active` pada tenant yang aktif.
- **FR-003**: Sistem MUST menolak login bagi user berstatus non-`active` (termasuk yang ter-soft-delete) tanpa membocorkan apakah email terdaftar.
- **FR-004**: Sistem MUST menampilkan shell klinik dengan sidebar yang visibilitasnya diturunkan dari `clinic_role` (admin/doctor/therapist/cashier) setelah login berhasil.
- **FR-005**: Sistem MUST menolak penonaktifan user jika user tersebut adalah admin aktif terakhir di tenant (tenant tidak boleh tanpa admin aktif).
- **FR-006**: Setiap user MUST memiliki dua lapis peran: `role` platform-level (`platform_admin`/`tenant_admin`/`member`) dan `clinic_role` (`admin`/`doctor`/`therapist`/`cashier`, nullable untuk user non-klinik seperti platform admin).
- **FR-007**: Sistem MUST menjamin email unik secara global lintas tenant (FR-015) sebagai kredensial login; satu email satu tenant di v1.
- **FR-008**: Admin pertama tenant MUST tercipta saat registrasi tenant (Langkah 1) dengan `role=tenant_admin`, `clinic_role=admin`, `status=active`; fitur ini mengelola staf setelah admin pertama ada.
- **FR-009**: Sistem MUST menyediakan daftar staf aktif per tenant bagi admin klinik, yang hanya menampilkan user dengan status `active` dan tidak ter-soft-delete (`deleted_at IS NULL`) di tenant tersebut.
- **FR-010**: Admin klinik MUST dapat mengubah `clinic_role` staf aktif; perubahan langsung memengaruhi visibilitas modul staf tersebut.
- **FR-011**: Penonaktifan staf MUST dilakukan via soft-delete (`deleted_at` diisi) disertai `status=inactive`, bukan hard-delete; record yang dibuat staf tetap dimiliki tenant.
- **FR-012**: FK dari `bookings.assignee_id`, `medical_records.author_id`, `transactions.cashier_id` ke `users` MUST menggunakan `restrictOnDelete` (bukan cascade) sehingga data buatan user tidak bisa dihapus lewat penghapusan user.
- **FR-013**: Sistem MUST menyediakan index `(tenant_id, deleted_at)` pada tabel users untuk mendukung query daftar staf aktif per tenant secara efisien.
- **FR-014**: Setiap aksi perubahan keanggotaan (login, ubah peran, nonaktifkan, undang, accept, batalkan, expire) MUST tercatat di audit log via `LogAuditAction` dengan deskripsi naratif (mis. "Menonaktifkan staf {name} — peran {role}", "Mengundang {email} sebagai {role}").
- **FR-015**: Admin klinik MUST dapat membuat undangan anggota baru via email; undangan berstatus `pending`, memiliki token unik acak dan `expires_at`.
- **FR-016**: Sistem MUST menolak undangan untuk email yang sudah menjadi user aktif di tenant yang sama (FR-022).
- **FR-017**: Sistem MUST menjamin token undangan unik; accept hanya valid untuk token berstatus `pending` dan belum melewati `expires_at`.
- **FR-018**: Saat accept undangan valid, sistem MUST membuat user baru terikat ke tenant dengan `clinic_role` sesuai undangan, lalu menandai undangan `accepted`.
- **FR-019**: Sistem MUST menandai undangan `expired` ketika `expires_at` terlewati dan menolak accept terhadapnya.
- **FR-020**: Admin klinik MUST dapat membatalkan undangan `pending`; undangan menjadi `cancelled` dan token tidak dapat dipakai accept.
- **FR-021**: Status undangan MUST bertransisi satu arah: `pending` → (`accepted` | `cancelled` | `expired`); tidak ada transisi balik.
- **FR-022**: Sistem MUST mencatat causer, action code, subject (bila relevan), dan context (tenant_id, peran lama/baru, email) di audit log untuk setiap aksi keanggotaan, dengan deskripsi naratif bukan mekanis.

### Key Entities *(include if feature involves data)*

- **User**: Akun pengguna terikat tepat satu tenant (v1). Atribut kunci: name, email (unik global), password, `role` platform-level (platform_admin/tenant_admin/member), `clinic_role` (admin/doctor/therapist/cashier, nullable), status (pending/active/inactive), email_verified_at, deleted_at (soft delete). Relasi: belongsTo Tenant; hasMany Booking (assignee), MedicalRecord (author), Transaction (cashier); causer pada audit log via morph. State: registrasi→active (admin pertama), undangan→pending→active, nonaktif→inactive+deleted_at. Soft-delete + restrictOnDelete menjaga data buatan user tetap utuh.
- **Invitation**: Undangan anggota ke tenant. Atribut kunci: email diundang, role (tenant_admin/member), token (unik, acak), expires_at, status (pending/accepted/cancelled/expired). Relasi: belongsTo Tenant. State satu arah: pending → (accepted | cancelled | expired). Saat accept → buat User terikat tenant dengan clinic_role sesuai undangan. Tolak undang email yang sudah user aktif di tenant sama (FR-022).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Staf klinik dapat menyelesaikan login tenant dan melihat shell klinik dalam waktu kurang dari 5 detik pada koneksi stabil.
- **SC-002**: Admin klinik dapat melihat daftar staf aktif yang 100% akurat — tidak ada staf ter-soft-delete/nonaktif yang muncul di daftar aktif.
- **SC-003**: 100% data yang dibuat staf (booking, rekam medis, transaksi) tetap utuh dan tetap menunjuk ke staf tersebut setelah staf dinonaktifkan (tidak ada data yatim).
- **SC-004**: Sistem menolak 100% upaya menonaktifkan admin aktif terakhir di tenant (tidak ada tenant yang ditinggalkan tanpa admin aktif).
- **SC-005**: 100% aksi keanggotaan (login, ubah peran, nonaktifkan, undang, accept, batalkan, expire) meninggalkan record audit naratif yang dapat di-query per tenant.
- **SC-006**: 100% undangan ke email yang sudah menjadi user aktif di tenant yang sama ditolak; tidak ada undangan ganda untuk anggota aktif.
- **SC-007**: 100% token undangan yang expired/cancelled/tidak valid ditolak saat accept; hanya token `pending` dalam masa berlaku yang menghasilkan user baru.
- **SC-008**: Admin klinik dapat menyelesaikan siklus undang-terima anggota baru (buat undangan → accept → user aktif) dalam satu alur tanpa langkah manual tambahan.

## Assumptions

- Autentikasi memakai token SPA (Sanctum) yang sudah ada di codebase (dipasang Langkah 1); fitur ini tidak mengganti mekanisme autentikasi, hanya menambah rute login tenant.
- Admin pertama tenant dibuat saat registrasi tenant (Langkah 1, spec 003); fitur ini mengelola staf setelah admin pertama ada dan tidak mengubah alur registrasi tenant.
- Soft-delete user menggunakan kolom `deleted_at` (Eloquent SoftDeletes); penonaktifan = `status=inactive` + `deleted_at` diisi bersamaan. Hard-delete user dilarang karena user adalah author rekam medis dan causer audit log.
- Satu email satu tenant di v1 (FR-015, asumsi spec 001); email tetap unik global sebagai kredensial login, namun cek undangan-ganda (FR-022) bersifat per-tenant.
- `platform_admin` tidak punya `clinic_role` (null) dan tidak muncul di daftar staf klinik; ia masuk via login central, bukan login tenant.
- Audit log infra (`LogAuditAction` + spatie/laravel-activitylog, custom `Activity` pada table `audit_logs`) dipasang pada Langkah 2 (spec 003); fitur ini memakainya tanpa perubahan signature — hanya menambah action code naratif (`user.login`, `staff.role_changed`, `staff.deactivated`, `invitation.created`, `invitation.accepted`, dst.).
- Saat accept undangan, user baru dibuat dengan status `active` (penerima menyetel kredensial pada saat accept); varian `pending`-set-password ditunda bila tidak diperlukan UI tambahan.
- Masa kedaluwarsa undangan mengikuti nilai default wajar (mis. 7 hari); nilai spesifik ditentukan saat plan.
- Data model sumber kebenaran: `docs/normalization/README.md` dan `docs/erd/{users,invitations}.md`; revisi soft-delete + index `(tenant_id, deleted_at)` + FK `restrictOnDelete` sudah tercermin di ERD `users.md`.
- Teks UI (label login, daftar staf, form undangan, breadcrumb) dalam bahasa Indonesia semi-formal friendly via sistem terjemahan yang sudah ada; tidak ada string hardcode.
- Breadcrumb wajib pada setiap halaman dalam (login tenant, daftar staf, form undangan) merefleksikan hierarki root→tenant→aktif.
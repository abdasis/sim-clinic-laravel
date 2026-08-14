# Feature Specification: Rekam Medis SOAP Klinik

**Feature Branch**: `009-medical-records`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "medical_records (ammar → zahiira) — setelah #8. 1 per booking (unique booking_id, R10). SOAP. Booking harus done (FR-033/040). Hanya doctor/therapist/admin (FR-044, Policy). Revisi: soft delete (deleted_at). FK booking_id/patient_id/author_id → restrictOnDelete (bukan cascade). Index baru (tenant_id, patient_id, created_at) untuk FR-022. patient_id denormalized dari booking — immutable setelah record ada (kaitan anomali #2). AC: soft-delete rekam medis → treatment/photo tetap; hard-delete diblokir restrict; query riwayat per pasien pakai index; activity log 'Mengisi rekam medis pasien {patient}'. FE: form rekam medis SOAP + breadcrumb. Sumber kebenaran: docs/erd/ + docs/normalization/README.md + docs/normalization/workflow.md."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Dokter Mengisi Rekam Medis SOAP dari Booking Selesai (Priority: P1)

Dokter atau terapis mengisi rekam medis SOAP (Subjektif, Objektif, Assessment, Plan) untuk pasien setelah kunjungan selesai. Hanya satu rekam medis per booking (R10) — bila booking sudah punya rekam medis, sistem menolak pembuatan duplikat. Booking harus berstatus `done` sebelum rekam medis bisa diisi (FR-033/040). `patient_id` diambil dari booking dan menjadi immutable setelah rekam medis ada — bila booking berusaha mengubah pasien setelah rekam medis dibuat, sistem menolak (anomali #2). Hanya peran dokter/terapis/admin yang boleh mengisi (FR-044).

**Why this priority**: Rekam medis adalah inti modul klinis (US7) dan catatan legal praktik. Tanpa pencatatan SOAP, riwayat treatment pasien (FR-022) dan fondasi treatment record/foto tidak ada. MVP klinis tidak berfungsi tanpa ini.

**Independent Test**: Bisa diuji dengan membuat satu booking berstatus `done`, lalu mengisi rekam medis SOAP untuk pasien tersebut tanpa melibatkan treatment record atau foto, dan memverifikasi rekam medis tersimpan dengan `patient_id` sesuai booking — semuanya tanpa modul treatment/photo.

**Acceptance Scenarios**:

1. **Given** dokter sudah login dan ada booking berstatus `done` untuk pasien A, **When** dokter membuka form rekam medis dan mengisi SOAP lalu menyimpan, **Then** rekam medis tersimpan dengan `booking_id` unik, `patient_id` = pasien A, `author_id` = dokter, dan status booking tetap `done`.
2. **Given** booking belum `done` (mis. `confirmed`), **When** dokter mencoba mengisi rekam medis, **Then** sistem menolak dengan pesan bahwa booking harus selesai dulu (FR-033/040).
3. **Given** booking sudah punya rekam medis, **When** dokter mencoba membuat rekam medis kedua untuk booking yang sama, **Then** sistem menolak (unique `booking_id`, R10) — satu rekam medis per booking.
4. **Given** rekam medis sudah ada untuk booking pasien A, **When** admin mencoba mengubah `patient_id` pada booking tersebut, **Then** sistem menolak (422) — `patient_id` immutable setelah rekam medis ada (anomali #2).
5. **Given** kasir (clinic_role tanpa izin modul medical), **When** kasir mencoba mengakses form rekam medis, **Then** sistem menolak (403) — hanya dokter/terapis/admin (FR-044).

---

### User Story 2 - Riwayat Rekam Medis per Pasien (Priority: P2)

Dokter dapat melihat riwayat rekam medis seorang pasien secara kronologis. Sistem meng-query rekam medis per pasien menggunakan `patient_id` denormalized tanpa join ke bookings (FR-022), didukung index `(tenant_id, patient_id, created_at)`. Riwayat menampilkan tanggal, dokter pengisi, dan ringkasan SOAP per kunjungan.

**Why this priority**: Riwayat pasien (FR-022) adalah nilai utama rekam medis — dokter butuh konteks kunjungan sebelumnya untuk diagnosis berikutnya. `patient_id` denormalized justru untuk query ini tanpa join. Penting, tapi baru bermakna setelah rekam medis bisa dibuat.

**Independent Test**: Bisa diuji dengan membuat 3 rekam medis untuk satu pasien lewat 3 booking `done` berbeda, lalu memverifikasi daftar riwayat pasien menampilkan ketiganya secara kronologis tanpa melibatkan relasi booking.

**Acceptance Scenarios**:

1. **Given** pasien A punya 3 rekam medis dari kunjungan berbeda, **When** dokter membuka riwayat pasien A, **Then** ketiga rekam medis muncul terurut kronologis (created_at) tanpa join ke bookings.
2. **Given** dua pasien A dan B di klinik sama, **When** dokter melihat riwayat pasien A, **Then** hanya rekam medis pasien A yang muncul — tidak ada kebocoran rekam medis pasien B (`patient_id` scope).
3. **Given** pasien A di klinik X dan pasien C di klinik Y dengan nama sama, **When** dokter klinik X melihat riwayat, **Then** hanya rekam medis tenant X yang muncul (isolasi tenant).
4. **Given** pasien punya rekam medis lama dan baru, **When** dokter melihat riwayat, **Then** query menggunakan index `(tenant_id, patient_id, created_at)` — riwayat tampil cepat untuk pasien dengan puluhan kunjungan.

---

### User Story 3 - Soft-Delete Rekam Medis & Integritas Child (Priority: P3)

Admin dapat menonaktifkan (soft-delete) rekam medis tanpa menghapus catatan klinis permanen. Saat rekam medis di-soft-delete, treatment record dan foto yang terkait tetap utuh (tidak terbawa). Penghapusan permanen diblokir bila rekam medis masih memiliki treatment record atau foto (restrict). Booking, pasien, dan dokter pengisi yang direferensi tidak boleh terhapus begitu rekam medis ada (restrict on delete).

**Why this priority**: Rekam medis adalah catatan legal — integritas wajib bertahan untuk audit. Soft-delete memungkinkan koreksi tanpa kehilangan jejak, dan restrict menjaga referensi child (treatment/photo) tetap utuh. Penting tapi baru relevan setelah rekam medis dasar ada.

**Independent Test**: Bisa diuji dengan men-soft-delete satu rekam medis yang punya treatment record, lalu memverifikasi rekam medis tidak muncul di daftar aktif namun treatment record tetap ada, dan rekam medis dengan foto tidak bisa di-hard-delete.

**Acceptance Scenarios**:

1. **Given** rekam medis aktif, **When** admin men-soft-delete rekam medis, **Then** `deleted_at` terisi, rekam medis tidak muncul di daftar aktif, dan data tetap utuh untuk audit.
2. **Given** rekam medis punya treatment record, **When** rekam medis di-soft-delete, **Then** treatment record tetap utuh — tidak terbawa soft-delete parent.
3. **Given** rekam medis punya foto, **When** rekam medis di-soft-delete, **Then** foto tetap utuh di database (file fisik cleanup ditangani terpisah via queue, bukan cascade DB).
4. **Given** rekam medis masih punya treatment record atau foto, **When** admin mencoba menghapus permanen rekam medis, **Then** sistem memblokir (restrict on delete FK `medical_record_id` child).
5. **Given** rekam medis merujuk booking/pasien/dokter, **When** admin mencoba menghapus booking/pasien/dokter tersebut, **Then** sistem memblokir (restrict on delete FK `booking_id`/`patient_id`/`author_id`) — referensi klinis tidak boleh putus.

---

### User Story 4 - Activity Log & Breadcrumb Form Rekam Medis (Priority: P4)

Setiap aksi ubah-data pada rekam medis tercatat dalam activity log secara naratif. Khusus pengisian rekam medis baru, log menyebutkan: "Mengisi rekam medis pasien {patient}". Halaman form rekam medis dan riwayat pasien menampilkan breadcrumb yang menunjukkan jalur dari beranda klinik ke halaman aktif.

**Why this priority**: Audit log naratif wajib untuk kepatuhan catatan klinis (konstitusi VI). Breadcrumb konvensi konsistensi UI seluruh halaman dalam. Keduanya penting tapi bukan blocker fungsional inti.

**Independent Test**: Bisa diuji dengan mengisi rekam medis baru untuk pasien A lalu memverifikasi activity log berisi narasi "Mengisi rekam medis pasien {A}"; dan membuka form rekam medis, memverifikasi breadcrumb menampilkan jalur induk.

**Acceptance Scenarios**:

1. **Given** dokter mengisi rekam medis baru untuk pasien A, **When** rekam medis disimpan, **Then** activity log tercatat naratif "Mengisi rekam medis pasien {A}" dengan siapa (causer), aksi, target, kapan, dan properti atribut lengkap SOAP.
2. **Given** dokter mengedit rekam medis yang sudah ada, **When** perubahan disimpan, **Then** activity log tercatat dengan properti old/new diff SOAP (nilai lama dan baru).
3. **Given** admin men-soft-delete rekam medis, **When** soft-delete diproses, **Then** activity log tercatat naratif penonaktifan rekam medis.
4. **Given** dokter membuka form rekam medis dari booking, **When** dokter melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Rekam Medis > {Pasien}" dengan item terakhir non-tautan dan item induk dapat diklik kembali.
5. **Given** dokter membuka riwayat rekam medis pasien, **When** dokter melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Pasien > {Pasien} > Rekam Medis" dengan item terakhir non-tautan.

---

### Edge Cases

- Apa yang terjadi bila booking belum `done` saat rekam medis diisi? Ditolak dengan pesan booking harus selesai dulu (FR-033/040).
- Bagaimana bila dua dokter mencoba membuat rekam medis untuk booking yang sama bersamaan? Unique constraint `booking_id` (R10) menjamin hanya satu yang berhasil; yang kedua ditolak.
- Apa yang terjadi bila booking berusaha mengubah `patient_id` setelah rekam medis ada? Ditolak (422) — `patient_id` immutable setelah record ada (anomali #2), mencegah drift riwayat pasien terbelah.
- Bagaimana bila SOAP hanya sebagian terisi (mis. hanya Subjektif)? Diperbolehkan — semua field SOAP nullable; dokter bisa menyimpan draf dan melengkapi nanti.
- Apa yang terjadi bila rekam medis di-soft-delete tetapi masih punya treatment record/foto? Soft-delete diizinkan (child tetap utuh); hard-delete diblokir restrict.
- Bagaimana menampilkan rekam medis ter-soft-delete? Tidak muncul di daftar aktif; tetap ada di database dengan `deleted_at` untuk audit forensik.
- Apa yang terjadi bila pasien/dokter/booking yang direferensi di-soft-delete/hapus? Rekam medis tetap utuh (restrict on delete); parent tidak bisa dihapus bila rekam medis merujuknya. Pasien/dokter di-soft-delete, rekam medis tetap.
- Bagaimana bila rekam medis diisi oleh peran tanpa izin (kasir/member)? Ditolak (403) — hanya dokter/terapis/admin (FR-044, Policy `clinic.access` modul `medical_record`).
- Apa yang terjadi bila rekam medis lintas-tenant diakses? Tidak muncul — `BelongsToTenant` + `TenantScope` memfilter `tenant_id` otomatis.
- Bagaimana bila query riwayat pasien punya puluhan kunjungan? Index `(tenant_id, patient_id, created_at)` menjaga query tetap cepat tanpa full scan.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-040**: Sistem WAJIB menyimpan rekam medis SOAP dengan atribut: booking (wajib, unik per booking R10), pasien (wajib, dari booking), dokter/terapis pengisi (wajib, otomatis dari user login), dan empat field SOAP (Subjektif, Objektif, Assessment, Plan — masing-masing nullable).
- **FR-033**: Sistem WAJIB mengizinkan pengisian rekam medis hanya untuk booking berstatus `done`; booking non-`done` ditolak.
- **FR-044**: Sistem WAJIB membatasi akses pengisian/manajemen rekam medis hanya pada peran dokter/terapis/admin (otorisasi via Policy modul `medical_record`).
- **FR-088**: Sistem WAJIB menegakkan satu rekam medis per booking (unique `booking_id` per tenant, R10) — pembuatan rekam medis kedua untuk booking yang sama ditolak.
- **FR-089**: Sistem WAJIB menetapkan `patient_id` dari booking saat rekam medis dibuat; `patient_id` immutable setelah record ada — perubahan `patient_id` pada booking yang sudah punya rekam medis ditolak (422) untuk mencegah drift riwayat pasien (anomali #2).
- **FR-022**: Sistem WAJIB menyediakan daftar riwayat rekam medis per pasien secara kronologis menggunakan `patient_id` denormalized tanpa join ke bookings, didukung index `(tenant_id, patient_id, created_at)`.
- **FR-090**: Sistem WAJIB menyediakan soft-delete rekam medis (`deleted_at`) — catatan klinis legal tidak di-hard-delete; data tetap utuh untuk audit. Rekam medis ter-soft-delete tidak muncul di daftar aktif.
- **FR-091**: Sistem WAJIB menjaga integritas child saat soft-delete rekam medis — treatment record dan foto tetap utuh (tidak terbawa soft-delete parent).
- **FR-092**: Sistem WAJIB memblokir penghapusan permanen rekam medis yang masih memiliki treatment record atau foto (restrict on delete FK `medical_record_id` child).
- **FR-093**: Sistem WAJIB menerapkan restrict on delete pada FK `booking_id`, `patient_id`, dan `author_id` rekam medis — booking/pasien/dokter yang direferensi tidak boleh dihapus begitu rekam medis ada.
- **FR-094**: Sistem WAJIB mencatat activity log naratif setiap aksi ubah-data rekam medis, termasuk "Mengisi rekam medis pasien {patient}" dengan siapa (causer), aksi, target, kapan, dan properti atribut (create → full SOAP; update → diff old/new).
- **FR-095**: Sistem WAJIB memfilter rekam medis per-tenant secara otomatis; satu klinik tidak dapat melihat rekam medis klinik lain (`BelongsToTenant` + `TenantScope`).
- **FR-096**: Sistem WAJIB mendukung pencarian, pengurutan, dan paginasi server-side pada daftar rekam medis aktif (tidak termasuk soft-deleted).
- **FR-097**: Halaman form rekam medis dan riwayat pasien WAJIB menampilkan breadcrumb yang menunjukkan jalur induk→halaman aktif, sesuai konvensi breadcrumb seluruh halaman dalam.

### Key Entities *(include if feature involves data)*

- **MedicalRecord**: Rekam medis SOAP pasien per kunjungan. Atribut kunci: booking (unik, R10), pasien (denormalized dari booking, immutable setelah record ada), pengisi (dokter/terapis), SOAP (Subjektif/Objektif/Assessment/Plan), `deleted_at` (soft delete). Aggregate root untuk `TreatmentRecord` + `MedicalPhoto` (hasMany). Milik satu tenant. FK `booking_id`/`patient_id`/`author_id` restrict on delete. `patient_id` denormalized transitif dari booking untuk query riwayat pasien (FR-022) tanpa join.
- **Booking**: Rujukan wajib rekam medis — harus `done` (FR-033/040). Satu rekam medis per booking (R10). `patient_id` booking menjadi immutable bila rekam medis sudah ada (anomali #2).
- **Patient**: Pemilik riwayat rekam medis (FR-022). `patient_id` denormalized ke rekam medis untuk query tanpa join. FK restrict on delete.
- **TreatmentRecord / MedicalPhoto**: Child rekam medis (hasMany). Tetap utuh saat parent di-soft-delete; restrict mencegah hard-delete parent bila child ada. Detail teknis di spec langkah 15/16.
- **Activity Log**: Mencatat aksi ubah-data rekam medis secara naratif, termasuk "Mengisi rekam medis pasien {patient}".

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Dokter dapat mengisi rekam medis SOAP dari booking `done` (buka form + isi + simpan) dalam waktu kurang dari 2 menit.
- **SC-002**: 100% rekam medis terikat satu booking unik (R10) — 0 duplikat rekam medis per booking.
- **SC-003**: 100% rekam medis hanya bisa dibuat dari booking `done` — 0 rekam medis dari booking non-`done`.
- **SC-004**: `patient_id` rekam medis 100% sesuai booking saat dibuat dan immutable setelahnya — 0 perubahan `patient_id` booking diterima bila rekam medis ada.
- **SC-005**: Riwayat rekam medis per pasien tampil dalam 1 detik untuk pasien dengan hingga 100 kunjungan, menggunakan index `(tenant_id, patient_id, created_at)`.
- **SC-006**: Rekam medis ter-soft-delete tidak pernah muncul di daftar aktif (0 kemunculan) namun tetap ada di database untuk audit.
- **SC-007**: Treatment record dan foto tetap utuh 100% saat rekam medis induk di-soft-delete.
- **SC-008**: Tidak ada penghapusan permanen rekam medis yang masih memiliki treatment record/foto berhasil (0 keberhasilan, 100% diblokir restrict).
- **SC-009**: Tidak ada penghapusan booking/pasien/dokter yang masih direferensi rekam medis berhasil (0 keberhasilan, 100% diblokir restrict on delete).
- **SC-010**: 100% aksi ubah-data rekam medis tercatat dalam activity log naratif yang dapat dibaca manusia, termasuk "Mengisi rekam medis pasien {patient}".
- **SC-011**: Akses rekam medis terbatas 100% pada peran dokter/terapis/admin — 0 akses berhasil dari peran lain (403).
- **SC-012**: Halaman form rekam medis dan riwayat pasien menampilkan breadcrumb yang benar 100% (jalur induk→aktif, item terakhir non-tautan).

## Assumptions

- Akses ke manajemen rekam medis terbatas pada peran klinik dengan izin modul `medical_record` (dokter/terapis/admin sesuai matriks izin klinik FR-044); otorisasi mengikuti sistem izin yang sudah ada (Gate `clinic.access` modul `medical_record`, Policy).
- Otorisasi dan activity log menggunakan paket yang sudah terpasang (spatie/laravel-activitylog untuk audit log naratif via `LogAuditAction`/`activity()`).
- Endpoint API mengikuti pola tenant-scoped yang sudah ada (`/{tenant}/clinic/medical-records`) dengan middleware resolve tenant aktif + `BelongsToTenant` trait + `TenantScope`.
- Layering Controller → Service → Action sudah ada; spec ini merinci revisi rekam medis, bukan membangun ulang arsitektur.
- Immutability `patient_id` booking (anomali #2) ditegakkan di FormRequest/Policy pada langkah booking (spec 008) — tolak ubah `patient_id` bila `medicalRecord` exists → 422. Spec ini menyatakan kebutuhan invariant; penerapan ada di kedua sisi.
- `patient_id` adalah denormalized transitif dari booking (pelanggaran 3NF intensional terkontrol, lihat docs/normalization/README.md); ditetapkan saat buat record, immutable setelahnya.
- Treatment record (langkah 15) dan foto (langkah 16) adalah child rekam medis; spec ini menyatakan integritas restrict/soft-delete, detail teknis di spec masing-masing.
- Frontend mengikuti pola halaman klinis yang sudah ada (TanStack Start, shadcn `radix-nova`, Tailwind v4) untuk konsistensi struktur, komponen UI, dan breadcrumb.
- Field SOAP (Subjektif/Objektif/Assessment/Plan) nullable untuk mendukung penyimpanan draf bertahap; tidak ada validasi wajib isi penuh di MVP.
- Pembersihan file fisik foto saat rekam medis soft-delete ditangani via queue listener/observer (detail di spec langkah 16), bukan cascade DB.
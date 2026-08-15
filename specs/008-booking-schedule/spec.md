# Feature Specification: Booking & Jadwal Klinik

**Feature Branch**: `008-booking-schedule`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "bookings (ammar → zahiira) — setelah L2 #5,#6. Assignee = doctor/therapist. Overlap detection (FR-035, tidak block, flag overlap_warnings). State pending→confirmed→done, →cancelled (FR-031); done tidak →cancelled. Index (tenant_id,assignee_id,start_at,end_at) + (tenant_id,start_at). Revisi: FK patient_id/assignee_id/service_id → restrictOnDelete. Booking tidak soft delete (status=cancelled cukup). Anomali #2 — immutability patient_id: tolak ubah patient_id bila medicalRecord exists, di FormRequest/Policy → 422. AC: booking + jadwal + overlap warning; ubah patient_id pada booking ada medical record → 422; state transition enforced; activity log naratif status lama→baru. FE: kalender/jadwal + form booking + breadcrumb; disable ubah pasien bila medical record ada (UX mencegah 422)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Buat & Kelola Booking Pasien (Priority: P1)

Admin resepsionis atau staf klinik membuat booking janji temu pasien dengan dokter/terapis untuk layanan tertentu pada waktu tertentu. Staf memilih pasien, layanan utama, dan penanggung jawab (dokter/terapis), lalu menentukan waktu mulai dan selesai. Sistem menyimpan booking dengan status awal `pending`. Staf dapat melihat daftar booking, memperbarui detail, dan mengubah status booking sepanjang transisi yang diizinkan. Bila staf mencoba mengubah pasien pada booking yang sudah memiliki rekam medis, sistem menolak perubahan tersebut.

**Why this priority**: Booking adalah inti operasional klinik — tanpa kemampuan membuat dan mengelola janji temu, tidak ada kunjungan, tidak ada rekam medis, tidak ada transaksi. Ini fondasi yang menghubungkan pasien, layanan, dan staf medis.

**Independent Test**: Dapat diuji dengan membuat satu booking baru (pasien + layanan + dokter + waktu), melihatnya muncul di daftar dan jadwal, lalu mengubah statusnya — seluruhnya tanpa melibatkan modul rekam medis atau transaksi POS.

**Acceptance Scenarios**:

1. **Given** staf klinik sudah login dengan izin kelola booking, **When** staf mengisi form booking baru dengan pasien, layanan aktif, dokter/terapis, waktu mulai hari ini, dan waktu selesai setelah mulai lalu menyimpan, **Then** booking tersimpan dengan status `pending` dan muncul di daftar serta jadwal.
2. **Given** booking "Facial Glow — dr. Andi" berstatus `pending`, **When** staf mengubah status menjadi `confirmed`, **Then** status berubah menjadi `confirmed`, waktu perubahan tercatat, dan aktivitas tercatat sebagai narasi "Mengubah status booking {pasien} — status berubah dari 'pending' ke 'confirmed'".
3. **Given** booking berstatus `done`, **When** staf mencoba mengubah status menjadi `cancelled`, **Then** sistem menolak dengan pesan bahwa booking yang sudah selesai tidak dapat dibatalkan.
4. **Given** booking sudah memiliki rekam medis, **When** staf mencoba mengubah pasien pada booking tersebut, **Then** sistem menolak perubahan pasien (422) dengan pesan bahwa pasien tidak dapat diubah karena rekam medis sudah ada.
5. **Given** admin mencoba menghapus permanen pasien/dokter/layanan yang masih direferensi booking, **When** penghapusan dijalankan, **Then** sistem memblokir penghapusan (restrict) — booking tetap utuh sebagai riwayat.

---

### User Story 2 - Jadwal & Deteksi Bentrokan (Priority: P2)

Staf klinik melihat jadwal janji temu dalam tampilan kalender harian/mingguan untuk merencanakan operasional. Saat membuat atau mengubah booking, sistem memeriksa apakah penanggung jawab (dokter/terapis) sudah memiliki booking lain pada rentang waktu yang tumpang tindih. Bila ada bentrokan, sistem memberi peringatan namun tetap mengizinkan penyimpanan — keputusan menyelesaikan bentrokan ada di staf.

**Why this priority**: Jadwal memberi visibilitas operasional dan deteksi bentrokan mencegah penjadwalan ganda yang tidak disengaja. Penting, tetapi booking dasar (P1) harus ada lebih dulu agar ada sesuatu untuk dijadwalkan dan diperiksa bentrokannya.

**Independent Test**: Dapat diuji dengan membuat dua booking pada dokter/terapis yang sama dengan waktu tumpang tindih, lalu memverifikasi peringatan bentrokan muncul pada booking kedua tanpa memblokir penyimpanan, dan keduanya tampil di tampilan jadwal.

**Acceptance Scenarios**:

1. **Given** dokter "dr. Andi" sudah memiliki booking 10:00–11:00 berstatus `confirmed`, **When** staf membuat booking baru untuk dr. Andi pada 10:30–11:30, **Then** booking tersimpan dan peringatan bentrokan (`overlap_warnings`) ditampilkan — penyimpanan tidak diblokir.
2. **Given** staf membuka tampilan jadwal mingguan, **When** staf melihat rentang minggu berjalan, **Then** seluruh booking klinik pada minggu tersebut tampil dipetakan ke waktu dan penanggung jawab.
3. **Given** booking bentrokan yang dibatalkan (`cancelled`), **When** sistem memeriksa bentrokan untuk booking baru, **Then** booking yang dibatalkan tidak dihitung sebagai bentrokan.
4. **Given** dua booking untuk dokter berbeda pada rentang waktu sama, **When** sistem memeriksa bentrokan, **Then** tidak ada peringatan bentrokan karena penanggung jawab berbeda.

---

### User Story 3 - Breadcrumb Navigasi Booking (Priority: P3)

Staf klinik menavigasi halaman booking dan jadwal dengan breadcrumb yang menunjukkan jalur dari beranda klinik ke halaman aktif, sehingga staf tahu posisi halaman saat ini dan dapat kembali ke induk.

**Why this priority**: Breadcrumb adalah konvensi konsistensi UI seluruh halaman dalam; nilai tinggi untuk orientasi namun bukan blocker fungsional inti.

**Independent Test**: Dapat diuji dengan membuka halaman daftar booking dan jadwal, lalu memverifikasi breadcrumb menampilkan jalur induk yang dapat diklik kembali ke beranda klinik.

**Acceptance Scenarios**:

1. **Given** staf berada di halaman daftar booking, **When** staf melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Booking" dengan "Booking" sebagai item terakhir (bukan tautan) dan "Beranda Klinik" sebagai tautan ke rute induk.
2. **Given** staf membuka form ubah booking tertentu, **When** staf melihat header, **Then** breadcrumb menampilkan "Beranda Klinik > Booking > Ubah Booking" dengan item terakhir sebagai non-tautan dan "Booking" sebagai tautan ke daftar booking.

---

### Edge Cases

- Apa yang terjadi bila staf mengisi waktu selesai sebelum atau sama dengan waktu mulai? Ditolak dengan pesan validasi bahwa waktu selesai harus setelah waktu mulai.
- Apa yang terjadi bila staf mengisi waktu mulai di masa lalu? Ditolak dengan pesan validasi bahwa waktu mulai harus setelah sekarang (kecuali skenario koreksi data — default MVP: tolak).
- Bagaimana menangani pemilihan dokter/terapis yang bukan bagian klinik (tenant) saat ini? Pilihan hanya menyertakan user klinik dengan peran `doctor`/`therapist`; pilihan dari luar tidak tersedia.
- Apa yang terjadi bila layanan yang dipilih berstatus arsip? Tidak muncul di pilihan layanan saat membuat booking baru (mengikuti FR-014); booking lama yang merujuk layanan terarsip tetap valid.
- Bagaimana sistem menangani bentrokan saat memperbarui (bukan membuat) booking? Sistem memeriksa bentrokan terhadap booking lain penanggung jawab yang sama, mengecualikan booking itu sendiri; peringatan muncul bila ada, penyimpanan tetap diizinkan.
- Apa yang terjadi bila booking dibatalkan lalu staf ingin membatalkan pembatalan (reactivate)? Default MVP: status `cancelled` bersifat final — tidak ada transisi keluar dari `cancelled`. Buat booking baru bila perlu.
- Bagaimana menampilkan indikator bentrokan di daftar/jadwal? Booking dengan bentrokan ditandai dengan badge/ikon peringatan yang dapat di-hover untuk detail.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-030**: Sistem WAJIB menyimpan booking dengan pasien, layanan utama, penanggung jawab (dokter/terapis), waktu mulai, dan waktu selesai. Layanan utama tunggal per booking (R9).
- **FR-031**: Sistem WAJIB memaksa transisi status booking hanya melalui jalur yang diizinkan: `pending`→`confirmed`→`done`, serta `pending`/`confirmed`→`cancelled`. Status `done` TIDAK boleh berubah ke `cancelled`. Status `cancelled` bersifat final (tidak ada transisi keluar). Transisi di luar jalur ini ditolak.
- **FR-032**: Sistem WAJIB menyediakan tampilan jadwal harian/mingguan yang menampilkan seluruh booking klinik pada rentang waktu dipilih, dipetakan ke waktu dan penanggung jawab.
- **FR-033**: Booking berstatus `done` WAJIB menjadi dasar pembuatan rekam medis dan rujukan transaksi. Booking yang belum `done` tidak dapat memiliki rekam medis.
- **FR-034**: Sistem WAJIB mencatat waktu perubahan status (`status_changed_at`) setiap kali status booking berubah.
- **FR-035**: Sistem WAJIB mendeteksi bentrokan jadwal penanggung jawab — booking lain dengan `assignee_id` sama yang rentang waktunya tumpang tindih (`start_at < other.end_at AND end_at > other.start_at`) dan status bukan `cancelled` — dan memberi tanda `overlap_warnings`. Deteksi bersifat peringatan, TIDAK memblokir penyimpanan.
- **FR-036**: Sistem WAJIB memvalidasi: waktu mulai setelah sekarang, waktu selesai setelah waktu mulai, pasien/layanan aktif/penanggung jawab valid dalam tenant yang sama, dan penanggung jawab memiliki peran `doctor` atau `therapist`.
- **FR-037**: Sistem WAJIB menolak perubahan `patient_id` pada booking yang sudah memiliki rekam medis (422), untuk menjaga integritas denormalized `medical_records.patient_id` (Anomali #2). Penolakan terjadi di lapis validasi/otorisasi sebelum data diubah.
- **FR-038**: Sistem WAJIB memblokir penghapusan permanen pasien, penanggung jawab (user), atau layanan yang masih direferensi booking (`restrictOnDelete`). Booking tidak dihapus saat parent dihapus/arsip/nonaktif — riwayat jadwal tetap utuh untuk audit.
- **FR-039**: Sistem WAJIB mencatat activity log naratif untuk setiap aksi ubah-data booking, termasuk perubahan status dengan narasi status lama→baru ("Mengubah status booking {pasien} — status berubah dari '{lama}' ke '{baru}'"), pencatatan siapa (causer), aksi, target, dan kapan. `withProperties` menyimpan atribut yang diubah beserta nilai lama dan baru.
- **FR-040**: Sistem WAJIB mengelola booking sebagai entitas non-soft-delete — status `cancelled` sudah cukup menandai berakhirnya booking; riwayat jadwal tetap utuh. Tidak ada kolom soft-delete pada booking.

### Key Entities *(include if feature involves data)*

- **Booking**: Janji temu pasien dengan penanggung jawab (dokter/terapis) untuk layanan utama pada rentang waktu tertentu. Atribut kunci: pasien, layanan utama, penanggung jawab, waktu mulai/selesai, status (`pending`/`confirmed`/`done`/`cancelled`), catatan, waktu perubahan status. Milik satu tenant. Direferensi oleh rekam medis (1:1) dan transaksi (opsional). FK `patient_id`/`assignee_id`/`service_id` memakai `restrictOnDelete`; FK `tenant_id` memakai `cascadeOnDelete`.
- **Patient**: Pasien yang dibooking. `patient_id` pada booking menjadi immutable setelah rekam medis dibuat (Anomali #2). Pasien di-nonaktifkan (bukan hard-delete) bila masih direferensi booking.
- **User (Assignee)**: Staf medis dengan peran `doctor`/`therapist` sebagai penanggung jawab booking. Di-nonaktifkan bila masih direferensi booking.
- **Service**: Layanan utama booking (tunggal, R9). Layanan terarsip tetap valid untuk booking yang sudah ada; disembunyikan dari pilihan booking baru.
- **MedicalRecord**: Rekam medis SOAP 1:1 per booking. Kehadirannya memicu immutability `booking.patient_id` (FR-037). `patient_id` pada rekam medis adalah denormalized transitif dari `bookings.patient_id`.
- **Activity Log**: Mencatat aksi ubah-data booking secara naratif, termasuk transisi status lama→baru dengan `withProperties` menyimpan nilai lama dan baru.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-007**: Staf klinik dapat membuat, melihat, memperbarui, dan mengubah status booking dalam waktu kurang dari 30 detik per aksi.
- **SC-008**: Deteksi bentrokan jadwal mengembalikan hasil dalam 1 detik untuk satu klinik dengan hingga 500 booking pada rentang minggu berjalan.
- **SC-009**: 100% transisi status yang melanggar jalur diizinkan (mis. `done`→`cancelled`, `cancelled`→status lain) ditolak — tidak ada satupun transisi ilegal berhasil.
- **SC-010**: 100% upaya mengubah pasien pada booking yang memiliki rekam medis ditolak (422) — tidak ada satupun perubahan `patient_id` pasca-rekam-medis berhasil.
- **SC-011**: 100% upaya menghapus permanen pasien/dokter/layanan yang masih direferensi booking diblokir (restrict) — tidak ada booking yatim karena parent dihapus.
- **SC-012**: 100% aksi ubah-data booking (buat, ubah, ubah status) tercatat dalam activity log dengan deskripsi naratif status lama→baru yang dapat dibaca manusia.
- **SC-013**: Tampilan jadwal harian/mingguan menampilkan seluruh booking klinik pada rentang dipilih dalam 1 detik untuk satu klinik dengan hingga 500 booking.

## Assumptions

- Akses ke halaman dan endpoint booking terbatas pada peran klinik dengan izin modul booking (sesuai matriks izin klinik); otorisasi mengikuti sistem izin yang sudah ada (Gate `clinic.access`).
- Endpoint API mengikuti pola tenant-scoped yang sudah ada (`/{tenant}/clinic/bookings`) dengan middleware resolve tenant + tenant aktif.
- Otorisasi dan activity log menggunakan paket yang sudah terpasang (spatie/laravel-activitylog untuk audit log); role statis klinik tetap memakai enum + Gate matrix sesuai konstitusi pengecualian.
- Data model sumber kebenaran mengikuti `docs/normalization/README.md` dan `docs/erd/bookings.md` + `docs/erd/medical_records.md`; revisi FK `restrictOnDelete`, booking non-soft-delete, dan immutability `patient_id` (Anomali #2) sudah thirdalam dokumen tersebut.
- Tumpang tindih dihitung per `assignee_id` dalam tenant yang sama; booking berstatus `cancelled` dikecualikan dari perhitungan bentrokan.
- Immutability `patient_id` (FR-037) ditegakkan di lapis FormRequest/Policy sebelum data diubah; frontend menonaktifkan field pasien bila rekam medis sudah ada sebagai pencegah UX (bukan pertahanan tunggal — backend tetap menolak 422).
- Frontend mengikuti pola halaman master yang sudah ada untuk konsistensi struktur, komponen UI, dan breadcrumb; tampilan jadwal memakai komponen kalender yang sesuai stack.
- Implementasi backend (ammar) lalu test (zahiira) sesuai alur delegasi project; skill relevan (`/laravel-best-practices`, `/clean-code-principles`) dipakai saat penulisan kode.
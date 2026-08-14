# Feature Specification: Company Profile Web (Public Landing Page + CMS)

**Feature Branch**: `010-company-profile-web`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "web profile dengan user story @docs/user-stories/zapclinic-company-profile.md semua content harus dinamis. gunakan tiptap editor untuk editor tambahan"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Pengunjung melihat landing page company profile (Priority: P1)

Sebagai calon klien, saya ingin membuka halaman publik company profile
klinik dan melihat seluruh informasi klinik, treatment, promo, dan testimoni
dalam satu halaman scroll, sehingga saya bisa memahami layanan klinik dan
mengambil tindakan (booking / belanja / chat) tanpa kebingungan.

**Why this priority**: Landing page adalah pintu masuk utama calon klien.
Tanpa halaman ini, tidak ada cara bagi publik untuk mengenal klinik. Ini
adalah MVP inti — semua section lain hanya relevan jika halaman ini bisa
dimuat dan dinavigasi.

**Independent Test**: Buka route `/` tanpa login. Verifikasi semua section
(header, hero, value props, treatment, promo, brand, testimoni, CTA, footer)
ter-render dengan konten dinamis dari backend. Klik navigasi, CTA, dan
back-to-top berfungsi.

**Acceptance Scenarios**:

1. **Given** halaman landing belum pernah dikunjungi, **When** saya membuka
   route `/`, **Then** halaman dimuat dengan sticky header berisi logo, menu
   navigasi, language switcher, dan ikon cart.
2. **Given** halaman landing dimuat, **When** saya melihat hero banner,
   **Then** saya melihat carousel berisi slide-slide promo dengan judul,
   subteks, dan tombol CTA (link internal atau WhatsApp), serta kontrol
   navigasi slide (dots/arrows) dan auto-rotate.
3. **Given** halaman landing dimuat, **When** saya scroll ke section "Kenapa
   Memilih", **Then** saya melihat grid kartu value proposition (icon + judul
   + deskripsi) yang jumlah dan urutannya ditentukan backend.
4. **Given** halaman landing dimuat, **When** saya scroll ke section
   "Treatment Pilihan", **Then** saya melihat grid kartu treatment (gambar,
   badge opsional, category tags, judul, deskripsi) dan link "Lihat Semua".
5. **Given** saya berada di section "Treatment Pilihan", **When** saya klik
   kartu treatment, **Then** saya diarahkan ke halaman detail treatment
   (`/treatment/<slug>`).
6. **Given** halaman landing dimuat, **When** saya scroll ke section brand,
   testimoni, pharma, CTA booking, CTA e-store, dan footer, **Then** setiap
   section menampilkan konten dinamis sesuai data backend dengan layout
   konsisten.
7. **Given** saya telah scroll ke bawah, **When** saya klik tombol back-to-top,
   **Then** halaman scroll kembali ke posisi paling atas.
8. **Given** widget chat mengambang terlihat di pojok kanan bawah, **When**
   saya klik tombol "Talk To Us", **Then** panel chat terbuka menampilkan
   daftar channel messaging.

---

### User Story 2 - Admin mengelola konten landing page secara dinamis (Priority: P2)

Sebagai admin klinik, saya ingin mengelola seluruh konten landing page
(slide hero, value props, treatment, promo, brand, testimoni, section CTA,
navigasi, footer) melalui panel admin, sehingga saya bisa memperbarui
informasi klinik tanpa bantuan developer dan konten langsung tampil di
halaman publik.

**Why this priority**: Syarat eksplisit user — "semua content harus dinamis".
Tanpa panel kelola, konten static tidak bisa diubah. Namun landing page
sendiri (P1) bisa ditampilkan dulu dengan data seeded; panel kelola adalah
lapisan berikutnya.

**Independent Test**: Login sebagai admin. Buka panel kelola konten. CRUD
satu slide hero / satu value prop / satu testimoni. Verifikasi perubahan
muncul di halaman publik `/`.

**Acceptance Scenarios**:

1. **Given** saya login sebagai admin, **When** saya membuka panel kelola
   konten, **Then** saya melihat daftar entitas konten (hero slide, value
   prop, treatment, promo, brand, testimoni, section CTA, navigasi, footer)
   yang bisa dilihat, ditambah, diubah, dan dihapus.
2. **Given** saya membuka form tambah/edit konten, **When** saya mengisi field
   teks deskripsi/narasi, **Then** saya menggunakan editor rich-text (Tiptap)
   yang mendukung format dasar (bold, italic, heading, list, link, gambar).
3. **Given** saya menyimpan perubahan konten, **When** saya membuka halaman
   publik `/`, **Then** konten terbaru langsung tampil tanpa perlu deploy.
4. **Given** saya mengelola urutan tampil (slide hero, value prop, treatment),
   **When** saya mengubah urutan, **Then** urutan baru tercermin di halaman
   publik.
5. **Given** saya mengelola status aktif/non-aktif suatu konten, **When**
   saya menonaktifkan satu slide/value prop, **Then** konten tersebut tidak
   tampil di halaman publik.
6. **Given** saya mengelola navigasi header dan link footer, **When** saya
   menambah/mengubah item menu, **Then** header dan footer publik mencerminkan
   perubahan.

---

### User Story 3 - Pengunjung mengganti bahasa halaman (Priority: P3)

Sebagai pengunjung, saya ingin mengganti bahasa halaman (ID/EN) melalui
switcher di header, sehingga saya bisa membaca konten company profile dalam
bahasa yang saya pahami.

**Why this priority**: Multi-bahasa meningkatkan jangkauan tetapi bukan
syarat MVP. Landing page berfungsi penuh dalam satu bahasa (ID default).
Internasionalisasi adalah pengaya.

**Independent Test**: Buka `/`. Klik switcher "EN". Verifikasi label UI dan
konten terjemahan tampil dalam bahasa Inggris. Klik "ID" untuk kembali.

**Acceptance Scenarios**:

1. **Given** halaman landing dimuat dalam bahasa default (ID), **When** saya
   klik tombol "EN" di header, **Then** label UI dan konten yang diterjemahkan
   dimuat dalam bahasa Inggris.
2. **Given** halaman dalam bahasa Inggris, **When** saya klik tombol "ID",
   **Then** halaman kembali ke bahasa Indonesia.

---

### Edge Cases

- Apa yang terjadi ketika tidak ada slide hero aktif? Section hero menampilkan
  empty state ramah atau disembunyikan, halaman tetap dapat diakses.
- Apa yang terjadi ketika gambar konten gagal dimuat? Tampilkan placeholder
  gambar fallback, layout tidak rusak.
- Apa yang terjadi saat admin menyimpan konten dengan field wajib kosong?
  Validasi mencegah penyimpanan, tampilkan pesan error jelas.
- Bagaimana sistem menangani urutan tampil duplikat? Sistem menstabilkan
  urutan (diurutkan berdasarkan urutan lalu ID) tanpa error.
- Apa yang terjadi saat panel chat pihak ketiga gagal dimuat? Widget
  menampilkan pesan fallback atau link alternatif kontak.
- Bagaimana konten rich-text yang sangat panjang ditampilkan di kartu
  treatment/testimoni? Konten di-truncate dengan indikator "selengkapnya"
  atau dibatasi saat authoring.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST menyajikan halaman publik landing page di route `/`
  tanpa autentikasi, berisi 12 section: header sticky, hero carousel, value
  props, treatment pilihan, promo, sub-brand, testimoni, pharma banner,
  online booking CTA, voucher/e-store CTA, footer, floating chat widget.
- **FR-002**: System MUST memuat seluruh konten landing page secara dinamis
  dari backend (tidak ada konten hardcode di frontend selain label UI sistem).
- **FR-003**: System MUST menyediakan hero carousel yang auto-rotate antar
  slide, dengan kontrol manual (dots/arrows), tiap slide berisi judul,
  subteks, gambar/visual, dan CTA (link internal atau eksternal).
- **FR-004**: System MUST menampilkan grid value proposition berisi kartu
  (icon, judul, deskripsi) yang jumlah, urutan, dan status aktifnya ditentukan
  backend.
- **FR-005**: System MUST menampilkan grid treatment pilihan berisi kartu
  (gambar, badge opsional, category tags, judul, deskripsi, link detail)
  yang konten dan urutannya ditentukan backend.
- **FR-006**: System MUST mengarahkan klik kartu treatment ke halaman detail
  treatment `/treatment/<slug>`.
- **FR-007**: System MUST menampilkan section sub-brand berisi kartu brand
  (gambar/logo, deskripsi, link eksternal "More info").
- **FR-008**: System MUST menampilkan section testimoni berisi kartu
  (quote, nama, label "ngeZAP Sejak <tahun>", avatar opsional).
- **FR-009**: System MUST menampilkan section CTA (online booking, voucher/
  e-store) berisi judul, deskripsi, dan tombol primary yang mengarah ke route
  tujuan.
- **FR-010**: System MUST menampilkan footer multi-kolom berisi link navigasi,
  ikon social media, ikon official marketplace, teks copyright, dan link
  back-to-top — seluruhnya dapat dikelola backend.
- **FR-011**: System MUST menyediakan sticky header dengan logo, menu
  navigasi, language switcher, dan ikon cart; item menu dapat anchor-link ke
  section di halaman sama atau route terpisah, dapat dikelola backend.
- **FR-012**: System MUST menyediakan tombol back-to-top yang muncul setelah
  scroll melewati viewport dan mengembalikan scroll ke atas saat diklik.
- **FR-013**: System MUST menyediakan floating chat widget fixed bottom-right
  yang dapat di-expand/collapse, menampilkan daftar channel messaging dan/
  atau form kontak.
- **FR-014**: System MUST menyediakan panel admin untuk CRUD seluruh entitas
  konten landing page (hero slide, value prop, treatment, promo, brand,
  testimoni, section CTA, navigasi, footer link, pengaturan situs).
- **FR-015**: Panel admin MUST menggunakan editor rich-text (Tiptap) untuk
  field deskripsi/narasi konten, mendukung format: bold, italic, heading,
  list, link, dan sisipan gambar.
- **FR-016**: System MUST mendukung pengaturan urutan tampil (sort order)
  untuk entitas yang berurutan (hero slide, value prop, treatment, promo,
  navigasi).
- **FR-017**: System MUST mendukung status aktif/non-aktif per entitas
  konten; hanya konten aktif yang tampil di halaman publik.
- **FR-018**: System MUST mendukung dua bahasa (ID default, EN) untuk label
  UI dan konten yang dapat diterjemahkan, diakses via language switcher.
- **FR-019**: System MUST mencatat audit log untuk setiap perubahan konten
  oleh admin (siapa, aksi, target, kapan, deskripsi naratif).
- **FR-020**: System MUST membatasi akses panel kelola konten hanya kepada
  pengguna dengan peran admin yang berwenang.

### Key Entities *(include if feature involves data)*

- **HeroSlide**: slide carousel berisi judul, subteks, gambar/visual, label
  CTA, URL CTA, urutan tampil, status aktif.
- **ValueProp**: kartu value proposition berisi icon, judul, deskripsi
  (rich-text), urutan tampil, status aktif.
- **FeaturedTreatment**: kartu treatment pilihan berisi gambar, judul, slug,
  deskripsi (rich-text), badge (opsional), category tags, link detail, urutan
  tampil, status aktif. Berpotensi terhubung ke entitas Service master bila
  treatment berasal dari data layanan klinik.
- **Promo**: kartu promo berisi gambar, judul, deskripsi (rich-text), label
  CTA, URL CTA, urutan tampil, status aktif.
- **Brand**: kartu sub-brand berisi gambar/logo, nama, deskripsi (rich-text),
  URL eksternal, status aktif.
- **Testimonial**: kartu testimoni berisi quote (rich-text), nama, label
  tahun, avatar (opsional), urutan tampil, status aktif.
- **ContentSection**: blok CMS generik untuk section dengan layout khusus
  (pharma banner, online booking CTA, voucher/e-store CTA) berisi key
  identifier, judul, body (rich-text), label CTA, URL CTA, tipe layout.
- **NavigationItem**: item menu header/footer berisi label, URL tujuan,
  tipe (anchor section / route internal / eksternal), urutan tampil, posisi
  (header / footer), status aktif.
- **SiteSetting**: pengaturan global situs berisi logo, teks copyright, link
  social media, link official marketplace, konfigurasi chat widget, bahasa
  default.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Pengunjung dapat melihat seluruh 12 section landing page dalam
  satu kali muat halaman tanpa error dalam waktu kurang dari 3 detik pada
  koneksi standar.
- **SC-002**: Admin dapat memperbarui satu konten (mis. slide hero) dan
  melihat perubahannya tampil di halaman publik dalam waktu kurang dari 1
  menit, tanpa bantuan developer.
- **SC-003**: 100% konten landing page (teks, gambar, link, urutan) dapat
  dikelola melalui panel admin tanpa menyentuh kode.
- **SC-004**: Pengunjung dapat menavigasi dari landing page ke halaman detail
  treatment, halaman booking, dan halaman e-store dalam maksimal 2 klik dari
  CTA terkait.
- **SC-005**: Pengunjung dapat mengganti bahasa halaman dan melihat konten
  diterjemahkan dalam waktu kurang dari 2 detik.
- **SC-006**: Halaman landing tetap dapat diakses dan terbaca dengan baik pada
  perangkat mobile (responsive) tanpa horizontal scroll.
- **SC-007**: Setidaknya 90% pengunjung baru dapat mengidentifikasi layanan
  utama klinik dan minimal satu CTA booking/belanja dalam kunjungan pertama.

## Assumptions

- Company profile adalah halaman publik terpisah dari aplikasi clinic SaaS;
  route `/` melayani landing page, panel kelola konten berada di area admin
  terautentikasi.
- Entitas treatment pada landing page awalnya dikelola mandiri sebagai
  featured content; integrasi penuh dengan Service master klinik (jika
  diperlukan) ditangani di fase plan.
- Editor rich-text Tiptap dipilih eksplisit oleh user untuk field deskripsi/
  narasi konten; ini adalah keputusan teknis yang dibawa masuk sebagai
  requirement (FR-015) karena diminta langsung.
- Multi-bahasa ID/EN: ID adalah default; konten admin dapat diperbarui per
  bahasa. Mekanisme terjemahan konten dinamis (bukan hanya label UI) perlu
  dirancang di fase plan.
- Chat widget: replikasi menggunakan komponen chat sendiri atau embed pihak
  ketiga; detail integrasi ditentukan di fase plan.
- Autentikasi admin menggunakan sistem yang sudah ada (Sanctum/role admin);
  tidak membangun sistem auth baru.
- Konten gambar disimpan via media storage yang sudah ada di project atau
  mekanisme upload standar; detail di fase plan.
- Responsive mobile adalah scope v1; optimasi performa lanjutan (lazy load,
  CDN gambar) dapat ditambahkan kemudian.
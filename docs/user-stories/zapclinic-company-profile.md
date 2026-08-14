# Zapclinic Company Profile — UI Spec & User Stories

Dieksplore dari `https://zapclinic.com/` pada 2026-08-14. Landing page publik (no auth, no form submission). Tujuan: replikasi struktur section untuk web company profile sendiri dengan desain lebih modern. Tidak ada interaksi submit — semua CTA arah ke link eksternal (WhatsApp, route lain, E-Store).

---

## UI Spec

### Screen: Landing page (single-page scroll)

- Route: `/`
- Type: landing / company profile (single-page, scroll-based)
- Breadcrumb: n/a (public landing, no inner hierarchy)
- Layout: sticky header + scrollable sections stacked vertikal + floating chat widget (bottom-right) + back-to-top (bottom-right, muncul saat scroll)

#### Section 1 — Header / Navbar (sticky, top)
- Elemen (kiri → kanan):
  - Logo (kiri)
  - Nav menu: Treatments, Solusi Kami, Promo, E-Store, Lokasi, Artikel, Tentang ZAP, Online Booking
  - Language switcher: EN | ID (link, toggle)
  - Cart icon (badge count, link ke `/cart`)
- Notes: sticky saat scroll. Item menu = anchor-link ke section di halaman sama atau route terpisah. Online Booking = CTA emphasized.

#### Section 2 — Hero carousel/banner
- Layout: full-width banner, teks kiri, visual/gradient kanan
- Konten (carousel 3 slide):
  1. "Happy Skin Happy Budget" — subtek FACIAL . LASER . PRODUK 3 IN 1, Mulai dari 599RB, batas 15 September 2026, CTA "Cek Happy Skin Happy Budget" → `/store?category=...`
  2. "NEW Treatment Z-Weightloss Program" — subtek berbasis medis, CTA WhatsApp → `api.whatsapp.com/send?phone=...`
  3. "Bikin Kulit Happy Mood Ikut Happy Facial Bu Ayu" — 9 tahapan facial, CTA WhatsApp
- State transitions: auto-rotate slide, manual dots/arrows
- Notes: judul besar, subtek kecil, CTA button primary. Improve: typografi lebih kontras, spacing lega, gradient subtle.

#### Section 3 — Kenapa Memilih ZAP (value props)
- Layout: grid 3 kolom × 2 baris (6 kartu) atau 6 kolom × 1 baris di desktop
- Kartu (icon + judul + deskripsi singkat):
  | # | Judul | Deskripsi |
  |---|-------|-----------|
  | 1 | Berpengalaman | 15+ tahun, destinasi terpercaya perawatan wajah/rambut/tubuh |
  | 2 | Pelayanan Ramah | Staf utamakan pelayanan personal, kenyamanan, keramahan |
  | 3 | Teknologi Terkini | Teknologi terbaik, standar medis ketat |
  | 4 | Tepat Waktu | Menghargai waktu klient, selalu tepat waktu |
  | 5 | Ratusan Lokasi | 100+ outlet terintegrasi di seluruh Indonesia |
  | 6 | Dokter Bersertifikasi | Dokter berpengalaman, memprioritaskan keamanan |
- Notes: icon besar di atas, teks ringkas. Improve: icon line-style konsisten, hover lift ringan.

#### Section 4 — Treatment Pilihan (featured grid)
- Layout: section header ("Treatment Pilihan" + link "Lihat Semua" → `/treatments`) + grid kartu 4 kolom
- Kartu treatment:
  - Gambar (atas)
  - Badge "TERFAVORIT" / "SAAT INI" (overlay pojok, opsional)
  - Category tags (chip kecil: "Menghilangkan Bulu", "rejuvenation", "brightening", dll)
  - Judul (contoh: "Women Brazilian (VIO) Super Hair Removal")
  - Deskripsi singkat (2-3 baris)
  - Link ke `/treatment/<slug>`
- Sample items terlihat: Brazilian VIO, Underarm SHR + Body Rejuvenation, IPL Rejuvenation, Photo Facial Glow, Photo Facial Acne, Double Pro Yellow Laser, Underarms Super Brightening, Underarm Combo, Underarm Rejuvenation Combo
- Notes: kartu rapat, hover scale gambar tipis. Improve: badge pill rounded, tag chip ringan, card border subtle.

#### Section 5 — PROMO
- Layout: section header ("PROMO" + link "Lihat Semua" → `/promo`) + grid/banner promo
- Notes: konten promo tidak ter-render penuh di ekstraksi (kemungkinan dynamic). Model sebagai section placeholder berisi kartu promo.

#### Section 6 — ZAP BRANDS (sub-brand)
- Layout: section header ("ZAP BRANDS") + 3 kartu brand horizontal
- Kartu brand:
  - Gambar/logo
  - Deskripsi singkat
  - Link "More info" → domain eksternal
- Items: Premiere Clinic (`premiereclinic.id`), Menology Clinic (`menologyclinic.com`), Juva by ZAP (`juvabyzap.com`)
- Notes: sub-brand punya situs sendiri, link eksternal.

#### Section 7 — Testimoni (Apa Saja Kata Mereka)
- Layout: section header + grid/carousel kartu testimoni
- Kartu testimoni:
  - Quote (naratif panjang, 3-5 kalimat)
  - Nama (contoh: "Wika Cahyaningtyas")
  - Label "ngeZAP Sejak <tahun>" (contoh: 2024, 2015)
- Notes: quote asli pelanggan. Improve: kutipan besar dengan tanda petik, avatar opsional.

#### Section 8 — ZAP Pharma Skincare (product banner)
- Layout: full-width banner, teks + harga mulai
- Konten: "ZAP PHARMA SKINCARE MULAI DARI 85.000" + deskripsi racikan medis
- Notes: section produk, bukan grid. CTA implisit ke E-Store.

#### Section 9 — Me Time / Online Booking CTA
- Layout: split panel (teks + ilustrasi/CTA)
- Konten: judul "ME TIME KINI JADI LEBIH PRAKTIS" + deskripsi + CTA "Booking Sekarang" → `/online-booking`
- Notes: CTA kuat, satu tombol primary.

#### Section 10 — Voucher / E-Store CTA
- Layout: split panel (teks + CTA)
- Konten: "VOUCHER TREATMENT DAN SKINCARE DIMANA SAJA" + CTA "Belanja di E-Store" → `/store`
- Notes: mirror section 9, layout konsisten.

#### Section 11 — Footer
- Layout: multi-kolom
- Elemen:
  - "Talk To Us" heading + link list: Liputan (`/liputan`), FAQ (`/faq`), Kontak Kami (`/contact`)
  - Social Media (ikon link, eksternal)
  - Official Marketplace (ikon link)
  - Copyright: "Copyright © 2026 PT.Zulu Alpha Papa All Right Reserved"
  - "back to top" link
- Notes: background gelap, teks terang. Improve: kolom rapi, spacing konsisten.

#### Section 12 — Floating Chat Widget
- Posisi: fixed bottom-right
- Label: "Talk To Us" / "Ask for a Question"
- Klik: buka panel Qiscus omnichannel (iframe) — daftar channel chat (WhatsApp, LINE, dll)
- Notes: widget pihak ketiga. Untuk replikasi, pakai komponen chat widget sendiri atau embed Qiscus.

### State transitions
- Scroll: header sticky, back-to-top muncul setelah scroll > viewport.
- Hero: auto-rotate slide tiap beberapa detik.
- Card hover: scale gambar tipis, border highlight.
- Chat widget: klik → expand panel, klik lagi / X → collapse.

### Catatan desain (improve modern)
- Shadow tipis, border `1px` subtle, radii `rounded-lg` konsisten.
- Density tinggi tapi napas, padding konsisten.
- Hierarki lewat spacing + bobot tipografi, bukan warna berlebih.
- State fokus ring `2px` tipis accent.
- Micro-interaction: transition halus pada hover/focus, feedback state lengkap.

---

## User Stories (Gherkin)

```gherkin
# Feature: Zapclinic Company Profile Landing Page

@happy
User Story:
  As calon klien
  I want melihat informasi klinik, treatment, dan promo dalam satu halaman
  So that bisa memilih treatment dan booking tanpa kebingungan

  Background:
    Given halaman landing company profile sudah dimuat di "/"

  Scenario: Pengunjung membuka landing page dan melihat hero banner
    When saya membuka halaman "/"
    Then saya melihat hero banner dengan judul "Happy Skin Happy Budget"
    And terdapat subteks "FACIAL . LASER . PRODUK 3 IN 1"
    And terdapat tombol "Cek Happy Skin Happy Budget" yang mengarah ke E-Store

  Scenario: Hero banner berotasi antar beberapa slide promo
    When hero banner ditampilkan
    Then slide pertama berisi promo "Happy Skin Happy Budget"
    And slide berikutnya berisi "NEW Treatment Z-Weightloss Program"
    And slide ketiga berisi "Bikin Kulit Happy Mood Ikut Happy Facial Bu Ayu"
    And setiap slide memiliki CTA (link internal atau WhatsApp)

  Scenario: Pengunjung melihat section "Kenapa Memilih ZAP"
    When saya scroll ke section "Kenapa Memilih ZAP?"
    Then saya melihat 6 kartu value proposition
    And kartu pertama berjudul "Berpengalaman" dengan deskripsi 15+ tahun
    And kartu terakhir berjudul "Dokter Bersertifikasi"

  Scenario: Pengunjung menelusuri daftar treatment pilihan
    When saya scroll ke section "Treatment Pilihan"
    Then saya melihat grid kartu treatment
    And setiap kartu menampilkan gambar, judul, deskripsi, dan category tag
    And beberapa kartu menampilkan badge "TERFAVORIT" atau "SAAT INI"
    And terdapat link "Lihat Semua" yang mengarah ke "/treatments"

  Scenario: Pengunjung membuka detail satu treatment
    Given saya berada di section "Treatment Pilihan"
    When saya klik kartu "Women Brazilian (VIO) Super Hair Removal"
    Then saya diarahkan ke "/treatment/brazilian-hair-removal-vio-area"

  Scenario: Pengunjung melihat section sub-brand ZAP
    When saya scroll ke section "ZAP BRANDS"
    Then saya melihat 3 kartu brand: Premiere Clinic, Menology, Juva by ZAP
    And setiap kartu memiliki link "More info" ke domain eksternal

  Scenario: Pengunjung membaca testimoni pelanggan
    When saya scroll ke section "APA SAJA KATA MEREKA TENTANG ZAP"
    Then saya melihat kartu testimoni berisi quote, nama, dan label "ngeZAP Sejak <tahun>"
    And salah satu testimoni bernama "Wika Cahyaningtyas" sejak 2024

  Scenario: Pengunjung mengklik CTA Online Booking
    When saya scroll ke section "ME TIME KINI JADI LEBIH PRAKTIS"
    And saya klik tombol "Booking Sekarang"
    Then saya diarahkan ke "/online-booking"

  Scenario: Pengunjung mengklik CTA E-Store
    When saya scroll ke section voucher E-Store
    And saya klik tombol "Belanja di E-Store"
    Then saya diarahkan ke "/store"

  Scenario: Pengunjung mengganti bahasa halaman
    When saya klik tombol "EN" di header
    Then halaman dimuat ulang dengan konten berbahasa Inggris
    And URL mengandung parameter lang EN

  Scenario: Pengunjung mengakses menu navigasi header
    When saya klik item menu "Lokasi" di header
    Then saya diarahkan ke "/locations"
    And header tetap terlihat (sticky) saat halaman dimuat

  Scenario: Pengunjung menekan tombol kembali ke atas
    Given saya telah scroll ke bawah halaman
    When saya klik tombol "back to top"
    Then halaman scroll kembali ke posisi paling atas

  Scenario: Pengunjung membuka widget chat
    When saya klik tombol "Talk To Us" mengambang di pojok kanan bawah
    Then panel chat terbuka menampilkan daftar channel messaging
    And panel menampilkan form "Ask for a Question"

  Scenario: Pengunjung melihat footer
    When saya scroll ke bagian paling bawah halaman
    Then saya melihat footer berisi link "Liputan", "FAQ", "Kontak Kami"
    And terdapat ikon Social Media dan Official Marketplace
    And terdapat teks copyright "Copyright © 2026 PT.Zulu Alpha Papa All Right Reserved"
```
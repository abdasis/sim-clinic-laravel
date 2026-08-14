# Research: Company Profile Web

**Feature**: 010-company-profile-web | **Date**: 2026-08-14

## R1. Model tenant company profile: tenant-scoped vs global public

**Decision**: Company profile **tenant-scoped public** diakses di route `/{tenant}`
(resolve tenant by slug, tanpa auth), bukan bare `/` global.

**Rationale**: Monorepo ini multi-tenant SaaS — tiap klinik punya profil
sendiri. Route `/` saat ini adalah starter template (bukan konten klinik).
Middleware `ResolveTenant` + `EnsureTenantActive` sudah ada untuk mengikat
request ke tenant via slug tanpa auth. Pola ini konsisten dengan route
login `/{tenant}/login` yang sudah publik (resolve tenant, no auth).
Frontend route: `apps/web/src/routes/$tenant/index.tsx` (landing publik).

**Alternatives considered**:
- Bare `/` global satu profil untuk semua tenant — ditolak: bertentangan
  dengan arsitektur multi-tenant; tiap klinik butuh profil berbeda.
- Subdomain per tenant (`klinikA.app.com`) — ditolak: butuh DNS/wildcard
  cert, out of scope; route-by-slug sudah jadi konvensi project.

## R2. Struktur data konten dinamis

**Decision**: Satu tabel CMS generik per tipe entitas (bukan EAV pola
key-value tunggal), dengan field `content`/`body` rich-text `jsonb` dan
field teks translatable sebagai `jsonb` locale-map `{"id": ...,"en": ...}`.

Entitas (lihat data-model.md): `company_profile_slides`, `company_value_props`,
`company_treatments`, `company_promos`, `company_brands`, `company_testimonials`,
`company_content_sections` (CMS generik untuk pharma/booking/e-store CTA),
`company_navigation_items`, `company_profile_settings`.

Semua tenant-scoped (`BelongsToTenant` + `TenantScope`), dengan kolom
`sort_order` (int), `is_active` (bool), `image_path` (nullable, disk public).

**Rationale**: Tiap entitas punya field berbeda (slide vs value prop vs
testimoni) — memaksa ke satu EAV menambah kompleksitas join. Tabel per
tipe = query sederhana, validasi per FormRequest jelas, Resource bersih.
Tabel prefix `company_*` memisahkan dari entitas clinic inti (services,
products) yang sudah ada. `jsonb` untuk rich-text + translatable cocok
PostgreSQL (project sudah pakai PG) dan menghindari tabel relasi
terjemahan yang berat.

**Alternatives considered**:
- EAV `content_blocks` tunggal (key-value) — ditolak: field berbeda antar
  tipe, validasi kabur, query kompleks.
- Satu tabel `content_sections` untuk SEMUA entitas — ditolak: kehilangan
  type-safety field spesifik; baik untuk section dengan layout khusus
  (pharma/CTA) saja, itulah `company_content_sections`.
- Tabel terjemahan relasional (`content_translations` FK) — ditolak:
  berat untuk CMS landing; jsonb locale-map cukup, fallback ID.

## R3. Rich-text editor: Tiptap

**Decision**: Tiptap (`@tiptap/react`, `@tiptap/starter-kit`,
`@tiptap/extension-image`, `@tiptap/extension-link`) di frontend.
Konten disimpan sebagai **JSON Tiptap** (`jsonb`) bukan HTML. Render
server-side/public via `@tiptap/static-renderer` (`renderToHTMLString`)
agar landing publik tidak butuh instance editor.

**Rationale**: User minta Tiptap eksplisit. Tiptap headless, cocok dengan
shadcn/Tailwind. JSON storage lebih aman (no XSS dari raw HTML), bisa
divalidasi struktur, dan dirender ulang konsisten. StarterKit = bold,
italic, heading, list. Image + Link untuk kebutuhan CMS konten landing.
Static renderer = landing publik baca JSON → HTML tanpa editor runtime.

**Alternatives considered**:
- Simpan HTML string — ditolak: raw HTML = risiko XSS, sanitasi manual,
  struktur tidak divalidasi.
- Editor lain (Quill/Draft) — ditolak: user minta Tiptap.

## R4. i18n konten dinamis

**Decision**: Field teks user-facing konten (judul, subteks, deskripsi
non-rich-text) disimpan sebagai `jsonb` locale-map `{"id": "...", "en": "..."}`.
ID = fallback default. Label UI sistem tetap via file bahasa `lang/id/*.php`
(mekanisme i18n existing). Language switcher baca query param `?lang=en`,
frontend pilih locale dari jsonb field.

**Rationale**: Konten CMS dinamis tidak bisa hardcode di file bahasa.
Locale-map jsonb = satu kolom per field, query sederhana, fallback mudah.
Pemisahan: label sistem (file bahasa) vs konten author (DB jsonb) jelas.

**Alternatives considered**:
- Tabel terjemahan relasional per field — ditolak: berat.
- Duplikasi record per locale (1 row ID + 1 row EN) — ditolak: data master
  duplikat, urutan/aktif sinkronisasi sulit.

## R5. Permission module konten

**Decision**: Tambah modul `content` ke `SyncTenantClinicRolesAction::MATRIX`:
`admin => 'rw'` saja (hanya admin klinik kelola company profile). Seeder
ikuti sinkronisasi otomatis. Policy `CompanyProfileContentPolicy` delegasi
Gate `{module}.view` / `{module}.manage` mengikuti pola existing
(`ServicePolicy`).

**Rationale**: Project sudah migrasi ke `spatie/laravel-permission` (konstitusi
VI). Tambah modul = extend MATRIX, bukan buat sistem baru. Hanya admin
kelola konten publik (tidak dokter/kasir).

**Alternatives considered**:
- Role `platform_admin` kelola lintas tenant — ditolak: konten adalah
  profil tiap klinik, admin klinik yang paham kontennya.
- Gate statik manual — ditolak: konstitusi VI mewajibkan spatie untuk
  role DB-driven; permission klinik sudah DB-driven.

## R6. Media storage

**Decision**: Gambar konten (slide, treatment, brand, avatar testimoni)
disimpan via disk `public` (sudah dikonfigurasi), pola upload mengikuti
`UploadMedicalPhotoAction`: `company-profile/{tenant_id}/{entity}/{file}`.
URL disajikan via `Storage::url()`.

**Rationale**: Pola upload + disk public sudah ada dan teruji. Tidak perlu
infra baru. Tenant_id di path untuk organisasi + isolasi.

**Alternatives considered**:
- S3/CDN — ditolak: out of scope v1; disk public cukup, upgrade path
  tinggal swap disk config.

## R7. Chat widget

**Decision**: v1 = komponen chat widget sederhana buatan sendiri (fixed
bottom-right, expand/collapse, tampilkan daftar channel link + tombol
 kontak). Konfigurasi channel (URL WhatsApp, LINE, dll) dikelola via
`company_profile_settings` (jsonb `chat_channels`). Tidak embed iframe
pihak ketiga (Qiscus) di v1.

**Rationale**: User story sebut replikasi pakai komponen sendiri atau
embed. Komponen sendiri = kontrol penuh, tidak ada dependency eksternal,
data channel dinamis dari CMS. Embed Qiscus bisa fase berikutnya jika
butuh omnichannel penuh.

**Alternatives considered**:
- Embed Qiscus iframe — ditolak v1: dependency runtime eksternal, rate/
  uptime di luar kendali, butuh akun pihak ketiga.

## R8. Navigasi & footer dinamis

**Decision**: `company_navigation_items` dengan `position` (header/footer),
`sort_order`, `label` (jsonb translatable), `url`, `link_type`
(anchor_section / route_internal / external). Header publik + footer
dirender dari tabel ini. Cart icon di header di-skip v1 (tidak ada
e-store checkout di scope landing) — link cart tetap jika `url` di-set.

**Rationale**: Navigasi harus dinamis per FR-011/FR-010. Satu tabel untuk
header + footer dengan flag position = DRY. `link_type` bedakan anchor
section (scroll di landing) vs route internal vs eksternal.

**Alternatives considered**:
- Hardcode navigasi frontend — ditolak: FR-011 minta dapat dikelola backend.

## R9. Back-to-top & hero carousel

**Decision**: Back-to-top = komponen React sederhana (IntersectionObserver/
scroll listener, show setelah >viewport, `scrollTo top`). Hero carousel =
`embla-carousel-react` (sudah terpasang di apps/web deps) dengan auto-rotate
+ dots/arrows manual.

**Rationale**: `embla-carousel-react` sudah ada dependency — tidak tambah
lib baru (YAGNI/ladder rung 4). Back-to-top native + 1 komponen kecil.

**Alternatives considered**:
- Lib carousel baru — ditolak: embla sudah ada.
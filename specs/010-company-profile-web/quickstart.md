# Quickstart: Company Profile Web

**Feature**: 010-company-profile-web | **Date**: 2026-08-14

Panduan validasi end-to-end. Tidak berisi kode implementasi penuh —
detail di `tasks.md`. Rujuk `data-model.md` untuk skema dan
`contracts/api-contracts.md` untuk endpoint.

## Prasyarat

- Docker db berjalan: `docker compose up -d db`
- Backend (apps/api) bermigrasi: `php artisan migrate`
- Seeder role/permission terbaru: `php artisan db:seed --class=RolesAndPermissionsSeeder`
  (tambah modul `content`).
- Admin klinik ter-login (role `admin`, punya `content.manage`).
- Frontend (apps/web) terpasang Tiptap deps: `cd apps/web && bun install`
  (tambah `@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-image`,
  `@tiptap/extension-link`, `@tiptap/static-renderer`).

## Skenario 1 — Landing publik termuat

1. Seed data contoh untuk satu tenant (slide, value props, treatment,
   testimoni, dll) dengan `is_active=true`, settings `is_published=true`.
2. Jalankan backend: `cd apps/api && php artisan serve` (port 8000).
3. Jalankan frontend: `cd apps/web && bun run dev` (port 3001).
4. Buka `http://localhost:3001/{tenant-slug}` (tanpa login).
5. **Validasi**: 12 section ter-render (header sticky, hero carousel
   auto-rotate, value props, treatments, promos, brands, testimoni,
   pharma banner, booking CTA, e-store CTA, footer, chat widget).
   Konten berasal dari backend (bukan hardcode).
6. Klik navigasi header (anchor section) → scroll ke section terkait.
7. Klik kartu treatment → ke `/{tenant}/treatment/{slug}`.
8. Scroll ke bawah → tombol back-to-top muncul → klik → scroll ke atas.
9. Klik chat widget → panel expand tampilkan channel.

## Skenario 2 — Admin CRUD konten dinamis

1. Login sebagai admin klinik di `/{tenant}/clinic`.
2. Buka menu sidebar "Company Profile" (modul baru, permission `content`).
3. **Create slide hero**: isi judul (ID+EN) via field teks, subteks,
   upload gambar (via media endpoint), CTA label + URL, save.
4. **Edit deskripsi promo** via Tiptap editor: bold/italic/heading/list/
   link/gambar → save. Verifikasi JSON Tiptap tersimpan.
5. **Toggle urutan** slide (reorder) → urutan berubah di landing publik.
6. **Nonaktifkan** satu value prop (`is_active=false`) → tidak tampil
   di landing publik.
7. Buka ulang `/{tenant-slug}` → perubahan langsung tampil tanpa deploy.
8. **Validasi**: setiap aksi create/update/delete/toggle mencatat audit
   log (cek `audit_logs`, deskripsi naratif).

## Skenario 3 — Ganti bahasa

1. Buka landing `/{tenant-slug}` (default ID).
2. Klik switcher "EN" di header.
3. **Validasi**: label UI + konten translatable tampil bahasa Inggris
   (dari jsonb locale-map, fallback ID bila EN kosong).
4. Klik "ID" → kembali ke Indonesia.

## Skenario 4 — Tenant nonaktif / belum publish

1. Set `company_profile_settings.is_published=false` via admin.
2. Buka landing → menampilkan empty state ramah (halaman tetap 200).
3. Set tenant `Inactive` (via platform admin) → landing 423.
4. Slug tidak dikenal → 404.

## Skenario 5 — API langsung

```bash
# Landing content (no auth)
curl http://localhost:8000/api/{tenant-slug}/profile

# Upload media (auth admin)
curl -X POST http://localhost:8000/api/{tenant-slug}/clinic/company-profile/media \
  -H "Authorization: Bearer {token}" \
  -F "file=@gambar.jpg" -F "entity=slides"

# Create slide (auth admin)
curl -X POST http://localhost:8000/api/{tenant-slug}/clinic/company-profile/slides \
  -H "Authorization: Bearer {token}" -H "Content-Type: application/json" \
  -d '{"title":{"id":"Promo A","en":"Promo A"},"sort_order":0,"is_active":true}'
```

## Test otomatis (backend)

- Feature test endpoint publik `GET /{tenant}/profile`: hanya entitas
  aktif, urut sort_order, `is_published=false` → empty state, tenant
  inactive → 423, slug unknown → 404.
- Feature test CMS CRUD per entitas: admin bisa create/update/delete/
  toggle/reorder; non-admin (doctor/cashier) → 403; validasi FormRequest.
- Feature test media upload: admin bisa upload, path tersimpan di disk
  public, non-admin → 403.
- Unit test audit log: setiap Action konten mencatat activity naratif
  dengan properties old/new.
- Isolasi tenant: konten tenant A tidak bocor ke tenant B (TenantScope).

Jalankan: `cd apps/api && php artisan test --filter=CompanyProfile`.
Sebelum rilis: `php artisan test -c phpunit.pgsql.xml` (constraint FK
RESTRICT hanya teruji di PostgreSQL).
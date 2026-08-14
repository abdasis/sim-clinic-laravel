# API Contracts: Company Profile Web

**Feature**: 010-company-profile-web | **Date**: 2026-08-14

Basis: konvensi existing — response `{ "data": ..., "meta": ... }`,
collection = Resource collection di `data`, pagination di `meta`.
Error: HTTP status + `{ message, errors }`. Validasi via FormRequest.
Authorisasi via Policy/Gate (spatie permission `content.view` / `content.manage`).

Dua grup route:

1. **Publik** (tenant-scoped, no auth): `/{tenant}/profile/*` — baca
   landing content, resolve tenant by slug, ensure active.
2. **Admin CMS** (tenant-scoped, auth + permission): `/{tenant}/clinic/company-profile/*`
   — CRUD entitas konten, upload media.

## Grup 1 — Publik (no auth)

Middleware: `resolve.tenant`, `ensure.tenant.active`, `permission.team`.
Response: gabungan seluruh konten landing dalam satu endpoint (orkestrasi
backend) untuk minimalkan round-trip SSR.

### GET /{tenant}/profile

Ambil seluruh konten landing page aktif dalam satu response.

**Response 200**:
```json
{
  "data": {
    "settings": { "site_name": {"id":"...","en":"..."}, "logo_path": "...", "copyright_text": "...", "chat_channels": [...], "social_links": [...], "marketplace_links": [...], "default_locale": "id", "is_published": true },
    "navigation": {
      "header": [ {"id":1,"label":{"id":"...","en":"..."},"url":"...","link_type":"route_internal","is_cta":false} ],
      "footer": [ ... ]
    },
    "slides": [ {"id":1,"title":{"id":"..."},"subtitle":{...},"image_path":"...","cta_label":{...},"cta_url":"...","cta_type":"route_internal"} ],
    "value_props": [ {"id":1,"icon":"...","title":{"id":"..."},"description":{...}} ],
    "treatments": [ {"id":1,"slug":"...","title":{"id":"..."},"description":{...},"image_path":"...","badge":"featured","category_tags":["..."]} ],
    "promos": [ {"id":1,"title":{"id":"..."},"description":{...},"image_path":"...","cta_label":{...},"cta_url":"...","cta_type":"external"} ],
    "brands": [ {"id":1,"name":{"id":"..."},"description":{...},"logo_path":"...","external_url":"..."} ],
    "testimonials": [ {"id":1,"quote":{...},"author_name":"...","since_year":2024,"avatar_path":"..."} ],
    "content_sections": { "pharma_banner": {…}, "booking_cta": {…}, "estore_cta": {…} }
  },
  "meta": { "locale": "id" }
}
```

Hanya entitas `is_active=true`, urut `sort_order`. `content_sections`
keyed by `section_key`. Rich-text field (`quote`, `body`, `description`
promo) = Tiptap JSON mentah; frontend render via `@tiptap/static-renderer`.

**Response 200 (tidak dipublikasi)**: `data.is_published=false` →
`{"data": {"is_published": false, ...minimal}, "meta": {...}}`. Landing
menampilkan empty state ramah.

**423**: tenant inactive (middleware `EnsureTenantActive`).
**404**: tenant slug tidak ditemukan (middleware `ResolveTenant`).

### GET /{tenant}/profile/treatments/{slug}

Detail satu featured treatment (jika halaman detail treatment dibutuhkan).
**Response 200**: `{"data": { ...fields treatment + optional body rich-text }, "meta": []}`.
**404**: slug tidak aktif/tidak ditemukan untuk tenant.

## Grup 2 — Admin CMS (auth + content.manage)

Middleware: `resolve.tenant`, `ensure.tenant.active`, `permission.team`,
`auth:santum`. Policy `CompanyProfileContentPolicy` (admin = CRUD).

Route prefix `/{tenant}/clinic/company-profile`. Bentuk DataTable
(server-side params `page, per_page, sort, direction, search, filter[column]`)
via `InteractsWithDataTable` untuk list.

### Endpoints per entitas

Pola seragam per entitas (slides, value-props, treatments, promos, brands,
testimonials, content-sections, navigation-items):

| Method | Path | Aksi |
|--------|------|------|
| GET | `/{entitas}` | list (DataTable) — `content.view` |
| POST | `/{entitas}` | create — `content.manage` |
| GET | `/{entitas}/{id}` | show — `content.view` |
| PATCH | `/{entitas}/{id}` | update — `content.manage` |
| DELETE | `/{entitas}/{id}` | delete — `content.manage` |
| PATCH | `/{entitas}/{id}/toggle` | toggle `is_active` — `content.manage` |
| PATCH | `/{entitas}/{id}/reorder` | ubah `sort_order` — `content.manage` |

Entitas path (kebab-case plural):
`slides`, `value-props`, `treatments`, `promos`, `brands`, `testimonials`,
`content-sections`, `navigation-items`.

### Settings (singleton per tenant)

| Method | Path | Aksi |
|--------|------|------|
| GET | `/settings` | show — `content.view` |
| PATCH | `/settings` | update — `content.manage` |
| PATCH | `/settings/publish` | toggle `is_published` — `content.manage` |

### Media upload

| Method | Path | Aksi |
|--------|------|------|
| POST | `/media` | upload gambar, return `path` + `url` — `content.manage` |

**Request**: `multipart/form-data`, field `file` (image), `entity`
opsional (untuh organisasi path `company-profile/{tenant_id}/{entity}/{file}`).
**Response 201**: `{"data": {"path":"...","url":"..."}, "meta": []}`.

### Request body contoh (create slide)

```json
{
  "title": {"id": "Happy Skin Happy Budget", "en": "Happy Skin Happy Budget"},
  "subtitle": {"id": "FACIAL . LASER . PRODUK 3 IN 1", "en": "..."},
  "image_path": "company-profile/1/slides/abc.jpg",
  "cta_label": {"id": "Cek Happy Skin Happy Budget", "en": "..."},
  "cta_url": "/store?category=...",
  "cta_type": "route_internal",
  "sort_order": 0,
  "is_active": true
}
```

Validasi FormRequest per entitas (lihat tasks.md): field wajib,
`jsonb` locale-map wajib beri key `id` minimal, `cta_type`/`link_type`
enum valid, `sort_order` int ≥ 0.

## Permission

Modul `content` ditambah ke `SyncTenantClinicRolesAction::MATRIX`:
`admin => 'rw'` (hanya admin). Permission: `content.view`, `content.manage`.
Policy delegasi Gate seperti `ServicePolicy`.
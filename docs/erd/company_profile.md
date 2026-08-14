# Tabel `company_*` — Company Profile

Skema terpasang untuk konten landing publik per tenant (spec 010,
`specs/010-company-profile-web/data-model.md`). Sembilan tabel, semuanya
tenant-scoped lewat `BelongsToTenant` + `TenantScope`.

Dokumen ini mencatat skema yang benar-benar dibuat migration; ERD turunan
UI ada di `zapclinic-company-profile.md`.

## Pola bersama

Kecuali disebut lain, tiap tabel punya:

| Field | Tipe | Catatan |
|-------|------|---------|
| id | bigint unsigned | PK |
| tenant_id | bigint unsigned | FK→tenants, cascade on delete |
| sort_order | int unsigned | urutan tampil, diatur admin |
| is_active | boolean | baris nonaktif tidak ikut dirender |
| created_at / updated_at | timestamp | |

Index `(tenant_id, is_active, sort_order)` ada di setiap tabel berurutan —
landing selalu membaca dengan tiga kolom itu sekaligus. Urutan final
`ORDER BY sort_order, id` supaya baris dengan `sort_order` sama tetap stabil.

### Field narasi = peta bahasa

Field teks yang dibaca pengunjung disimpan sebagai peta locale:

```json
{ "id": "Rawat kulitmu dengan tenang", "en": "Care for your skin, calmly" }
```

Satu baris melayani dua bahasa tanpa tabel terjemahan terpisah. **Peta ini
dikirim apa adanya ke frontend** (api-contracts.md) — frontend yang memilih
bahasanya lewat `pickTranslatable`, jadi mengganti bahasa tidak memicu
request baru. Fallback ke `id` bila versi yang diminta belum ditulis.

Field rich-text (`promos.description`, `testimonials.quote`,
`content_sections.body`) menyimpan **dokumen Tiptap JSON** di dalam peta
locale, bukan HTML — rendering lewat `@tiptap/static-renderer` ke elemen
React, bukan `dangerouslySetInnerHTML`.

ponytail: kolomnya `json`, bukan `jsonb` seperti di data-model.md. Ceiling:
query berdasarkan isi field terjemahan tidak bisa memakai index GIN.
Alasannya migration harus tetap jalan di SQLite saat test. Upgrade: ubah ke
`jsonb` lewat migration terpisah yang di-skip di SQLite, saat pencarian
konten dibutuhkan.

## `company_profile_settings`

Singleton per tenant — `unique(tenant_id)`. Tidak punya `sort_order`/`is_active`.

| Field | Tipe | Catatan |
|-------|------|---------|
| logo_path | string nullable | disk `public` |
| site_name | json (locale map) | |
| copyright_text | string nullable | |
| chat_channels | json | `[{type, url, label:{id,en}}]` |
| social_links | json | `[{platform, url, icon}]` |
| marketplace_links | json | `[{name, url, icon}]` |
| default_locale | string(5) | default `id` |
| is_published | boolean | default `false` |

`is_published=false` bukan sekadar penanda tampilan: `CompanyProfileService`
mengembalikan koleksi kosong, jadi draf admin tidak pernah bocor lewat API,
dan detail treatment ikut 404.

## `company_navigation_items`

| Field | Tipe | Catatan |
|-------|------|---------|
| label | json (locale map) | |
| url | string nullable | anchor `#id`, path internal, atau URL penuh |
| link_type | string enum | `anchor_section` \| `route_internal` \| `external` |
| position | string enum | `header` \| `footer` |
| is_cta | boolean | menu yang tampil sebagai tombol, mis. Online Booking |

Index `(tenant_id, position, is_active, sort_order)`.

## `company_profile_slides`

`title` (wajib), `subtitle`, `image_path`, `cta_label`, `cta_url`, `cta_type`.

## `company_value_props`

`icon` (nama ikon), `title`, `description` — keduanya teks pendek dua
bahasa, bukan rich-text.

## `company_treatments`

Etalase treatment. `unique(tenant_id, slug)`.

| Field | Tipe | Catatan |
|-------|------|---------|
| service_id | bigint unsigned nullable | FK→services, **nullOnDelete** |
| slug | string | dipakai di URL publik `/{tenant}/treatment/{slug}` |
| title, description | json (locale map) | description = teks pendek |
| badge | string enum nullable | `featured` \| `current` |
| category_tags | json nullable | array string |
| detail_url | string nullable | override tautan detail |

`service_id` sengaja `nullOnDelete`, bukan restrict: etalase publik boleh
menampilkan tindakan yang belum (atau tidak lagi) jadi master layanan, dan
menghapus layanan tidak boleh menjatuhkan halaman publik.

## `company_promos`

`title`, `description` (Tiptap per locale), `image_path`, `cta_label`,
`cta_url`, `cta_type`. Masa berlaku promo tidak disimpan — admin menyalakan
dan mematikan lewat `is_active`.

## `company_brands`

`name` dan `description` (locale map), `logo_path`, `external_url`.

## `company_testimonials`

`quote` (Tiptap per locale), `author_name` (string biasa), `since_year`
smallint nullable, `avatar_path` nullable.

## `company_content_sections`

Section bebas yang mengisi slot tetap di landing. `unique(tenant_id, section_key)`.

| Field | Tipe | Catatan |
|-------|------|---------|
| section_key | string(100) | `pharma_banner` \| `booking_cta` \| `estore_cta` |
| title | json (locale map) | |
| body | json (Tiptap per locale) | |
| image_path | string nullable | |
| cta_label, cta_url, cta_type | | |
| layout_type | string enum | `banner` \| `split`, default `split` |

Kuncinya tetap supaya frontend tahu di mana section dirender; isinya bebas
diatur admin.

## Endpoint publik

- `GET /api/{tenant}/profile` — seluruh landing sekaligus, `meta.locale`
  menandai bahasa yang diminta. Tanpa auth, tetap lewat `resolve.tenant` +
  `ensure.tenant.active`.
- `GET /api/{tenant}/profile/treatments/{slug}` — detail satu treatment;
  404 selama landing belum diterbitkan.

Resource mengirim `image_path` sesuai kontrak sekaligus `image_url` hasil
`Storage::url()`, supaya frontend tidak perlu tahu konvensi disk.

Permission modul `content` (admin) menjaga sisi CMS-nya.

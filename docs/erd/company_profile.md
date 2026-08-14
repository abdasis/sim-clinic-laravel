# Tabel `company_*` — Company Profile

Konten landing publik per tenant (spec 010). Sembilan tabel, semuanya
tenant-scoped lewat `BelongsToTenant` + `TenantScope`.

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
landing selalu membaca dengan tiga kolom itu sekaligus.

### Field narasi = peta bahasa

Field teks yang dibaca pengunjung disimpan sebagai peta locale:

```json
{ "id": "Rawat kulitmu dengan tenang", "en": "Care for your skin, calmly" }
```

Satu baris melayani dua bahasa tanpa tabel terjemahan terpisah. Backend
memilih bahasanya di Resource (`LocaleText::pick`) dan jatuh ke `id` bila
versi yang diminta belum ditulis.

Field rich-text (deskripsi, body, quote) menyimpan **dokumen Tiptap JSON**
di dalam peta locale, bukan HTML — rendering tetap lewat jalur React yang
aman, bukan `dangerouslySetInnerHTML`.

ponytail: kolomnya `json`, bukan `jsonb`. Ceiling: query berdasarkan isi
field terjemahan tidak bisa memakai index GIN. Alasannya migration ini
harus tetap jalan di SQLite saat test. Upgrade: ubah ke `jsonb` lewat
migration terpisah yang di-skip di SQLite, saat pencarian konten dibutuhkan.

## `company_profile_settings`

Singleton per tenant — `unique(tenant_id)`. Tidak punya `sort_order`.

| Field | Tipe | Catatan |
|-------|------|---------|
| is_published | boolean | saklar landing publik; `false` = isi tidak dibagikan sama sekali |
| brand_name, tagline, address | json (locale map) | |
| logo_path | string | path disk `public` |
| phone, whatsapp, email | string nullable | |
| map_embed_url | string(1000) nullable | |
| social_links | json | peta `{nama: url}` |
| meta_title, meta_description | json (locale map) | SEO |
| chat_widget_enabled | boolean | |
| chat_widget_number | string nullable | nomor tombol chat mengambang |

`is_published=false` bukan sekadar penanda tampilan: `CompanyProfileService`
mengembalikan koleksi kosong, jadi draf admin tidak pernah bocor lewat API.

## `company_navigation_items`

| Field | Tipe | Catatan |
|-------|------|---------|
| position | string enum | `header` \| `footer` |
| label | json (locale map) | |
| link_type | string enum | `anchor_section` \| `route_internal` \| `external` |
| link_value | string(1000) | anchor `#id`, path internal, atau URL penuh |

Index `(tenant_id, position, is_active, sort_order)`.

## `company_profile_slides`

Hero carousel. `image_path` wajib; `title`/`subtitle`/`cta_*` opsional.

## `company_value_props`

`icon` (nama ikon), `title` (locale map), `description` (Tiptap per locale).

## `company_treatments`

Etalase treatment. `unique(tenant_id, slug)`.

| Field | Tipe | Catatan |
|-------|------|---------|
| service_id | bigint unsigned nullable | FK→services, **nullOnDelete** |
| slug | string | dipakai di URL publik |
| name, excerpt | json (locale map) | |
| description | json (Tiptap per locale) | |
| badge | string enum nullable | `featured` \| `current` |
| price_label | string nullable | teks bebas, bukan angka — harga publik sering "Mulai 250rb" |

`service_id` sengaja `nullOnDelete`, bukan restrict: etalase publik boleh
menampilkan tindakan yang belum (atau tidak lagi) jadi master layanan, dan
mengarsipkan layanan tidak boleh menjatuhkan halaman publik.

## `company_promos`

Tambahan `starts_at` / `ends_at` (nullable). Scope `running()` memperlakukan
tanggal kosong sebagai tanpa batas — promo permanen lazim dipakai.

## `company_brands`

`name`, `logo_path` (wajib), `url` nullable. Nama brand tidak diterjemahkan.

## `company_testimonials`

`author_name` (string biasa), `author_role` (locale map), `quote` (Tiptap
per locale), `avatar_path` nullable, `rating` tinyint nullable 1–5.

## `company_content_sections`

Section bebas yang mengisi slot tetap di landing. `unique(tenant_id, section_key)`.

| Field | Tipe | Catatan |
|-------|------|---------|
| section_key | string enum | `pharma_banner` \| `booking_cta` \| `estore_cta` |
| layout | string enum | `banner` \| `split` |
| title, body, cta_label | json (locale map / Tiptap) | |
| cta_type | string enum nullable | `route_internal` \| `external` \| `whatsapp` |
| cta_value | string(1000) nullable | dibaca sesuai `cta_type` |

Kuncinya tetap supaya frontend tahu di mana section dirender; isinya bebas
diatur admin.

## Endpoint publik

- `GET /api/{tenant}/profile` — seluruh landing sekaligus. Tanpa auth,
  tetap lewat `resolve.tenant` + `ensure.tenant.active`.
- `GET /api/{tenant}/profile/treatments/{slug}` — detail satu treatment;
  404 selama landing belum diterbitkan.

Permission modul `content` (admin) menjaga sisi CMS-nya.

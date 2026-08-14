# Data Model: Company Profile Web

**Feature**: 010-company-profile-web | **Date**: 2026-08-14

Semua tabel tenant-scoped: `tenant_id` FK→tenants, pakai trait
`BelongsToTenant` + global scope `TenantScope` (auto-fill + auto-filter).
Kolom `content`/`body` rich-text = `jsonb` (Tiptap JSON). Field teks
user-facing translatable = `jsonb` locale-map `{"id": ..., "en": ...}`,
fallback ID. Media `image_path` = string path di disk `public`,
URL via `Storage::url()`.

## company_profile_settings

Pengaturan global profil per tenant (singleton logis: 1 baris per tenant).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, unique, cascade | 1:1 |
| logo_path | string(255) | nullable | disk public |
| site_name | jsonb | nullable | translatable `{"id","en"}` |
| copyright_text | string(255) | nullable | tahun bisa template `:year` |
| chat_channels | jsonb | nullable | `[{type:"whatsapp",url:"…",label:{…}}]` |
| social_links | jsonb | nullable | `[{platform, url, icon}]` |
| marketplace_links | jsonb | nullable | `[{name, url, icon}]` |
| default_locale | string(5) | default 'id' | |
| is_published | bool | default false | gating publikasi landing |
| created_at / updated_at | timestamp | | |

Validasi: `default_locale` ∈ {id, en}.

## company_navigation_items

Item menu header + footer, dinamis.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, cascade | |
| label | jsonb | not null | translatable |
| url | string(255) | nullable | null untuk anchor section |
| link_type | enum | not null | `anchor_section` / `route_internal` / `external` |
| position | enum | not null | `header` / `footer` |
| sort_order | int | default 0 | urutan tampil |
| is_active | bool | default true | |
| is_cta | bool | default false | penanda tombol emphasized (mis. Online Booking) |
| created_at / updated_at | timestamp | | |

Validasi: `link_type=external` → `url` wajib diisi. `position` + `sort_order`
+ `tenant_id` stabilisasi urutan via `ORDER BY sort_order, id`.

## company_profile_slides

Slide hero carousel.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, cascade | |
| title | jsonb | not null | translatable |
| subtitle | jsonb | nullable | translatable |
| image_path | string(255) | nullable | disk public |
| cta_label | jsonb | nullable | translatable |
| cta_url | string(255) | nullable | internal/eksternal |
| cta_type | enum | nullable | `route_internal` / `external` / `whatsapp` |
| sort_order | int | default 0 | |
| is_active | bool | default true | |
| created_at / updated_at | timestamp | | |

## company_value_props

Kartu "Kenapa Memilih".

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, cascade | |
| icon | string(100) | nullable | nama ikon hugeicons / key |
| title | jsonb | not null | translatable |
| description | jsonb | nullable | translatable (teks pendek) |
| sort_order | int | default 0 | |
| is_active | bool | default true | |
| created_at / updated_at | timestamp | | |

## company_treatments

Kartu treatment pilihan (featured). Mandiri dari `services` (entitas clinic
inti); opsional `service_id` link ke master bila treatment dari katalog.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, cascade | |
| service_id | bigint | nullable, FK→services, nullOnDelete | opsional link master |
| slug | string(255) | not null | URL `/treatment/{slug}` |
| title | jsonb | not null | translatable |
| description | jsonb | nullable | translatable rich-text? — teks pendek, lihat catatan |
| image_path | string(255) | nullable | disk public |
| badge | enum | nullable | `featured` / `current` (TERFAVORIT / SAAT INI) |
| category_tags | jsonb | nullable | `["Menghilangkan Bulu","rejuvenation"]` |
| detail_url | string(255) | nullable | override link detail |
| sort_order | int | default 0 | |
| is_active | bool | default true | |
| created_at / updated_at | timestamp | | |

Validasi: `slug` unik per tenant (`tenant_id` + `slug` unique index).
`badge` nullable. `category_tags` array string.

Catatan: `description` = teks pendek (jsonb translatable string), bukan
rich-text. Body detail treatment (jika butuh rich-text) di halaman detail
terpisah, out of scope v1 landing.

## company_promos

Kartu promo.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, cascade | |
| title | jsonb | not null | translatable |
| description | jsonb | nullable | rich-text Tiptap JSON |
| image_path | string(255) | nullable | disk public |
| cta_label | jsonb | nullable | translatable |
| cta_url | string(255) | nullable | |
| cta_type | enum | nullable | `route_internal` / `external` / `whatsapp` |
| sort_order | int | default 0 | |
| is_active | bool | default true | |
| created_at / updated_at | timestamp | | |

## company_brands

Kartu sub-brand.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, cascade | |
| name | jsonb | not null | translatable |
| description | jsonb | nullable | translatable |
| logo_path | string(255) | nullable | disk public |
| external_url | string(255) | nullable | link "More info" |
| sort_order | int | default 0 | |
| is_active | bool | default true | |
| created_at / updated_at | timestamp | | |

## company_testimonials

Kartu testimoni.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, cascade | |
| quote | jsonb | not null | translatable rich-text Tiptap JSON |
| author_name | string(255) | not null | |
| since_year | int | nullable | label "ngeZAP Sejak <tahun>" |
| avatar_path | string(255) | nullable | disk public |
| sort_order | int | default 0 | |
| is_active | bool | default true | |
| created_at / updated_at | timestamp | | |

## company_content_sections

CMS generik untuk section dengan layout khusus (pharma banner, online
booking CTA, voucher/e-store CTA). Identifikasi via `section_key`.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| tenant_id | bigint | FK→tenants, cascade | |
| section_key | string(100) | not null | `pharma_banner` / `booking_cta` / `estore_cta` |
| title | jsonb | nullable | translatable |
| body | jsonb | nullable | rich-text Tiptap JSON |
| image_path | string(255) | nullable | disk public |
| cta_label | jsonb | nullable | translatable |
| cta_url | string(255) | nullable | |
| cta_type | enum | nullable | `route_internal` / `external` / `whatsapp` |
| layout_type | enum | default `split` | `banner` / `split` |
| is_active | bool | default true | |
| created_at / updated_at | timestamp | | |

Validasi: `section_key` unik per tenant (`tenant_id` + `section_key`
unique index). Satu baris per section_key per tenant.

## Relationships

```text
Tenant 1───* company_profile_settings (1:1 logis)
Tenant 1───* company_navigation_items
Tenant 1───* company_profile_slides
Tenant 1───* company_value_props
Tenant 1───* company_treatments ──? Service (service_id, nullable)
Tenant 1───* company_promos
Tenant 1───* company_brands
Tenant 1───* company_testimonials
Tenant 1───* company_content_sections
```

Semua FK `cascadeOnDelete` kecuali `company_treatments.service_id`
`nullOnDelete` (hapus service tidak hapus featured card).

## State transitions

- `is_active` (semua entitas): `true` ↔ `false`, toggle via admin. Hanya
  `is_active=true` disajikan di landing publik.
- `is_published` (settings): `false` → `true`. Jika `false`, landing
  publik menampilkan empty state / placeholder ramah (FR: landing tetap
  dapat diakses).
- Urutan tampil: `sort_order` ascending, tie-break `id` ascending
  (stabilisasi, lihat edge case spec).

## Indexes

- `company_treatments`: unique(`tenant_id`, `slug`)
- `company_content_sections`: unique(`tenant_id`, `section_key`)
- `company_profile_settings`: unique(`tenant_id`)
- Semua tabel: index(`tenant_id`, `is_active`, `sort_order`) untuk query
  landing publik (WHERE tenant + active + ORDER BY sort_order).
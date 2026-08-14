## Ringkasan

Company profile landing page publik per tenant (route `/{tenant}`, no auth) + panel admin CMS kelola seluruh konten dinamis. Konten DB-driven (9 entitas `company_*`, tenant-scoped). Editor rich-text **Tiptap** untuk field narasi, disimpan JSON. i18n konten via jsonb locale-map `{id,en}`. Permission modul `content` (admin only) via spatie. Media via disk public. Carousel via `embla-carousel-react` (sudah terpasang).

Spec lengkap: `specs/010-company-profile-web/` (spec.md, plan.md, research.md, data-model.md, contracts/api-contracts.md, quickstart.md, tasks.md)

## Phase 1 — Setup

- [ ] T001 Tambah deps Tiptap ke `apps/web/package.json`: `@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-image`, `@tiptap/extension-link`, `@tiptap/static-renderer` → jalankan `bun install` sendiri
- [ ] T002 Tambah modul `content` ke `apps/api/app/Actions/Tenant/SyncTenantClinicRolesAction.php` MATRIX: `admin => ['content' => 'rw']`
- [ ] T003 Buat enum `apps/api/app/Enums/`: `CompanyCtaType.php` (route_internal|external|whatsapp), `CompanyLinkType.php` (anchor_section|route_internal|external), `CompanyNavPosition.php` (header|footer), `CompanyTreatmentBadge.php` (featured|current), `CompanySectionLayout.php` (banner|split), `CompanySectionKey.php` (pharma_banner|booking_cta|estore_cta)

## Phase 2 — Foundational (blocking)

- [ ] T005 Buat migration `apps/api/database/migrations/2026_08_14_100000_create_company_profile_tables.php` — 9 tabel per data-model.md, FK tenant_id cascade (service_id nullOnDelete), unique index (tenant_id+slug, tenant_id+section_key, tenant_id settings), composite index (tenant_id, is_active, sort_order)
- [ ] T006 Buat 9 model di `apps/api/app/Models/` (CompanyProfileSetting, CompanyNavigationItem, CompanyProfileSlide, CompanyValueProp, CompanyTreatment, CompanyPromo, CompanyBrand, CompanyTestimonial, CompanyContentSection) — trait BelongsToTenant, `#[ScopedBy([TenantScope])]`, `$fillable`, casts jsonb→array, enum casts, relasi (CompanyTreatment belongsTo Service nullable)
- [ ] T007 Tambah group `company_profile` ke `apps/api/lang/id/company_profile.php` + `apps/api/lang/en/company_profile.php` (label UI: title section, button, empty state, validation) + daftarkan ke `TranslationController::GROUPS` di `apps/api/app/Http/Controllers/TranslationController.php`

## Phase 3 — User Story 1: Landing Publik (P1, MVP)

**Goal**: Pengunjung lihat landing page publik `/{tenant}` dengan 12 section konten dinamis dari backend (no auth).

### Backend — public read endpoint

- [ ] T010 Buat Resource per entitas + `CompanyProfileLandingResource` di `apps/api/app/Http/Resources/CompanyProfile/` — transform jsonb translatable (pilih locale), path→`Storage::url()`
- [ ] T011 Buat `apps/api/app/Services/CompanyProfileService.php` — method `landingData(Tenant, locale)` orkestrasi query semua entitas aktif urut sort_order, gabung ke struktur `{settings, navigation{header,footer}, slides, value_props, treatments, promos, brands, testimonials, content_sections{keyed}}`; method `treatmentDetail(slug)`
- [ ] T012 Buat `apps/api/app/Http/Controllers/CompanyProfileController.php` — `index` resolve `app('tenant')` + locale param, return LandingResource; `showTreatment(slug)`
- [ ] T013 Daftarkan route publik `/{tenant}/profile` + `/{tenant}/profile/treatments/{slug}` di `apps/api/routes/api.php` — middleware `resolve.tenant` + `ensure.tenant.active` + `permission.team`, NO auth

### Frontend — landing page publik

- [ ] T014 Buat hook `apps/web/src/hooks/use-company-profile.ts` — React Query fetch `GET /{tenant}/profile`, key `['company-profile', tenant]`
- [ ] T015 Buat helper `apps/web/src/lib/company-locale.ts` — baca locale (query param `?lang` / default), fungsi `pickTranslatable(jsonb, locale)` fallback id
- [ ] T016 Buat 12 section partial di `apps/web/src/components/company-profile/`: `hero-carousel.tsx` (embla auto-rotate+dots/arrows), `value-props-section.tsx`, `treatment-grid.tsx`, `promo-section.tsx`, `brand-section.tsx`, `testimonial-section.tsx`, `content-banner.tsx` (pharma), `booking-cta.tsx`, `estore-cta.tsx`, `back-to-top.tsx`, `chat-widget.tsx`, `company-header.tsx` (sticky nav dinamis) — masing-masing ≤300 baris, render rich-text via `@tiptap/static-renderer` `renderToHTMLString`, gaya Linear
- [ ] T017 Buat route landing `apps/web/src/routes/$tenant/index.tsx` — orkestrasi `use-company-profile`, render 12 section partial, empty state ramah saat `is_published=false`, render header+footer dinamis dari navigation
- [ ] T018 Buat route detail `apps/web/src/routes/$tenant/treatment/$slug.tsx` — fetch detail treatment, render
- [ ] T019 Regenerasi route tree: `cd apps/web && bun run generate-routes` (jalankan sendiri)

## Phase 4 — User Story 2: Admin CMS CRUD + Tiptap (P2)

**Goal**: Admin kelola seluruh konten landing via panel admin, Tiptap editor untuk field narasi, perubahan langsung tampil di publik.

### Backend — CMS admin

- [ ] T023 Buat FormRequest di `apps/api/app/Http/Requests/CompanyProfile/`: `SlideRequest`, `ValuePropRequest`, `TreatmentRequest` (slug unique per tenant), `PromoRequest`, `BrandRequest`, `TestimonialRequest`, `ContentSectionRequest`, `NavigationItemRequest`, `SettingsRequest`, `MediaUploadRequest`, `ReorderRequest` — validasi jsonb locale-map wajib key `id`, enum, `cta_type`/`link_type` conditional url
- [ ] T024 Buat Action per entitas di `apps/api/app/Actions/CompanyProfile/` — Create/Update/Delete/ToggleActive per entitas (Slide, ValueProp, Treatment, Promo, Brand, Testimonial, ContentSection, NavigationItem) + `ReorderAction` generic + `UploadCompanyMediaAction` (disk public, path `company-profile/{tenant_id}/{entity}/{file}`) — tiap Action log audit via `LogAuditAction` naratif + properties old/new
- [ ] T025 Extend `apps/api/app/Services/CompanyProfileService.php` — method CRUD per entitas orkestrasi Action + `DB::transaction` boundary; method `uploadMedia`, `updateSettings`, `togglePublish`, `reorder`
- [ ] T026 Buat `apps/api/app/Policies/CompanyProfileContentPolicy.php` — delegasi Gate `content.view`/`content.manage` per pola `ServicePolicy`
- [ ] T027 Buat `apps/api/app/Http/Controllers/CompanyContentController.php` — DataTable list (`InteractsWithDataTable`) + CRUD + toggle + reorder per entitas; settings singleton show/update/publish; media upload; authorize via Policy
- [ ] T028 Daftarkan route CMS `/{tenant}/clinic/company-profile/*` di `apps/api/routes/api.php` — middleware `resolve.tenant` + `ensure.tenant.active` + `permission.team` + `auth:sanctum`, apiResource per entitas + toggle/reorder/settings/media

### Frontend — CMS admin + Tiptap

- [ ] T029 Buat editor Tiptap reusable `apps/web/src/components/ui/tiptap-editor.tsx` — `useEditor` StarterKit + Image + Link, toolbar (bold/italic/heading/list/link/image), `getJSON`/`setValue`, ≤300 baris, gaya Linear
- [ ] T030 Buat helper `apps/web/src/hooks/use-translatable-field.ts` — input field jsonb locale-map (ID+EN tabs), sinkron ke form
- [ ] T031 Buat partials CMS per entitas di `apps/web/src/routes/$tenant/clinic/company-profile/components/` — list (DataTable) + form (>5 field → halaman terpisah) per entitas: slides, value-props, treatments, promos, brands, testimonials, content-sections, navigation-items, settings — tiap form pakai TiptapEditor untuk field narasi + `useTranslatableField`, breadcrumb WAJIB
- [ ] T032 Buat route shell CMS `apps/web/src/routes/$tenant/clinic/company-profile/route.tsx` + `index.tsx` (overview/dashboard) — tambah item sidebar "Company Profile" ke `apps/web/src/routes/$tenant/clinic/route.tsx` (roles admin), breadcrumb root→Company Profile
- [ ] T033 Buat route per entitas `apps/web/src/routes/$tenant/clinic/company-profile/{slides,value-props,treatments,promos,brands,testimonials,content-sections,navigation-items,settings}/` — index + form page, gunakan partials T031
- [ ] T034 Regenerasi route tree: `cd apps/web && bun run generate-routes` (jalankan sendiri)

## Phase 5 — User Story 3: Language Switcher ID/EN (P3)

**Goal**: Pengunjung ganti bahasa halaman ID/EN via switcher header.

- [ ] T036 Implement language switcher di `apps/web/src/components/company-profile/company-header.tsx` — toggle EN|ID, set query param `?lang`, update hook company-locale, refetch/reaktif
- [ ] T037 Hubungkan locale switcher ke `useTrans` (label UI sistem) + `pickTranslatable` (konten jsonb) — pastikan keduanya reaktif ke perubahan locale

## Phase 6 — Polish

- [ ] T038 Buat seeder `apps/api/database/seeders/CompanyProfileDemoSeeder.php` — data contoh lengkap per tenant (slide, value props, treatment, promo, brand, testimoni, content sections, navigation, settings `is_published=true`)
- [ ] T039 Audit tipografi/spacing/radii/border konsisten gaya Linear di semua section landing + CMS (finishing anti-slop)
- [ ] T040 Tooltip + shortcut pada aksi non-eviden CMS (tombol ikon toggle/reorder/delete)
- [ ] T041 Jalankan `php artisan test --filter=CompanyProfile` (sqlite) — semua hijau (jalankan sendiri)
- [ ] T042 Jalankan `php artisan test -c phpunit.pgsql.xml --filter=CompanyProfile` sebelum rilis (jalankan sendiri)
- [ ] T043 Jalankan `cd apps/web && bun run test` — vitest hijau (jalankan sendiri)
- [ ] T044 Jalankan validasi `quickstart.md` — 5 skenario end-to-end (manual)
- [ ] T045 Update docs: `docs/erd/` tambah ERD tabel company_* + `docs/` normalization per tabel

## Dependencies

- **Phase 2** blocks semua user story (migration + models + i18n wajib selesai dulu)
- **US1**: after Phase 2 — backend read API → frontend landing
- **US2**: after Phase 2 — reuse models Phase 2; CMS independent dari US1 frontend
- **US3**: after US1 frontend (header + locale helper T016/T015)
- **Polish**: after all stories

## MVP scope

Phase 1 + Phase 2 + US1 saja = landing publik termuat konten dinamis.

## Catatan

- PHP class ≤300 baris, method ≤100; React component ≤300 — extract partials
- Audit log naratif + properties old/new tiap Action (konstitusi VI)
- TenantScope isolation WAJIB terjaga (konstitusi III)
- UI text via i18n (`lang/id` + `lang/en` `company_profile.php`), identifier English
- Breadcrumb WAJIB tiap halaman CMS dalam
- Command build/format/dev JANGAN auto-run — jalankan sendiri (T001, T019, T034, T041–T043)
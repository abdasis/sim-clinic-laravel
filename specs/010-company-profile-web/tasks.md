# Tasks: Company Profile Web

**Input**: Design documents from `specs/010-company-profile-web/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-contracts.md, quickstart.md

**Tests**: TDD WAJIB (Constitution II) — test task ditulis lebih dulu, konfirmasi FAIL sebelum implementasi.

**Organization**: Tasks grouped by user story (US1 P1, US2 P2, US3 P3).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story this task belongs to (US1/US2/US3)
- Exact file paths in descriptions

## Path Conventions

- Backend: `apps/api/`
- Frontend: `apps/web/`
- Monorepo root: `.`

## Delegasi Authoring

- BE task → `ammar` (backend authoring)
- FE task → `sierly` (frontend authoring, langsung tanpa eksplorasi)
- Push BE → `haikal` review (`/code-review` low)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Dependency + permission + migration foundation

- [ ] T001 Tambah deps Tiptap ke apps/web/package.json: `@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-image`, `@tiptap/extension-link`, `@tiptap/static-renderer` — jalankan `cd apps/web && bun install` sendiri
- [ ] T002 [P] Tambah modul `content` ke `apps/api/app/Actions/Tenant/SyncTenantClinicRolesAction.php` MATRIX: `admin => ['content' => 'rw']` (BE)
- [ ] T003 [P] Buat enum `apps/api/app/Enums/CompanyCtaType.php` (route_internal|external|whatsapp), `CompanyLinkType.php` (anchor_section|route_internal|external), `CompanyNavPosition.php` (header|footer), `CompanyTreatmentBadge.php` (featured|current), `CompanySectionLayout.php` (banner|split), `CompanySectionKey.php` (pharma_banner|booking_cta|estore_cta) (BE)

**Checkpoint**: deps + enum + permission matrix siap

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migration + models + i18n group — MUST complete before any user story

**⚠️ CRITICAL**: No user story work until this phase complete

### Tests (TDD — write first, FAIL)

- [ ] T004 [P] Feature test TenantScope isolation untuk model company: data tenant A tidak bocor ke tenant B di `apps/api/tests/Feature/CompanyProfileTenantIsolationTest.php` (BE)

### Migration + Models

- [ ] T005 Buat migration `apps/api/database/migrations/2026_08_14_100000_create_company_profile_tables.php` — 9 tabel per data-model.md, FK tenant_id cascade (service_id nullOnDelete), unique index (tenant_id+slug, tenant_id+section_key, tenant_id settings), composite index (tenant_id, is_active, sort_order) (BE)
- [ ] T006 [P] Buat 9 model di `apps/api/app/Models/` (CompanyProfileSetting, CompanyNavigationItem, CompanyProfileSlide, CompanyValueProp, CompanyTreatment, CompanyPromo, CompanyBrand, CompanyTestimonial, CompanyContentSection) — trait BelongsToTenant, #[ScopedBy([TenantScope])], $fillable, casts jsonb→array, enum casts, relasi (CompanyTreatment belongsTo Service nullable) (BE)
- [ ] T007 [P] Tambah group `company_profile` ke file bahasa `apps/api/lang/id/company_profile.php` + `apps/api/lang/en/company_profile.php` (label UI: title section, button, empty state, validation message) + daftarkan ke `TranslationController::GROUPS` di `apps/api/app/Http/Controllers/TranslationController.php` (BE)

**Checkpoint**: Foundation ready — migration jalan, model ter-scope, i18n group terdaftar

---

## Phase 3: User Story 1 — Landing Publik (Priority: P1) MVP

**Goal**: Pengunjung lihat landing page publik `/{tenant}` dengan 12 section konten dinamis dari backend (no auth)

**Independent Test**: Buka `/{tenant-slug}` tanpa login → 12 section ter-render konten dinamis, navigasi/CTA/back-to-top/chat widget berfungsi. API `GET /{tenant}/profile` return hanya entitas aktif urut sort_order.

### Tests (TDD — write first, FAIL)

- [ ] T008 [P] [US1] Feature test `GET /{tenant}/profile` di `apps/api/tests/Feature/CompanyProfileLandingTest.php`: 200 + struktur 9 grup; hanya is_active=true; urut sort_order; is_published=false → empty state; tenant inactive → 423; slug unknown → 404 (BE)
- [ ] T009 [P] [US1] Feature test `GET /{tenant}/profile/treatments/{slug}` di `apps/api/tests/Feature/CompanyTreatmentDetailTest.php`: 200 aktif; 404 slug tidak ada/non-aktif (BE)

### Backend — Public read endpoint

- [ ] T010 [US1] Buat Resource per entitas + `CompanyProfileLandingResource` di `apps/api/app/Http/Resources/CompanyProfile/` — transform jsonb translatable (pilih locale), path→Storage::url() (BE)
- [ ] T011 [US1] Buat `apps/api/app/Services/CompanyProfileService.php` — method `landingData(Tenant, locale)` orkestrasi query semua entitas aktif urut sort_order, gabung ke struktur {settings, navigation{header,footer}, slides, value_props, treatments, promos, brands, testimonials, content_sections{keyed}}; method `treatmentDetail(slug)` (BE, no DB direct — via Action/read query boleh per CLAUDE.md read exception)
- [ ] T012 [US1] Buat `apps/api/app/Http/Controllers/CompanyProfileController.php` — `index` resolve app('tenant') + locale param, authorize skip (publik), return LandingResource; `showTreatment(slug)` (BE)
- [ ] T013 [US1] Daftarkan route publik `/{tenant}/profile` + `/{tenant}/profile/treatments/{slug}` di `apps/api/routes/api.php` — middleware resolve.tenant + ensure.tenant.active + permission.team, NO auth (BE)

**Checkpoint BE US1**: API landing jalan, test hijau

### Frontend — Landing page publik

- [ ] T014 [P] [US1] Buat hook `apps/web/src/hooks/use-company-profile.ts` — React Query fetch `GET /{tenant}/profile`, key `['company-profile', tenant]` (FE)
- [ ] T015 [P] [US1] Buat helper `apps/web/src/lib/company-locale.ts` — baca locale (query param ?lang / default), fungsi pickTranslatable(jsonb, locale) fallback id (FE)
- [ ] T016 [P] [US1] Buat 12 section partial di `apps/web/src/components/company-profile/`: hero-carousel.tsx (embla auto-rotate+dots/arrows), value-props-section.tsx, treatment-grid.tsx, promo-section.tsx, brand-section.tsx, testimonial-section.tsx, content-banner.tsx (pharma), booking-cta.tsx, estore-cta.tsx, back-to-top.tsx, chat-widget.tsx, company-header.tsx (sticky nav dinamis) — masing-masing ≤300 baris, render rich-text via @tiptap/static-renderer renderToHTMLString, gaya Linear per CLAUDE.md (FE)
- [ ] T017 [US1] Buat route landing `apps/web/src/routes/$tenant/index.tsx` — orkestrasi use-company-profile, render 12 section partial, empty state ramah saat is_published=false, render header+footer dinamis dari navigation (FE)
- [ ] T018 [US1] Buat route detail `apps/web/src/routes/$tenant/treatment/$slug.tsx` — fetch detail treatment, render (FE)
- [ ] T019 [US1] Regenerasi route tree: `cd apps/web && bun run generate-routes` (jalankan sendiri)

**Checkpoint**: US1 fully functional — landing publik termuat konten dinamis, test independen lulus

---

## Phase 4: User Story 2 — Admin CMS CRUD + Tiptap (Priority: P2)

**Goal**: Admin kelola seluruh konten landing via panel admin, Tiptap editor untuk field narasi, perubahan langsung tampil di publik

**Independent Test**: Login admin → CRUD satu slide/testimoni via Tiptap → perubahan muncul di `/{tenant}`. Audit log tercatat naratif.

### Tests (TDD — write first, FAIL)

- [ ] T020 [P] [US2] Feature test CMS CRUD per entitas di `apps/api/tests/Feature/CompanyContentCrudTest.php`: admin create/update/delete/toggle/reorder 200; non-admin (doctor/cashier) 403; validasi FormRequest (jsonb wajib key id, enum valid, sort_order int≥0); audit log tercatat properties old/new (BE)
- [ ] T021 [P] [US2] Feature test media upload di `apps/api/tests/Feature/CompanyMediaUploadTest.php`: admin 201 + path di disk public; non-admin 403; file non-image 422 (BE)
- [ ] T022 [P] [US2] Feature test settings singleton + publish toggle di `apps/api/tests/Feature/CompanySettingsTest.php`: admin update settings; toggle is_published; audit log (BE)

### Backend — CMS admin

- [ ] T023 [P] [US2] Buat FormRequest di `apps/api/app/Http/Requests/CompanyProfile/`: SlideRequest, ValuePropRequest, TreatmentRequest (slug unique per tenant), PromoRequest, BrandRequest, TestimonialRequest, ContentSectionRequest, NavigationItemRequest, SettingsRequest, MediaUploadRequest, ReorderRequest — validasi jsonb locale-map wajib id, enum, cta_type/link_type conditional url (BE)
- [ ] T024 [P] [US2] Buat Action per entitas di `apps/api/app/Actions/CompanyProfile/` — Create/Update/Delete/ToggleActive per entitas (Slide, ValueProp, Treatment, Promo, Brand, Testimonial, ContentSection, NavigationItem) + ReorderAction generic + UploadCompanyMediaAction (disk public, path company-profile/{tenant_id}/{entity}/{file}) — tiap Action log audit via LogAuditAction naratif + properties old/new (BE)
- [ ] T025 [US2] Extend `apps/api/app/Services/CompanyProfileService.php` — method CRUD per entitas orkestrasi Action + DB::transaction boundary; method uploadMedia, updateSettings, togglePublish, reorder (BE)
- [ ] T026 [US2] Buat `apps/api/app/Policies/CompanyProfileContentPolicy.php` — delegasi Gate content.view/content.manage per pola ServicePolicy (BE)
- [ ] T027 [US2] Buat `apps/api/app/Http/Controllers/CompanyContentController.php` — DataTable list (InteractsWithDataTable) + CRUD + toggle + reorder per entitas; settings singleton show/update/publish; media upload; authorize via Policy (BE)
- [ ] T028 [US2] Daftarkan route CMS `/{tenant}/clinic/company-profile/*` di `apps/api/routes/api.php` — middleware resolve.tenant + ensure.tenant.active + permission.team + auth:sanctum, apiResource per entitas + toggle/reorder/settings/media (BE)

**Checkpoint BE US2**: API CMS CRUD jalan, test hijau, audit log naratif

### Frontend — CMS admin + Tiptap

- [ ] T029 [P] [US2] Buat editor Tiptap reusable `apps/web/src/components/ui/tiptap-editor.tsx` — useEditor StarterKit + Image + Link, toolbar (bold/italic/heading/list/link/image), getJSON/setValue, ≤300 baris, gaya Linear (FE)
- [ ] T030 [P] [US2] Buat helper `apps/web/src/hooks/use-translatable-field.ts` — input field jsonb locale-map (ID+EN tabs), sinkron ke form (FE)
- [ ] T031 [P] [US2] Buat partials CMS per entitas di `apps/web/src/routes/$tenant/clinic/company-profile/components/` — list (DataTable) + form dialog/halaman (>5 field → halaman terpisah per CLAUDE.md) per entitas: slides, value-props, treatments, promos, brands, testimonials, content-sections, navigation-items, settings — tiap form pakai TiptapEditor untuk field narasi + useTranslatableField, breadcrumb WAJIB (FE)
- [ ] T032 [US2] Buat route shell CMS `apps/web/src/routes/$tenant/clinic/company-profile/route.tsx` + `index.tsx` (overview/dashboard) — tambah item sidebar "Company Profile" ke `apps/web/src/routes/$tenant/clinic/route.tsx` (roles admin), breadcrumb root→Company Profile (FE)
- [ ] T033 [US2] Buat route per entitas `apps/web/src/routes/$tenant/clinic/company-profile/{slides,value-props,treatments,promos,brands,testimonials,content-sections,navigation-items,settings}/` — index + form page, gunakan partials T031 (FE)
- [ ] T034 [US2] Regenerasi route tree: `cd apps/web && bun run generate-routes` (jalankan sendiri)

**Checkpoint**: US2 functional — admin CRUD konten + Tiptap, perubahan tampil di publik, audit log tercatat

---

## Phase 5: User Story 3 — Language Switcher ID/EN (Priority: P3)

**Goal**: Pengunjung ganti bahasa halaman ID/EN via switcher header

**Independent Test**: Buka `/{tenant}` → klik EN → label UI + konten translatable tampil Inggris → klik ID kembali.

### Tests (TDD — write first, FAIL)

- [ ] T035 [P] [US3] Frontend test language switcher di `apps/web/src/routes/$tenant/__tests__/locale-switch.test.tsx` (vitest): klik EN → locale=en, konten jsonb en tampil, fallback id bila en kosong; klik ID kembali (FE)

### Implementation

- [ ] T036 [US3] Implement language switcher di `apps/web/src/components/company-profile/company-header.tsx` — toggle EN|ID, set query param ?lang, update hook company-locale, refetch/reaktif (FE)
- [ ] T037 [US3] Hubungkan locale switcher ke useTrans (label UI sistem) + pickTranslatable (konten jsonb) — pastikan keduanya reaktif ke perubahan locale (FE)

**Checkpoint**: US3 functional — switcher bahasa bekerja, semua user story independen lulus

---

## Phase 6: Polish & Cross-Cutting

**Purpose**: Improvements across stories

- [ ] T038 [P] Tambah seeder `apps/api/database/seeders/CompanyProfileDemoSeeder.php` — data contoh lengkap per tenant (slide, value props, treatment, promo, brand, testimoni, content sections, navigation, settings is_published=true) untuk demo + quickstart (BE)
- [ ] T039 [P] Audit tipografi/spacing/radii/border konsisten gaya Linear di semua section landing + CMS — per CLAUDE.md finishing anti-slop (FE)
- [ ] T040 [P] Tooltip + shortcut pada aksi non-eviden CMS (tombol ikon toggle/reorder/delete) per CLAUDE.md (FE)
- [ ] T041 Jalankan `php artisan test --filter=CompanyProfile` (sqlite) — semua hijau (BE, jalankan sendiri)
- [ ] T042 Jalankan `php artisan test -c phpunit.pgsql.xml --filter=CompanyProfile` sebelum rilis — constraint FK RESTRICT teruji (BE, jalankan sendiri)
- [ ] T043 Jalankan `cd apps/web && bun run test` — vitest hijau (FE, jalankan sendiri)
- [ ] T044 Jalankan validasi quickstart.md — 5 skenario end-to-end (manual)
- [ ] T045 [P] Update docs: `docs/erd/` tambah ERD tabel company_* + `docs/` normalization per tabel (BE)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No deps — start immediately
- **Foundational (Phase 2)**: Depends T001–T003 — BLOCKS all user stories
- **US1 (Phase 3)**: Depends Phase 2 (migration+models+i18n) — backend read API → frontend landing
- **US2 (Phase 4)**: Depends Phase 2; shares models dengan US1 (sudah ada). CMS independent dari US1 frontend
- **US3 (Phase 5)**: Depends US1 frontend (header + locale helper sudah ada di T016/T015)
- **Polish (Phase 6)**: Depends semua story complete

### User Story Dependencies

- **US1 (P1)**: After Foundational — no dep on other stories. MVP.
- **US2 (P2)**: After Foundational — independent testable (backend CMS test tidak perlu US1 frontend). Reuse models Phase 2.
- **US3 (P3)**: After US1 frontend (header + locale infra). Build on US1.

### Within Each User Story

- Test FIRST → konfirmasi FAIL → implementasi → test hijau (TDD Constitution II)
- Models (Phase 2 shared) → Service → Controller → Route (BE)
- Hook → helper → partials → route (FE)
- Commit after each task / logical group

### Parallel Opportunities

- T002, T003 paralel (Setup)
- T006 semua model paralel; T007 i18n paralel (Foundational)
- T008, T009 test US1 paralel; T016 semua 12 section partial paralel (US1)
- T020, T021, T022 test US2 paralel; T023 semua FormRequest paralel; T024 semua Action paralel; T031 semua partial CMS paralel (US2)
- T038 seeder, T039 polish, T040 tooltip, T045 docs paralel (Polish)

---

## Parallel Example: User Story 1

```bash
# Backend US1 (sequential within BE): Resource → Service → Controller → Route
# Frontend US1 (parallel set):
Task: "use-company-profile.ts"           # T014
Task: "company-locale.ts helper"         # T015
Task: "12 section partials"              # T016 (parallel per file)
# Then: route landing T017 (depends T014-T016), detail T018
```

## Parallel Example: User Story 2

```bash
# Backend US2 (parallel batches):
Task: "11 FormRequest files"             # T023 parallel
Task: "Action per entitas"               # T024 parallel
# Then: Service T025 → Policy T026 → Controller T027 → Route T028 (sequential)
# Frontend US2 (parallel):
Task: "tiptap-editor.tsx"                # T029
Task: "use-translatable-field.ts"        # T030
Task: "partials CMS per entitas"         # T031 parallel
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup (Tiptap deps, permission matrix, enum)
2. Phase 2: Foundational (migration, models, i18n) — CRITICAL blocks all
3. Phase 3: US1 — backend landing API + frontend landing page
4. **STOP VALIDATE**: `/{tenant-slug}` termuat 12 section dinamis, test lulus
5. Deploy/demo

### Incremental Delivery

1. Setup + Foundational → foundation ready
2. + US1 → landing publik MVP → demo
3. + US2 → CMS admin + Tiptap → demo
4. + US3 → language switcher → demo
5. Polish → rilis (PostgreSQL test WAJIB sebelum rilis)

### Parallel Team Strategy

- BE (ammar): Phase 2 → US1 backend → US2 backend → seeder/docs
- FE (sierly): Tiptap deps → US1 frontend → US2 frontend → US3 → polish
- Setelah US1 BE jalan, US1 FE bisa mulai paralel dengan US2 BE

---

## Notes

- TDD WAJIB (Constitution II): test FAIL sebelum implementasi, hijau sebelum lanjut
- BE authoring → ammar; FE authoring → sierly; push BE → haikal `/code-review` low
- Command build/format/dev JANGAN auto-run — user jalankan sendiri (T001, T019, T034, T041–T043)
- PHP class ≤300 baris, method ≤100; React component ≤300 — extract partials
- Audit log naratif + properties old/new tiap Action (Constitution VI)
- TenantScope isolation WAJIB teruji (Constitution III)
- UI text via i18n (lang/id + lang/en company_profile.php), identifier English
- Breadcrumb WAJIB tiap halaman CMS dalam
- [P] = different files, no deps; [Story] = traceability ke spec.md
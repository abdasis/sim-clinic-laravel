# Implementation Plan: Company Profile Web

**Branch**: `010-company-profile-web` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/010-company-profile-web/spec.md`

## Summary

Company profile landing page publik per tenant (route `/{tenant}`, no auth)
+ panel admin CMS untuk kelola seluruh konten secara dinamis. Semua konten
DB-driven (9 entitas `company_*`, tenant-scoped). Editor rich-text Tiptap
untuk field narasi, disimpan sebagai JSON. i18n konten via jsonb locale-map
`{id,en}`, label UI via file bahasa existing. Permission modul `content`
(admin only) via spatie. Media via disk public pola existing. Carousel via
`embla-carousel-react` (sudah terpasang). Riset keputusan di [research.md](research.md),
skema di [data-model.md](data-model.md), endpoint di [contracts/api-contracts.md](contracts/api-contracts.md).

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13), TypeScript (React 19, TanStack Start)

**Primary Dependencies**:
- Backend: Laravel, Sanctum, spatie/laravel-permission, spatie/laravel-activitylog, PostgreSQL.
- Frontend (baru): `@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-image`, `@tiptap/extension-link`, `@tiptap/static-renderer`.
- Frontend (existing, reuse): `embla-carousel-react`, shadcn/ui radix-nova, TanStack Router/Query, Tailwind v4.

**Storage**: PostgreSQL (tabel `company_*`, jsonb untuk rich-text + translatable); disk `public` untuk media (pola `UploadMedicalPhotoAction`).

**Testing**: PHPUnit feature/unit (backend), Vitest (frontend).

**Target Platform**: Web (SSR TanStack Start, responsive mobile-first).

**Project Type**: Web service (API Laravel) + web app (TanStack Start SPA/SSR), monorepo.

**Performance Goals**: Landing publik < 3 detik muat (SC-001) — satu endpoint orkestrasi gabungan konten minimalkan round-trip SSR.

**Constraints**: Konten publik baca-only, tanpa auth. CMS admin wajib auth + `content.manage`. Isolasi tenant (konstitusi III). Audit log naratif tiap perubahan (konstitusi VI).

**Scale/Scope**: 9 entitas CMS + 1 landing page publik + ~8 halaman admin CRUD + 1 editor Tiptap reusable. Multi-tenant: tiap klinik profil sendiri.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|--------|
| I. Clean Code | PASS | Controller→Service→Action satu arah; DRY via Service per use case; FormRequest validasi; Resource response. |
| II. TDD | PASS | Feature test per endpoint + unit test Action audit; test task ditulis lebih dulu di tasks.md. |
| III. Multi-Tenant Isolation | PASS | Semua entitas `BelongsToTenant` + `TenantScope`; landing resolve tenant by slug; FK tenant_id NOT NULL. |
| IV. Simplicity (YAGNI) | PASS | Tabel per tipe entitas (bukan EAV) — field berbeda, validasi jelas; embla reuse; back-to-top native; chat widget sendiri v1. |
| V. Bounded Size | PASS | Class PHP ≤300, method ≤100, komponen React ≤300 — extract section landing ke partials. |
| VI. Permission & Activity Log | PASS | Modul `content` via spatie MATRIX; audit via `LogAuditAction`/`activity()` naratif + properties old/new. |

Tidak ada violation. Tidak ada Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/010-company-profile-web/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── api-contracts.md
└── tasks.md             # /speckit-tasks (next)
```

### Source Code (repository root)

```text
apps/api/
├── app/
│   ├── Enums/
│   │   ├── CompanyCtaType.php          # route_internal|external|whatsapp
│   │   ├── CompanyLinkType.php         # anchor_section|route_internal|external
│   │   ├── CompanyNavPosition.php      # header|footer
│   │   ├── CompanyTreatmentBadge.php   # featured|current
│   │   ├── CompanySectionLayout.php    # banner|split
│   │   └── CompanySectionKey.php       # pharma_banner|booking_cta|estore_cta
│   ├── Models/
│   │   ├── CompanyProfileSetting.php
│   │   ├── CompanyNavigationItem.php
│   │   ├── CompanyProfileSlide.php
│   │   ├── CompanyValueProp.php
│   │   ├── CompanyTreatment.php
│   │   ├── CompanyPromo.php
│   │   ├── CompanyBrand.php
│   │   ├── CompanyTestimonial.php
│   │   └── CompanyContentSection.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CompanyProfileController.php    # publik GET /{tenant}/profile
│   │   │   └── CompanyContentController.php    # CMS admin CRUD per entitas
│   │   ├── Requests/CompanyProfile/
│   │   │   ├── SlideRequest.php
│   │   │   ├── ValuePropRequest.php
│   │   │   ├── TreatmentRequest.php
│   │   │   ├── PromoRequest.php
│   │   │   ├── BrandRequest.php
│   │   │   ├── TestimonialRequest.php
│   │   │   ├── ContentSectionRequest.php
│   │   │   ├── NavigationItemRequest.php
│   │   │   ├── SettingsRequest.php
│   │   │   ├── MediaUploadRequest.php
│   │   │   └── ReorderRequest.php
│   │   └── Resources/
│   │       └── CompanyProfile/  (Resource per entitas + CompanyProfileLandingResource)
│   ├── Services/
│   │   └── CompanyProfileService.php   # orkestrasi CRUD per entitas + landing aggregation
│   ├── Actions/CompanyProfile/
│   │   ├── CreateSlideAction.php
│   │   ├── UpdateSlideAction.php
│   │   ├── DeleteSlideAction.php
│   │   ├── ToggleSlideActiveAction.php
│   │   ├── … (per entitas: Create/Update/Delete/Toggle)
│   │   └── UploadCompanyMediaAction.php
│   └── Policies/
│       └── CompanyProfileContentPolicy.php
├── database/migrations/
│   └── 2026_08_14_XXXXXX_create_company_profile_tables.php
└── routes/api.php                      # tambah grup publik + CMS

apps/web/
├── src/
│   ├── routes/$tenant/
│   │   ├── index.tsx                    # landing publik (no auth)
│   │   ├── treatment/$slug.tsx          # detail featured treatment
│   │   └── clinic/company-profile/      # CMS admin
│   │       ├── route.tsx
│   │       ├── index.tsx                # dashboard/overview CMS
│   │       ├── slides/ value-props/ treatments/ promos/
│   │       ├── brands/ testimonials/ content-sections/
│   │       ├── navigation-items/ settings/
│   │       └── components/              # partials CMS (tiap entitas)
│   ├── components/company-profile/      # section landing publik (partials)
│   │   ├── hero-carousel.tsx
│   │   ├── value-props-section.tsx
│   │   ├── treatment-grid.tsx
│   │   ├── promo-section.tsx
│   │   ├── brand-section.tsx
│   │   ├── testimonial-section.tsx
│   │   ├── content-banner.tsx
│   │   ├── booking-cta.tsx
│   │   ├── estore-cta.tsx
│   │   ├── back-to-top.tsx
│   │   └── chat-widget.tsx
│   ├── components/ui/
│   │   └── tiptap-editor.tsx            # editor rich-text reusable
│   ├── hooks/
│   │   ├── use-company-profile.ts       # query landing content
│   │   └── use-translatable-field.ts    # helper field jsonb locale-map
│   └── lib/
└── package.json                         # tambah @tiptap/* deps
```

**Structure Decision**: Web application (frontend + backend) sesuai monorepo
existing `apps/api` + `apps/web`. Backend ikut layering Controller→Service→
Action + FormRequest + Resource + Policy. Frontend: landing publik di
`$tenant/index.tsx` dengan partials section di `components/company-profile/`;
CMS admin di `$tenant/clinic/company-profile/` (auth + sidebar existing).
Editor Tiptap reusable di `components/ui/tiptap-editor.tsx`.

## Complexity Tracking

Tidak ada violation konstitusi — tabel kosong.
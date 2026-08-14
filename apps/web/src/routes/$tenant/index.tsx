import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"

import { BackToTop } from "#/components/company-profile/back-to-top.tsx"
import { BookingCta } from "#/components/company-profile/booking-cta.tsx"
import { BrandSection } from "#/components/company-profile/brand-section.tsx"
import { ChatWidget } from "#/components/company-profile/chat-widget.tsx"
import { CompanyFooter } from "#/components/company-profile/company-footer.tsx"
import { CompanyHeader } from "#/components/company-profile/company-header.tsx"
import { ContentBanner } from "#/components/company-profile/content-banner.tsx"
import { EstoreCta } from "#/components/company-profile/estore-cta.tsx"
import { HeroCarousel } from "#/components/company-profile/hero-carousel.tsx"
import { CompanyLocaleProvider } from "#/components/company-profile/locale-context.tsx"
import { PromoSection } from "#/components/company-profile/promo-section.tsx"
import { TestimonialSection } from "#/components/company-profile/testimonial-section.tsx"
import { TreatmentGrid } from "#/components/company-profile/treatment-grid.tsx"
import { ValuePropsSection } from "#/components/company-profile/value-props-section.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useCompanyProfile } from "#/hooks/use-company-profile.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { normalizeLocale, type ContentLocale } from "#/lib/company-locale.ts"

export const Route = createFileRoute("/$tenant/")({
  component: CompanyLandingPage,
  validateSearch: (search: Record<string, unknown>) => ({
    lang: search.lang ? String(search.lang) : undefined,
  }),
})

function CompanyLandingPage() {
  const { tenant } = useParams({ from: "/$tenant/" })
  const { lang } = Route.useSearch()
  const navigate = useNavigate({ from: "/$tenant/" })
  const locale = normalizeLocale(lang)
  // Label sistem ikut bahasa yang dipilih pengunjung, bukan hanya kontennya.
  const { t } = useTrans(locale)

  const { data, isLoading } = useCompanyProfile(tenant, locale)

  const switchLocale = (next: ContentLocale) =>
    navigate({ search: { lang: next }, replace: true })

  return (
    <CompanyLocaleProvider locale={locale}>
      <div className="flex min-h-svh flex-col">
        <CompanyHeader
          tenant={tenant}
          settings={data?.settings ?? null}
          items={data?.navigation.header ?? []}
          locale={locale}
          languageLabel={t("company_profile.language")}
          onLocaleChange={switchLocale}
        />

        {isLoading ? (
          <LandingSkeleton />
        ) : !data?.is_published ? (
          <main className="flex flex-1 items-center justify-center px-4 py-24">
            <div className="max-w-md text-center">
              <h1 className="text-lg font-semibold tracking-tight">
                {t("company_profile.not_published")}
              </h1>
              <p className="mt-1.5 text-sm text-muted-foreground">
                {t("company_profile.not_published_hint")}
              </p>
            </div>
          </main>
        ) : (
          <>
            <main className="flex-1">
              <HeroCarousel tenant={tenant} slides={data.slides} />
              <ValuePropsSection
                id="keunggulan"
                heading={t("company_profile.value_props")}
                items={data.value_props}
              />
              <TreatmentGrid
                id="treatment"
                tenant={tenant}
                heading={t("company_profile.treatments")}
                readMoreLabel={t("company_profile.read_more")}
                items={data.treatments}
              />
              <PromoSection
                id="promo"
                tenant={tenant}
                heading={t("company_profile.promos")}
                items={data.promos}
              />
              <ContentBanner
                tenant={tenant}
                section={data.content_sections.pharma_banner}
              />
              <BookingCta
                tenant={tenant}
                section={data.content_sections.booking_cta}
              />
              <BrandSection
                id="brand"
                heading={t("company_profile.brands")}
                items={data.brands}
              />
              <TestimonialSection
                id="testimoni"
                heading={t("company_profile.testimonials")}
                sinceTemplate={t("company_profile.since_year")}
                items={data.testimonials}
              />
              <EstoreCta
                tenant={tenant}
                section={data.content_sections.estore_cta}
              />
            </main>

            <CompanyFooter
              tenant={tenant}
              settings={data.settings}
              items={data.navigation.footer}
            />

            <BackToTop label={t("company_profile.back_to_top")} />
            <ChatWidget
              channels={data.settings?.chat_channels ?? []}
              fallbackLabel={t("company_profile.chat_with_us")}
            />
          </>
        )}
      </div>
    </CompanyLocaleProvider>
  )
}

function LandingSkeleton() {
  return (
    <div>
      <Skeleton className="h-[380px] w-full rounded-none sm:h-[460px]" />
      <div className="mx-auto w-full max-w-6xl space-y-4 px-4 py-14">
        <Skeleton className="h-8 w-56" />
        <Skeleton className="h-40 w-full" />
      </div>
    </div>
  )
}

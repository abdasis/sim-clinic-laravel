import { createFileRoute } from "@tanstack/react-router"

import { BookingCta } from "#/components/company-profile/booking-cta.tsx"
import { ContentBanner } from "#/components/company-profile/content-banner.tsx"
import { HeroCarousel } from "#/components/company-profile/hero-carousel.tsx"
import { PromoSection } from "#/components/company-profile/promo-section.tsx"
import { TestimonialSection } from "#/components/company-profile/testimonial-section.tsx"
import { TreatmentGrid } from "#/components/company-profile/treatment-grid.tsx"
import { ValuePropsSection } from "#/components/company-profile/value-props-section.tsx"
import { useContentLocale } from "#/components/company-profile/locale-context.tsx"
import { LANDING_CONTENT } from "#/lib/landing-content.ts"

export const Route = createFileRoute("/_marketing/")({
  component: LandingPage,
})

/**
 * Landing marketing. Kontennya statis, tapi komponen section-nya sama persis
 * dengan yang dipakai halaman tenant — hanya sumber datanya yang berbeda.
 */
function LandingPage() {
  const locale = useContentLocale()
  const isEnglish = locale === "en"
  const data = LANDING_CONTENT
  const sections = data.content_sections

  const copy = isEnglish
    ? {
        valueProps: "Why choose us",
        treatments: "Featured treatments",
        promos: "Running promos",
        testimonials: "What our patients say",
        readMore: "Details",
      }
    : {
        valueProps: "Kenapa memilih kami",
        treatments: "Treatment pilihan",
        promos: "Promo berjalan",
        testimonials: "Kata pasien kami",
        readMore: "Selengkapnya",
      }

  return (
    <main>
      <HeroCarousel tenant="" slides={data.slides} />

      {/* Pola latar diselang-seling supaya halaman panjang tidak terasa rata. */}
      <ValuePropsSection
        id="kenapa"
        heading={copy.valueProps}
        items={data.value_props}
        className="bg-dot-grid"
      />
      <TreatmentGrid
        id="treatment"
        tenant=""
        basePath="/treatment"
        heading={copy.treatments}
        readMoreLabel={copy.readMore}
        items={data.treatments}
      />
      <PromoSection
        id="promo"
        tenant=""
        heading={copy.promos}
        items={data.promos}
        className="bg-line-grid"
      />
      <ContentBanner tenant="" section={sections.pharma_banner} />
      <BookingCta tenant="" section={sections.booking_cta} />
      <TestimonialSection
        id="testimoni"
        heading={copy.testimonials}
        sinceTemplate={
          isEnglish ? "Patient since :year" : "Pelanggan sejak :year"
        }
        items={data.testimonials}
      />
    </main>
  )
}

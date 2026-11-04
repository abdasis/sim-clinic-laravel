import { createFileRoute } from "@tanstack/react-router"

import { BookingCta } from "#/components/company-profile/booking-cta.tsx"
import { ContentBanner } from "#/components/company-profile/content-banner.tsx"
import { HeroCarousel } from "#/components/company-profile/hero-carousel.tsx"
import { PromoSection } from "#/components/company-profile/promo-section.tsx"
import { TestimonialSection } from "#/components/company-profile/testimonial-section.tsx"
import { TreatmentGrid } from "#/components/company-profile/treatment-grid.tsx"
import { ValuePropsSection } from "#/components/company-profile/value-props-section.tsx"
import { LANDING_CONTENT } from "#/lib/landing-content.ts"

export const Route = createFileRoute("/_marketing/")({
  component: LandingPage,
})

/**
 * Landing marketing. Kontennya statis, tapi komponen section-nya sama persis
 * dengan yang dipakai halaman tenant — hanya sumber datanya yang berbeda.
 */
function LandingPage() {
  const data = LANDING_CONTENT
  const sections = data.content_sections

  return (
    <main>
      <HeroCarousel tenant="" slides={data.slides} />

      {/* Pola latar diselang-seling supaya halaman panjang tidak terasa rata. */}
      <ValuePropsSection
        id="kenapa"
        heading="Kenapa memilih kami"
        items={data.value_props}
        className="bg-dot-grid"
      />
      <TreatmentGrid
        id="treatment"
        tenant=""
        basePath="/treatment"
        heading="Treatment pilihan"
        readMoreLabel="Selengkapnya"
        items={data.treatments}
      />
      <PromoSection
        id="promo"
        tenant=""
        heading="Promo berjalan"
        items={data.promos}
        className="bg-line-grid"
      />
      <ContentBanner tenant="" section={sections.pharma_banner} />
      <BookingCta tenant="" section={sections.booking_cta} />
      <TestimonialSection
        id="testimoni"
        heading="Kata pasien kami"
        sinceTemplate="Pelanggan sejak :year"
        items={data.testimonials}
      />
    </main>
  )
}

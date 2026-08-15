import { createFileRoute } from "@tanstack/react-router"

import { useContentLocale } from "#/components/company-profile/locale-context.tsx"
import { TreatmentGrid } from "#/components/company-profile/treatment-grid.tsx"
import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { LANDING_CONTENT } from "#/lib/landing-content.ts"

export const Route = createFileRoute("/_marketing/treatments/")({
  component: TreatmentListPage,
})

function TreatmentListPage() {
  const locale = useContentLocale()
  const isEnglish = locale === "en"

  return (
    <MarketingPage
      crumbs={[{ label: isEnglish ? "Treatments" : "Treatment" }]}
      title={isEnglish ? "All treatments" : "Semua treatment"}
      description={
        isEnglish
          ? "Every treatment starts with a free consultation, so the plan fits your skin — not a package."
          : "Setiap treatment dimulai dari konsultasi gratis, supaya rencananya menyesuaikan kulitmu — bukan paketnya."
      }
    >
      <TreatmentGrid
        tenant=""
        basePath="/treatment"
        readMoreLabel={isEnglish ? "Details" : "Selengkapnya"}
        items={LANDING_CONTENT.treatments}
        className="border-t-0 py-0"
      />
    </MarketingPage>
  )
}

import { createFileRoute } from "@tanstack/react-router"

import { TreatmentGrid } from "#/components/company-profile/treatment-grid.tsx"
import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { LANDING_CONTENT } from "#/lib/landing-content.ts"

export const Route = createFileRoute("/_marketing/treatments/")({
  component: TreatmentListPage,
})

function TreatmentListPage() {
  return (
    <MarketingPage
      crumbs={[{ label: "Treatment" }]}
      title="Semua treatment"
      description="Setiap treatment dimulai dari konsultasi gratis, supaya rencananya menyesuaikan kulitmu — bukan paketnya."
    >
      <TreatmentGrid
        tenant=""
        basePath="/treatment"
        readMoreLabel="Selengkapnya"
        items={LANDING_CONTENT.treatments}
        className="border-t-0 py-0"
      />
    </MarketingPage>
  )
}

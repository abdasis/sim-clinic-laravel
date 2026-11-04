import { createFileRoute } from "@tanstack/react-router"

import { PromoSection } from "#/components/company-profile/promo-section.tsx"
import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { LANDING_CONTENT } from "#/lib/landing-content.ts"

export const Route = createFileRoute("/_marketing/promo/")({
  component: PromoListPage,
})

function PromoListPage() {
  return (
    <MarketingPage
      crumbs={[{ label: "Promo" }]}
      title="Promo yang sedang berjalan"
      description="Syarat berlaku per promo. Tanyakan dulu ke tim kami sebelum booking supaya tidak ada kejutan di kasir."
    >
      <PromoSection
        tenant=""
        items={LANDING_CONTENT.promos}
        className="border-t-0 py-0"
      />
    </MarketingPage>
  )
}

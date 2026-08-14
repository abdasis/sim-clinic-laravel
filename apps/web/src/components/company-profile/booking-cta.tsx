import type { CompanyContentSection } from "#/hooks/use-company-profile.ts"
import { ContentBanner } from "./content-banner.tsx"

interface BookingCtaProps {
  tenant: string
  section?: CompanyContentSection | null
}

/** Ajakan booking — slot yang paling ingin diklik, jadi diberi penekanan. */
export function BookingCta({ tenant, section }: BookingCtaProps) {
  return (
    <ContentBanner id="booking" tenant={tenant} section={section} emphasis />
  )
}

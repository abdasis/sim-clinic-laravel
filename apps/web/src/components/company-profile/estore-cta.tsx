import type { CompanyContentSection } from "#/hooks/use-company-profile.ts"
import { ContentBanner } from "./content-banner.tsx"

interface EstoreCtaProps {
  tenant: string
  section?: CompanyContentSection | null
}

/** Ajakan belanja produk di toko daring klinik. */
export function EstoreCta({ tenant, section }: EstoreCtaProps) {
  return <ContentBanner id="estore" tenant={tenant} section={section} />
}

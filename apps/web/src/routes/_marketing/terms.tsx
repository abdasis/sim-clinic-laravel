import { createFileRoute } from "@tanstack/react-router"

import {
  LegalDocument,
  legalContact,
} from "#/components/marketing/legal-document.tsx"
import { TERMS_EFFECTIVE_DATE, termsSections } from "./components/terms-content.tsx"

export const Route = createFileRoute("/_marketing/terms")({
  component: TermsPage,
})

function TermsPage() {
  return (
    <LegalDocument
      kicker="Legal"
      title="Ketentuan Layanan"
      summary="Aturan pemakaian sistem klinik dan kanal pesan WhatsApp, termasuk tanggung jawab klinik atas data pasien."
      effectiveDate={TERMS_EFFECTIVE_DATE}
      sections={termsSections(legalContact())}
    />
  )
}

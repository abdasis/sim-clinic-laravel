import { createFileRoute } from "@tanstack/react-router"

import {
  LegalDocument,
  legalContact,
} from "#/components/marketing/legal-document.tsx"
import { PRIVACY_EFFECTIVE_DATE, privacySections } from "./components/privacy-content.tsx"

export const Route = createFileRoute("/_marketing/privacy")({
  component: PrivacyPage,
})

function PrivacyPage() {
  return (
    <LegalDocument
      kicker="Legal"
      title="Kebijakan Privasi"
      summary="Ringkasan cara kami mengumpulkan, memakai, dan menjaga data pasien — termasuk pesan yang masuk lewat WhatsApp klinik."
      effectiveDate={PRIVACY_EFFECTIVE_DATE}
      sections={privacySections(legalContact())}
    />
  )
}

import { createFileRoute, useParams } from "@tanstack/react-router"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { LoyaltyRatesCard } from "./components/loyalty-rates-card.tsx"

export const Route = createFileRoute("/$tenant/clinic/loyalty/")({
  component: LoyaltyPage,
})

/**
 * Poin loyalitas — satu-satunya program pelanggan yang berjalan di klinik ini.
 *
 * Halaman ini menggantikan Keanggotaan. Tingkat member sempat ada tapi tidak
 * pernah punya akibat apa pun di kasir: poin diberikan ke semua pasien tanpa
 * melihat tingkatnya, jadi labelnya cuma menjanjikan sesuatu yang tidak pernah
 * terjadi. Yang tersisa dan memang bekerja adalah tarifnya.
 */
function LoyaltyPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/loyalty/" })
  const { t } = useTrans()

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
          { label: t("loyalty.title") },
        ]}
      />

      <div className="mt-4 mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("loyalty.title")}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {t("loyalty.page_desc")}
        </p>
      </div>

      <LoyaltyRatesCard tenant={tenant} />
    </div>
  )
}

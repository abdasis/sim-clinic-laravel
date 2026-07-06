import { createFileRoute, useParams } from "@tanstack/react-router"
import { useState } from "react"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { StockMovementForm } from "./components/stock-movement-form.tsx"
import { StockMovementHistory } from "./components/stock-movement-history.tsx"

export const Route = createFileRoute("/$tenant/clinic/inventory/")({
  component: InventoryPage,
})

function InventoryPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/inventory/" })
  const { t } = useTrans()
  const [productId, setProductId] = useState("")

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("inventory.title") },
        ]}
      />
      <h1 className="mb-4 text-xl font-semibold">{t("inventory.title")}</h1>
      <StockMovementForm tenant={tenant} onProductChange={setProductId} />
      {productId ? (
        <StockMovementHistory tenant={tenant} productId={productId} />
      ) : null}
    </div>
  )
}

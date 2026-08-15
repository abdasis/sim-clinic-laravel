import { createFileRoute, useParams } from "@tanstack/react-router"
import { useCallback, useState } from "react"
import { DeliveryBox01Icon } from "@hugeicons/core-free-icons"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { IndexCta } from "#/components/stats/index-cta.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useStats } from "#/hooks/use-stats.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { StockMovementForm } from "./components/stock-movement-form.tsx"
import { StockMovementHistory } from "./components/stock-movement-history.tsx"

export const Route = createFileRoute("/$tenant/clinic/inventory/")({
  component: InventoryPage,
})

/** Anchor formulir; ajakan di atas menggulir ke sini, bukan pindah halaman. */
const FORM_ANCHOR = "stock-movement-form"

function InventoryPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/inventory/" })
  const { t } = useTrans()
  const [productId, setProductId] = useState("")
  const stats = useStats({ tenant, module: "inventory" })

  // Formulirnya sudah ada di halaman ini, jadi ajakannya mengantar ke sana
  // alih-alih membuka dialog kedua yang isinya sama.
  const focusForm = useCallback(() => {
    const anchor = document.getElementById(FORM_ANCHOR)
    if (!anchor) return

    anchor.scrollIntoView({ behavior: "smooth", block: "center" })
    anchor.querySelector<HTMLElement>("button, input, select")?.focus({
      preventScroll: true,
    })
  }, [])

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("inventory.title") },
        ]}
      />

      <IndexCta
        icon={DeliveryBox01Icon}
        tone="ink"
        mascot="clinic"
        title={t("cta.inventory.title")}
        description={t("cta.inventory.description")}
        actionLabel={t("cta.inventory.action")}
        onAction={focusForm}
      />

      <StatsSection
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
      />

      <h1 className="mb-4 text-xl font-semibold">{t("inventory.title")}</h1>
      <div id={FORM_ANCHOR} className="scroll-mt-20">
        <StockMovementForm tenant={tenant} onProductChange={setProductId} />
      </div>
      {productId ? (
        <StockMovementHistory tenant={tenant} productId={productId} />
      ) : (
        <EmptyState
          className="mt-4 rounded-lg border border-dashed border-border/50 py-12"
          illustration="inventory-select"
          title={t("inventory.select_product")}
          description={t("inventory.select_product_desc")}
        />
      )}
    </div>
  )
}

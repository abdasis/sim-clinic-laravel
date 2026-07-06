import { createFileRoute, useParams } from "@tanstack/react-router"
import { useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "#/components/ui/tabs.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { getAuthUser } from "#/lib/auth.ts"
import { apiGet } from "#/lib/api.ts"
import { RevenueSummary } from "./components/revenue-summary.tsx"
import type { RevenueData } from "./components/revenue-summary.tsx"
import { ServiceSalesTable } from "./components/service-sales-table.tsx"
import type { ServiceSalesRow } from "./components/service-sales-table.tsx"
import { ProductSalesTable } from "./components/product-sales-table.tsx"
import type { ProductSalesRow } from "./components/product-sales-table.tsx"

export const Route = createFileRoute("/$tenant/clinic/reports/")({
  component: ReportsPage,
})

type ReportTab = "revenue" | "services" | "products"

interface ReportResponse {
  data: RevenueData | ServiceSalesRow[] | ProductSalesRow[]
  meta?: { empty?: boolean }
}

function ReportsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/reports/" })
  const { t } = useTrans()
  const isAdmin = getAuthUser()?.clinic_role === "admin"

  const [tab, setTab] = useState<ReportTab>("revenue")
  const [from, setFrom] = useState("")
  const [to, setTo] = useState("")
  const [applied, setApplied] = useState<{ from: string; to: string } | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ["reports", tenant, tab, applied?.from, applied?.to],
    queryFn: () =>
      apiGet<ReportResponse>(`/${tenant}/clinic/reports/${tab}`, {
        from: applied?.from,
        to: applied?.to,
      }),
    enabled: !!applied && isAdmin,
  })

  if (!isAdmin) {
    return (
      <div>
        <ClinicBreadcrumb
          items={[
            { label: tenant, to: "/$tenant/clinic", params: { tenant } },
            { label: t("clinic.clinic") },
            { label: t("report.title") },
          ]}
        />
        <p className="text-muted-foreground">{t("clinic.forbidden")}</p>
      </div>
    )
  }

  const isEmpty = data?.meta?.empty === true

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("report.title") },
        ]}
      />
      <h1 className="mb-4 text-xl font-semibold">{t("report.title")}</h1>

      <form
        className="mb-6 flex flex-wrap items-end gap-4"
        onSubmit={(e) => {
          e.preventDefault()
          setApplied({ from, to })
        }}
      >
        <div className="grid gap-1.5">
          <Label htmlFor="report-from">{t("report.from")}</Label>
          <Input
            id="report-from"
            type="date"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
          />
        </div>
        <div className="grid gap-1.5">
          <Label htmlFor="report-to">{t("report.to")}</Label>
          <Input
            id="report-to"
            type="date"
            value={to}
            onChange={(e) => setTo(e.target.value)}
          />
        </div>
        <Button type="submit">{t("report.generate")}</Button>
      </form>

      <Tabs value={tab} onValueChange={(v) => setTab(v as ReportTab)}>
        <TabsList>
          <TabsTrigger value="revenue">{t("report.revenue")}</TabsTrigger>
          <TabsTrigger value="services">{t("report.services")}</TabsTrigger>
          <TabsTrigger value="products">{t("report.products")}</TabsTrigger>
        </TabsList>

        <div className="mt-4">
          {!applied ? (
            <p className="text-muted-foreground">{t("report.select_range")}</p>
          ) : isLoading ? (
            <p className="text-muted-foreground">{t("general.loading")}</p>
          ) : isEmpty ? (
            <p className="text-muted-foreground">{t("report.empty")}</p>
          ) : (
            <>
              <TabsContent value="revenue">
                {data ? <RevenueSummary data={data.data as RevenueData} /> : null}
              </TabsContent>
              <TabsContent value="services">
                {data ? (
                  <ServiceSalesTable rows={data.data as ServiceSalesRow[]} />
                ) : null}
              </TabsContent>
              <TabsContent value="products">
                {data ? (
                  <ProductSalesTable rows={data.data as ProductSalesRow[]} />
                ) : null}
              </TabsContent>
            </>
          )}
        </div>
      </Tabs>
    </div>
  )
}

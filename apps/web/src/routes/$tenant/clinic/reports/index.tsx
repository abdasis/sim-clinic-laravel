import { createFileRoute, useParams } from "@tanstack/react-router"
import { useState } from "react"
import { useQuery } from "@tanstack/react-query"

import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useStats } from "#/hooks/use-stats.ts"
import { AccessDenied } from "#/components/ui/access-denied.tsx"
import { Card, CardContent } from "#/components/ui/card.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "#/components/ui/tabs.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useDigitShortcut } from "#/hooks/use-go-to-shortcut.ts"
import { useIsMounted } from "#/hooks/use-is-mounted.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { apiGet } from "#/lib/api.ts"
import { MonthlyReport } from "./components/monthly-report.tsx"
import { ReportRangePicker } from "./components/report-range-picker.tsx"
import type { ReportRange } from "./components/report-range-picker.tsx"
import { RevenueSummary } from "./components/revenue-summary.tsx"
import type { RevenueData } from "./components/revenue-summary.tsx"
import { SalesTable } from "./components/sales-table.tsx"
import type { SalesRow } from "./components/sales-table.tsx"

export const Route = createFileRoute("/$tenant/clinic/reports/")({
  component: ReportsPage,
})

type ReportTab = "revenue" | "services" | "products" | "monthly"

/** Baris penjualan datang dengan nama kolom berbeda per tab. */
interface ServiceSalesRow {
  service_id: number
  service_name: string
  qty_sold: number
  revenue: string
}

interface ProductSalesRow {
  product_id: number
  product_name: string
  qty_sold: number
  revenue: string
}

interface ReportResponse {
  data: RevenueData | ServiceSalesRow[] | ProductSalesRow[]
  meta?: { empty?: boolean }
}

const TABS: Array<{ value: ReportTab; key: string; hint: string }> = [
  { value: "revenue", key: "report.revenue", hint: "1" },
  { value: "services", key: "report.services", hint: "2" },
  { value: "products", key: "report.products", hint: "3" },
  { value: "monthly", key: "report.monthly", hint: "4" },
]

const rangeLabel = new Intl.DateTimeFormat("id-ID", {
  day: "numeric",
  month: "short",
  year: "numeric",
})

function ReportsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/reports/" })
  const { t } = useTrans()
  // ponytail: render null saat belum mount, guard role setelah mount — SSR & first client render identik kosong (mencegah hydration mismatch dari localStorage).
  const mounted = useIsMounted()
  const isAdmin = useAuthUser()?.clinic_role === "admin"
  // Laporan memakai ringkasan transaksi yang sama; tanpa ajakan karena
  // pemilih rentang di bawah sudah jadi aksi utamanya.
  const stats = useStats({ tenant, module: "transactions", enabled: isAdmin })

  const [tab, setTab] = useState<ReportTab>("revenue")
  const [applied, setApplied] = useState<ReportRange | null>(null)

  useDigitShortcut("1", () => setTab("revenue"))
  useDigitShortcut("2", () => setTab("services"))
  useDigitShortcut("3", () => setTab("products"))
  useDigitShortcut("4", () => setTab("monthly"))

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ["reports", tenant, tab, applied?.from, applied?.to],
    queryFn: () =>
      apiGet<ReportResponse>(`/${tenant}/clinic/reports/${tab}`, {
        from: applied?.from,
        to: applied?.to,
      }),
    enabled: !!applied && isAdmin,
  })

  if (!mounted) return null

  if (!isAdmin) {
    return <AccessDenied description={t("report.forbidden_desc")} />
  }

  const isEmpty = data?.meta?.empty === true
  const busy = isLoading || isFetching

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("report.title")}
        </h1>
        {applied ? (
          <p className="text-xs text-muted-foreground">
            {t("report.showing")}{" "}
            <span className="font-medium text-foreground tabular-nums">
              {rangeLabel.format(new Date(applied.from))} –{" "}
              {rangeLabel.format(new Date(applied.to))}
            </span>
          </p>
        ) : null}
      </div>

      <StatsSection
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
      />

      <ReportRangePicker applied={applied} onApply={setApplied} />

      <Tabs value={tab} onValueChange={(v) => setTab(v as ReportTab)}>
        <TabsList>
          {TABS.map((item) => (
            <Tooltip key={item.value}>
              <TooltipTrigger asChild>
                <TabsTrigger value={item.value}>{t(item.key)}</TabsTrigger>
              </TooltipTrigger>
              <TooltipContent className="flex items-center gap-2">
                {t(item.key)}
                <Kbd>{item.hint}</Kbd>
              </TooltipContent>
            </Tooltip>
          ))}
        </TabsList>

        <div className="mt-4">
          {!applied ? (
            <Card>
              <CardContent className="py-10">
                <EmptyState
                  illustration="reports"
                  title={t("report.select_range_title")}
                  description={t("report.select_range")}
                />
              </CardContent>
            </Card>
          ) : tab === "monthly" ? (
            <MonthlyReport tenant={tenant} from={applied.from} to={applied.to} />
          ) : busy ? (
            <ReportSkeleton tab={tab} />
          ) : isEmpty ? (
            <Card>
              <CardContent className="py-10">
                <EmptyState
                  illustration="reports"
                  title={t("report.empty")}
                  description={t("report.empty_desc")}
                />
              </CardContent>
            </Card>
          ) : (
            <>
              <TabsContent value="revenue">
                {data ? <RevenueSummary data={data.data as RevenueData} /> : null}
              </TabsContent>
              <TabsContent value="services">
                {data ? (
                  <SalesTable
                    nameLabel={t("report.service_name")}
                    rows={(data.data as ServiceSalesRow[]).map(toSalesRow)}
                  />
                ) : null}
              </TabsContent>
              <TabsContent value="products">
                {data ? (
                  <SalesTable
                    nameLabel={t("report.product_name")}
                    rows={(data.data as ProductSalesRow[]).map(toSalesRow)}
                  />
                ) : null}
              </TabsContent>
            </>
          )}
        </div>
      </Tabs>
    </div>
  )
}

/**
 * Kerangka yang menyerupai isinya, bukan satu baris "Memuat...".
 *
 * Rentang selebar sebulan butuh waktu; kerangka sebentuk hasil membuat
 * layarnya tidak melompat saat angkanya datang.
 */
function ReportSkeleton({ tab }: { tab: ReportTab }) {
  if (tab === "revenue") {
    return (
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {[0, 1, 2].map((i) => (
          <Skeleton key={i} className="h-28 w-full rounded-lg" />
        ))}
      </div>
    )
  }

  return (
    <div className="space-y-2 rounded-lg border border-border/60 p-3">
      {[0, 1, 2, 3, 4].map((i) => (
        <Skeleton key={i} className="h-8 w-full" />
      ))}
    </div>
  )
}

/** Dua tab memakai kolom bernama berbeda untuk data yang bentuknya sama. */
function toSalesRow(row: ServiceSalesRow | ProductSalesRow): SalesRow {
  return {
    id: "service_id" in row ? row.service_id : row.product_id,
    name: "service_name" in row ? row.service_name : row.product_name,
    qty_sold: row.qty_sold,
    revenue: row.revenue,
  }
}

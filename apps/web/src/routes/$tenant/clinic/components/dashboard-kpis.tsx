import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import {
  Alert01Icon,
  Calendar01Icon,
  CoinsDollarIcon,
  UserAdd01Icon,
  WalletAdd01Icon,
} from "@hugeicons/core-free-icons"

import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import type { DashboardKpis, RevenuePoint } from "#/hooks/use-dashboard.ts"
import { cn } from "#/lib/utils.ts"

interface DashboardKpisProps {
  kpis?: DashboardKpis
  trend?: RevenuePoint[]
  isLoading?: boolean
}

export function DashboardKpiRow({ kpis, trend, isLoading }: DashboardKpisProps) {
  const { t } = useTrans()

  if (isLoading || !kpis) {
    return (
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        {Array.from({ length: 5 }, (_, index) => (
          <Skeleton key={index} className="h-24 w-full rounded-lg" />
        ))}
      </div>
    )
  }

  const tiles = [
    {
      key: "revenue_today",
      icon: CoinsDollarIcon,
      label: t("dashboard.kpi.revenue_today"),
      value: formatCurrency(Number(kpis.revenue_today)),
      delta: revenueDelta(trend),
    },
    {
      key: "bookings_today",
      icon: Calendar01Icon,
      label: t("dashboard.kpi.bookings_today"),
      value: String(kpis.bookings_today),
    },
    {
      key: "new_patients",
      icon: UserAdd01Icon,
      label: t("dashboard.kpi.new_patients"),
      value: String(kpis.new_patients_7d),
    },
    {
      key: "outstanding",
      icon: WalletAdd01Icon,
      label: t("dashboard.kpi.outstanding"),
      value: formatCurrency(Number(kpis.outstanding_amount)),
    },
    {
      key: "low_stock",
      icon: Alert01Icon,
      label: t("dashboard.kpi.low_stock"),
      value: String(kpis.low_stock_count),
      alert: kpis.low_stock_count > 0,
    },
  ]

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      {tiles.map((tile, index) => (
        <StatTile {...tile} key={tile.key} index={index} />
      ))}
    </div>
  )
}

function StatTile({
  icon,
  label,
  value,
  delta,
  alert,
  index,
}: {
  icon: IconSvgElement
  label: string
  value: string
  delta?: number
  alert?: boolean
  index: number
}) {
  return (
    <article
      className="rise-in rounded-lg border border-border/50 bg-card p-4 shadow-sm transition-colors hover:border-border"
      style={{ animationDelay: `${index * 45}ms` }}
    >
      <div className="mb-2 flex items-center justify-between gap-2">
        <span className="text-2xs font-medium tracking-widest text-muted-foreground uppercase">
          {label}
        </span>
        <HugeiconsIcon
          icon={icon}
          strokeWidth={2}
          className={cn(
            "size-4 shrink-0",
            alert ? "text-destructive" : "text-muted-foreground",
          )}
        />
      </div>
      <p className="text-xl font-semibold tabular-nums">{value}</p>
      {delta !== undefined ? <DeltaLabel delta={delta} /> : null}
    </article>
  )
}

/** Selisih omzet hari terakhir terhadap hari sebelumnya, dalam persen. */
function DeltaLabel({ delta }: { delta: number }) {
  if (!Number.isFinite(delta) || delta === 0) return null

  const up = delta > 0

  return (
    <p
      className={cn(
        "mt-0.5 text-xs font-medium tabular-nums",
        up ? "text-primary" : "text-destructive",
      )}
    >
      {up ? "▲" : "▼"} {Math.abs(delta).toFixed(0)}%
    </p>
  )
}

function revenueDelta(trend?: RevenuePoint[]): number | undefined {
  if (!trend || trend.length < 2) return undefined

  const last = Number(trend[trend.length - 1].revenue)
  const previous = Number(trend[trend.length - 2].revenue)

  // Naik dari nol tidak punya persentase yang jujur; label disembunyikan.
  if (previous === 0) return undefined

  return ((last - previous) / previous) * 100
}

import { useMemo } from "react"
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from "recharts"

import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "#/components/ui/chart.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"
import type { RevenuePoint } from "#/hooks/use-dashboard.ts"

/** Satu seri, jadi judul kartu sudah menamainya — tidak perlu legend. */
const CONFIG = {
  revenue: { label: "Omzet", color: "var(--chart-3)" },
} satisfies ChartConfig

export function RevenueTrendChart({ points }: { points: RevenuePoint[] }) {
  const { t } = useTrans()

  const data = useMemo(
    () => points.map((point) => ({ ...point, value: Number(point.revenue) })),
    [points],
  )

  const hasRevenue = data.some((point) => point.value > 0)

  if (!hasRevenue) {
    return (
      <p className="flex h-56 items-center justify-center text-sm text-muted-foreground">
        {t("dashboard.empty_revenue")}
      </p>
    )
  }

  return (
    <ChartContainer config={CONFIG} className="h-56 w-full">
      <AreaChart data={data} margin={{ left: 4, right: 8, top: 8 }}>
        <defs>
          <linearGradient id="revenue-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--color-revenue)" stopOpacity={0.18} />
            <stop offset="100%" stopColor="var(--color-revenue)" stopOpacity={0.02} />
          </linearGradient>
        </defs>

        {/* Grid dan sumbu sengaja pucat; datanya yang harus menonjol. */}
        <CartesianGrid vertical={false} strokeOpacity={0.35} />
        <XAxis
          dataKey="date"
          tickLine={false}
          axisLine={false}
          tickMargin={8}
          minTickGap={24}
          tickFormatter={(value: string) =>
            new Date(value).toLocaleDateString("id-ID", {
              day: "2-digit",
              month: "short",
            })
          }
        />
        <YAxis
          tickLine={false}
          axisLine={false}
          width={56}
          tickFormatter={(value: number) => compactCurrency(value)}
        />
        <ChartTooltip
          cursor={{ strokeOpacity: 0.4 }}
          content={
            <ChartTooltipContent
              labelFormatter={(label) => formatDate(String(label))}
              formatter={(value) => formatCurrency(Number(value))}
            />
          }
        />
        <Area
          dataKey="value"
          name="revenue"
          type="monotone"
          stroke="var(--color-revenue)"
          strokeWidth={2}
          fill="url(#revenue-fill)"
          animationDuration={700}
          animationEasing="ease-out"
          activeDot={{ r: 4, strokeWidth: 2 }}
        />
      </AreaChart>
    </ChartContainer>
  )
}

/** Sumbu Y memakai bentuk ringkas supaya labelnya tidak saling tabrak. */
function compactCurrency(value: number): string {
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} jt`
  if (value >= 1_000) return `${Math.round(value / 1_000)} rb`

  return String(value)
}

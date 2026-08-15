import { useMemo } from "react"
import { Bar, BarChart, LabelList, XAxis, YAxis } from "recharts"

import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "#/components/ui/chart.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import type { TopService } from "#/hooks/use-dashboard.ts"

/** Magnitudo, bukan identitas — satu hue, makin besar makin pekat. */
const CONFIG = {
  revenue: { label: "Omzet", color: "var(--chart-3)" },
} satisfies ChartConfig

export function TopServicesChart({ items }: { items: TopService[] }) {
  const { t } = useTrans()

  const data = useMemo(
    () =>
      items.map((item, index) => ({
        ...item,
        value: Number(item.revenue),
        // Peringkat pertama paling pekat; sisanya melunak bertahap.
        opacity: 1 - index * 0.15,
      })),
    [items],
  )

  if (data.length === 0) {
    return (
      <p className="flex h-56 items-center justify-center text-sm text-muted-foreground">
        {t("dashboard.empty")}
      </p>
    )
  }

  return (
    <ChartContainer config={CONFIG} className="h-56 w-full">
      <BarChart
        data={data}
        layout="vertical"
        margin={{ left: 4, right: 56, top: 4, bottom: 4 }}
        barCategoryGap={10}
      >
        <XAxis type="number" dataKey="value" hide />
        <YAxis
          type="category"
          dataKey="service_name"
          tickLine={false}
          axisLine={false}
          width={110}
          tickMargin={6}
        />
        <ChartTooltip
          cursor={{ fillOpacity: 0.06 }}
          content={
            <ChartTooltipContent
              formatter={(value) => formatCurrency(Number(value))}
            />
          }
        />
        <Bar
          dataKey="value"
          name="revenue"
          fill="var(--color-revenue)"
          radius={[0, 4, 4, 0]}
          maxBarSize={24}
          animationDuration={700}
          animationEasing="ease-out"
        >
          {/* Angka di ujung batang; membaca panjang batang saja tidak cukup. */}
          <LabelList
            dataKey="value"
            position="right"
            offset={8}
            className="fill-muted-foreground text-xs tabular-nums"
            formatter={(value) => compactCurrency(Number(value))}
          />
        </Bar>
      </BarChart>
    </ChartContainer>
  )
}

function compactCurrency(value: number): string {
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} jt`
  if (value >= 1_000) return `${Math.round(value / 1_000)} rb`

  return String(value)
}

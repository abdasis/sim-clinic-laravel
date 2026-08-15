import { useId, useMemo } from "react"
import { motion, useReducedMotion } from "motion/react"
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from "recharts"

import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "#/components/ui/chart.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"
import type { StatPoint, StatUnit } from "#/hooks/use-stats.ts"

interface StatTrendChartProps {
  points: StatPoint[]
  /** Judul kartu; sekaligus menamai satu-satunya seri, jadi tanpa legend. */
  label: string
  unit?: StatUnit
}

/**
 * Tren satu modul dalam satu seri. Satu seri berarti judul kartu sudah cukup
 * menamainya — legend hanya menambah kotak tanpa menambah informasi.
 */
export function StatTrendChart({
  points,
  label,
  unit = "count",
}: StatTrendChartProps) {
  const { t } = useTrans()
  const reduced = useReducedMotion()
  const gradientId = `trend-${useId()}`

  const config = useMemo(
    () => ({ value: { label, color: "var(--chart-3)" } }) satisfies ChartConfig,
    [label],
  )

  const hasMovement = points.some((point) => point.value !== 0)

  return (
    <motion.section
      initial={reduced ? false : { opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45, delay: reduced ? 0 : 0.2, ease: [0.16, 1, 0.3, 1] }}
      className="rounded-lg border border-border/50 bg-card p-4 shadow-sm"
    >
      <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
        <h3 className="text-sm font-semibold tracking-tight">{label}</h3>
        <p className="text-xs text-muted-foreground">{t("stats.chart_hint")}</p>
      </div>

      {hasMovement ? (
        <ChartContainer config={config} className="h-48 w-full">
          <AreaChart data={points} margin={{ left: 4, right: 8, top: 8 }}>
            <defs>
              <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="var(--color-value)" stopOpacity={0.2} />
                <stop offset="100%" stopColor="var(--color-value)" stopOpacity={0.02} />
              </linearGradient>
            </defs>

            {/* Grid dan sumbu sengaja pucat; garisnya yang harus terbaca. */}
            <CartesianGrid vertical={false} strokeOpacity={0.3} />
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              minTickGap={28}
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
              width={unit === "currency" ? 56 : 34}
              allowDecimals={false}
              tickFormatter={(value) => compact(Number(value), unit)}
            />
            <ChartTooltip
              cursor={{ strokeOpacity: 0.4 }}
              content={
                <ChartTooltipContent
                  labelFormatter={(value) => formatDate(String(value))}
                  formatter={(value) =>
                    unit === "currency"
                      ? formatCurrency(Number(value))
                      : String(value)
                  }
                />
              }
            />
            <Area
              dataKey="value"
              type="monotone"
              stroke="var(--color-value)"
              strokeWidth={2}
              fill={`url(#${gradientId})`}
              isAnimationActive={!reduced}
              animationDuration={700}
              animationEasing="ease-out"
              activeDot={{ r: 4, strokeWidth: 2 }}
            />
          </AreaChart>
        </ChartContainer>
      ) : (
        <div className="flex h-48 flex-col items-center justify-center gap-1 text-center">
          <p className="text-sm font-medium">{t("stats.empty_title")}</p>
          <p className="max-w-xs text-xs text-muted-foreground">
            {t("stats.empty_description")}
          </p>
        </div>
      )}
    </motion.section>
  )
}

/** Sumbu Y memakai bentuk ringkas supaya labelnya tidak saling tabrak. */
function compact(value: number, unit: StatUnit): string {
  const abs = Math.abs(value)

  if (abs >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} jt`
  if (abs >= 1_000) return `${Math.round(value / 1_000)} rb`
  if (unit === "currency" && value === 0) return "0"

  return String(value)
}

import { useEffect, useId } from "react"
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import {
  animate,
  motion,
  useMotionValue,
  useReducedMotion,
  useTransform,
} from "motion/react"
import { Area, AreaChart } from "recharts"

import { ChartContainer, type ChartConfig } from "#/components/ui/chart.tsx"
import { formatCurrency } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import type { StatPoint, StatUnit } from "#/hooks/use-stats.ts"

const number = new Intl.NumberFormat("id-ID")

const SPARK_CONFIG = {
  value: { label: "", color: "var(--chart-3)" },
} satisfies ChartConfig

/** Titik terakhir saja — kartu KPI hanya perlu isyarat arah, bukan detail. */
const SPARK_POINTS = 14

export interface StatCardProps {
  label: string
  value: number
  unit?: StatUnit
  icon: IconSvgElement
  /** Deret tren modul; kartu memotong sendiri ekornya. */
  spark?: StatPoint[]
  /** Urutan kartu — dipakai untuk menahan animasi masuk secara berjenjang. */
  index?: number
  /** Sorot merah, mis. saat stok menipis. */
  alert?: boolean
}

export function StatCard({
  label,
  value,
  unit = "count",
  icon,
  spark,
  index = 0,
  alert,
}: StatCardProps) {
  const reduced = useReducedMotion()
  // Beberapa kartu hidup berdampingan; id gradien harus unik per kartu.
  const gradientId = `spark-${useId()}`
  const tail = spark?.slice(-SPARK_POINTS) ?? []
  const hasSpark = tail.some((point) => point.value !== 0)

  return (
    <motion.article
      initial={reduced ? false : { opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{
        duration: 0.4,
        delay: reduced ? 0 : index * 0.05,
        ease: [0.16, 1, 0.3, 1],
      }}
      whileHover={reduced ? undefined : { y: -2 }}
      className="group rounded-lg border border-border/50 bg-card p-4 shadow-sm transition-colors hover:border-border focus-within:border-border"
    >
      <div className="mb-2 flex items-start justify-between gap-2">
        <span className="text-[11px] leading-4 font-medium tracking-widest text-muted-foreground uppercase">
          {label}
        </span>
        <HugeiconsIcon
          icon={icon}
          strokeWidth={2}
          className={cn(
            "size-4 shrink-0 transition-colors",
            alert
              ? "text-destructive"
              : "text-muted-foreground group-hover:text-foreground",
          )}
        />
      </div>

      <CountUp value={value} unit={unit} reduced={Boolean(reduced)} />

      {/* Sparkline hanya muncul kalau ada yang bergerak — garis rata di nol
          justru menyiratkan data yang tidak ada. */}
      <div className="mt-2 h-8">
        {hasSpark ? (
          <ChartContainer config={SPARK_CONFIG} className="h-8 w-full">
            <AreaChart data={tail} margin={{ top: 2, bottom: 2, left: 0, right: 0 }}>
              <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="var(--color-value)" stopOpacity={0.25} />
                  <stop offset="100%" stopColor="var(--color-value)" stopOpacity={0} />
                </linearGradient>
              </defs>
              <Area
                dataKey="value"
                type="monotone"
                stroke="var(--color-value)"
                strokeWidth={2}
                fill={`url(#${gradientId})`}
                isAnimationActive={!reduced}
                animationDuration={600}
                dot={false}
              />
            </AreaChart>
          </ChartContainer>
        ) : null}
      </div>
    </motion.article>
  )
}

/**
 * Angka merayap naik dari nol saat kartu muncul. Nilainya diformat tiap frame
 * lewat motion value, jadi React tidak ikut render 60 kali per detik.
 */
function CountUp({
  value,
  unit,
  reduced,
}: {
  value: number
  unit: StatUnit
  reduced: boolean
}) {
  const raw = useMotionValue(reduced ? value : 0)
  const text = useTransform(raw, (current) => formatStat(current, unit))

  useEffect(() => {
    if (reduced) {
      raw.set(value)

      return
    }

    const controls = animate(raw, value, {
      duration: 0.8,
      ease: [0.16, 1, 0.3, 1],
    })

    return () => controls.stop()
  }, [raw, reduced, value])

  return (
    <p className="text-xl leading-tight font-semibold tabular-nums">
      <motion.span>{text}</motion.span>
    </p>
  )
}

export function formatStat(value: number, unit: StatUnit): string {
  if (unit === "currency") return formatCurrency(Math.round(value))

  return number.format(Math.round(value))
}

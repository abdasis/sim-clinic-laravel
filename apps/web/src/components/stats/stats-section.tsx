import { useEffect } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { RefreshIcon } from "@hugeicons/core-free-icons"
import { motion, useReducedMotion } from "motion/react"

import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import type { StatsPayload, StatUnit } from "#/hooks/use-stats.ts"
import { StatCard } from "./stat-card.tsx"
import { StatTrendChart } from "./stat-trend-chart.tsx"
import { isAlertKpi, statIcon } from "./stat-icons.ts"

interface StatsSectionProps {
  stats?: StatsPayload
  isLoading?: boolean
  isError?: boolean
  isFetching?: boolean
  /** Muat ulang manual; juga terpasang di pintasan `r`. */
  onRefresh?: () => void
  /** Rentang yang dirangkum, untuk teks "Data N hari terakhir". */
  days?: number
  /** Ganti teks rentang seluruhnya, mis. saat trennya mingguan. */
  rangeLabel?: string
  className?: string
}

/**
 * Blok ringkasan di kepala halaman index: sederet kartu KPI dengan sparkline,
 * lalu satu grafik tren lebar di bawahnya.
 */
export function StatsSection({
  stats,
  isLoading,
  isError,
  isFetching,
  onRefresh,
  days = 30,
  rangeLabel,
  className,
}: StatsSectionProps) {
  const { t } = useTrans()
  const reduced = useReducedMotion()

  useRefreshShortcut(onRefresh)

  if (isError) {
    return (
      <section
        className={cn(
          "mb-6 rounded-lg border border-border/50 bg-card p-6 text-center shadow-sm",
          className,
        )}
      >
        <p className="text-sm font-medium">{t("stats.error_title")}</p>
        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
          {t("stats.error_description")}
        </p>
        {onRefresh ? (
          <Button variant="outline" size="sm" className="mt-4" onClick={onRefresh}>
            {t("stats.retry")}
          </Button>
        ) : null}
      </section>
    )
  }

  // Tinggi kerangka disamakan dengan isi aslinya supaya halaman tidak
  // melompat begitu datanya datang.
  if (isLoading || !stats) {
    return (
      <section className={cn("mb-6 space-y-3", className)} aria-busy>
        <span className="sr-only">{t("stats.loading")}</span>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {Array.from({ length: 4 }, (_, index) => (
            <Skeleton key={index} className="h-[122px] w-full rounded-lg" />
          ))}
        </div>
        <Skeleton className="h-[256px] w-full rounded-lg" />
      </section>
    )
  }

  return (
    <section className={cn("mb-6 space-y-3", className)}>
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <div className="flex items-baseline gap-2">
          <h2 className="text-sm font-semibold tracking-tight">
            {t("stats.title")}
          </h2>
          <p className="text-xs text-muted-foreground">
            {rangeLabel ?? t("stats.last_days").replace(":days", String(days))}
          </p>
        </div>

        {onRefresh ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={onRefresh}
                disabled={isFetching}
                aria-label={t("stats.refresh")}
                className="h-7 gap-1.5 px-2 text-xs text-muted-foreground hover:text-foreground"
              >
                <motion.span
                  animate={isFetching && !reduced ? { rotate: 360 } : { rotate: 0 }}
                  transition={
                    isFetching && !reduced
                      ? { duration: 0.9, repeat: Infinity, ease: "linear" }
                      : { duration: 0.2 }
                  }
                  className="inline-flex"
                >
                  <HugeiconsIcon icon={RefreshIcon} strokeWidth={2} className="size-3.5" />
                </motion.span>
                {t("stats.refresh")}
              </Button>
            </TooltipTrigger>
            <TooltipContent className="flex items-center gap-2">
              {t("stats.refresh_hint")}
              <Kbd>r</Kbd>
            </TooltipContent>
          </Tooltip>
        ) : null}
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {stats.kpis.map((kpi, index) => (
          <StatCard
            key={kpi.key}
            label={kpi.label}
            value={kpi.value}
            unit={kpi.unit}
            icon={statIcon(kpi.key)}
            alert={isAlertKpi(kpi.key, kpi.value)}
            spark={stats.trend}
            index={index}
          />
        ))}
      </div>

      <StatTrendChart
        points={stats.trend}
        label={stats.trend_label}
        unit={trendUnit(stats.trend_metric)}
      />
    </section>
  )
}

/** Hanya tren omzet yang berupa uang; sisanya cacahan. */
function trendUnit(metric: string): StatUnit {
  return metric === "transactions_revenue" ? "currency" : "count"
}

/**
 * Pintasan `r` tanpa modifier — tidak bentrok dengan bawaan browser dan tidak
 * aktif saat kursor sedang berada di kolom isian.
 */
function useRefreshShortcut(onRefresh?: () => void) {
  useEffect(() => {
    if (!onRefresh) return

    const onKey = (event: KeyboardEvent) => {
      if (event.metaKey || event.ctrlKey || event.altKey) return

      const target = event.target as HTMLElement | null
      if (target?.isContentEditable) return
      if (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) return

      if (event.key === "r") onRefresh()
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [onRefresh])
}

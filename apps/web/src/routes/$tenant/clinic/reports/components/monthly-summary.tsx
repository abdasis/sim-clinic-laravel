import {
  ChartUpIcon,
  Coins01Icon,
  UserGroupIcon,
  Wallet01Icon,
} from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import type { MonthlyData } from "./monthly-types.ts"
import { ReportStatCard } from "./report-stat-card.tsx"

/**
 * Komposisi pendapatan: satu batang bertumpuk treatment vs produk.
 *
 * Dua deret, jadi legendanya wajib ada dan angkanya ditulis langsung — bukan
 * hanya beda warna, supaya tetap terbaca bagi yang sulit membedakan warna.
 */
function RevenueMix({
  totals,
  averageTicket,
}: {
  totals: MonthlyData["totals"]
  averageTicket: number
}) {
  const { t } = useTrans()
  const base = totals.revenue > 0 ? totals.revenue : 1
  const share = (value: number) => `${(value / base) * 100}%`

  const legend = [
    { label: t("report.treatment_short"), value: totals.treatment, dot: "bg-primary" },
    { label: t("report.product_short"), value: totals.product, dot: "bg-chart-2" },
  ]

  return (
    <section className="rounded-lg border border-border/60 bg-card p-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h3 className="text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
          {t("report.revenue_mix")}
        </h3>
        <p className="text-xs text-muted-foreground">
          {t("report.average_ticket")}{" "}
          <span className="font-medium text-foreground tabular-nums">
            {formatCurrency(averageTicket)}
          </span>
        </p>
      </div>

      <div
        className="mt-3 flex h-2 gap-0.5 overflow-hidden rounded-full bg-muted"
        role="img"
        aria-label={`${t("report.treatment_short")} ${formatCurrency(totals.treatment)}, ${t("report.product_short")} ${formatCurrency(totals.product)}`}
      >
        <span className="bg-primary transition-[width] duration-300" style={{ width: share(totals.treatment) }} />
        <span className="bg-chart-2 transition-[width] duration-300" style={{ width: share(totals.product) }} />
      </div>

      <dl className="mt-3 flex flex-wrap gap-x-6 gap-y-1">
        {legend.map((item) => (
          <div key={item.label} className="flex items-baseline gap-2">
            <span aria-hidden className={cn("size-2 shrink-0 rounded-full", item.dot)} />
            <dt className="text-xs text-muted-foreground">{item.label}</dt>
            <dd className="text-xs font-medium tabular-nums">{formatCurrency(item.value)}</dd>
          </div>
        ))}
      </dl>
    </section>
  )
}

/** Empat angka pembuka plus komposisi pendapatan. */
export function MonthlySummary({ report }: { report: MonthlyData }) {
  const { t } = useTrans()
  const { summary, totals } = report

  return (
    <div className="space-y-3">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <ReportStatCard
          icon={Coins01Icon}
          accent="bg-primary"
          label={t("report.income")}
          value={formatCurrency(totals.revenue)}
          hint={`${t("report.treatment_short")} ${formatCurrency(totals.treatment)} · ${t("report.product_short")} ${formatCurrency(totals.product)}`}
        />
        <ReportStatCard
          icon={Wallet01Icon}
          accent="bg-chart-cat-2"
          label={t("report.expense")}
          value={formatCurrency(report.expenses.total)}
          hint={`${report.expenses.by_category.length} ${t("report.expense_categories")}`}
        />
        <ReportStatCard
          emphasis
          icon={ChartUpIcon}
          accent="bg-primary"
          label={t("report.net_profit")}
          value={formatCurrency(report.net_profit)}
          hint={t("report.net_profit_formula")}
        />
        <ReportStatCard
          icon={UserGroupIcon}
          accent="bg-chart-cat-1"
          label={t("report.visits")}
          value={String(summary.visits)}
          hint={`${summary.patients} ${t("report.patients")} · ${summary.new_patients} ${t("report.new_patients")}`}
        />
      </div>

      <RevenueMix totals={totals} averageTicket={summary.average_ticket} />
    </div>
  )
}

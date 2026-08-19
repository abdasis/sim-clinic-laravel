import { HugeiconsIcon } from "@hugeicons/react"
import type { IconSvgElement } from "@hugeicons/react"
import { Award01Icon, Invoice01Icon, Wallet01Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import type { MonthlyData } from "./monthly-types.ts"

interface BreakdownRow {
  label: string
  value: number
}

/**
 * Satu blok rincian: kepala berwarna, baris label + nilai, lalu total.
 *
 * Tiap baris diberi batang tipis sepanjang porsinya terhadap yang terbesar —
 * cukup untuk melihat mana yang mendominasi tanpa harus membandingkan angka
 * satu per satu, dan tidak menggantikan angkanya yang tetap ditulis.
 */
function BreakdownCard({
  icon,
  accent,
  bar,
  title,
  rows,
  totalLabel,
  total,
  emptyText,
  note,
}: {
  icon: IconSvgElement
  accent: string
  bar: string
  title: string
  rows: BreakdownRow[]
  totalLabel: string
  total: number
  emptyText: string
  note?: string
}) {
  const peak = Math.max(1, ...rows.map((row) => Math.abs(row.value)))

  return (
    <section className="flex flex-col overflow-hidden rounded-lg border border-border/60 bg-card">
      {/* Warnanya jadi latar tipis dan lencana ikon, bukan blok pekat dengan
          teks putih: oranye dan kuning kategori tidak pernah cukup kontras
          untuk teks kecil, sementara sebagai latar 12% keduanya aman. */}
      <header
        className={cn(
          "flex items-center gap-2 border-b px-3 py-2",
          accent,
        )}
      >
        <span
          aria-hidden
          className={cn("flex size-5 shrink-0 items-center justify-center rounded", bar)}
        >
          <HugeiconsIcon icon={icon} strokeWidth={2.2} className="size-3 text-white" />
        </span>
        <h3 className="text-2xs font-semibold tracking-wider uppercase">{title}</h3>
      </header>

      <dl className="flex-1 divide-y divide-border/40">
        {rows.length === 0 ? (
          <p className="px-3 py-4 text-xs text-muted-foreground italic">{emptyText}</p>
        ) : (
          rows.map((row) => (
            <div key={row.label} className="relative px-3 py-2">
              <span
                aria-hidden
                className={cn("absolute inset-y-0 left-0 opacity-10", bar)}
                style={{ width: `${(Math.abs(row.value) / peak) * 100}%` }}
              />
              <div className="relative flex items-baseline justify-between gap-3">
                <dt className="truncate text-sm text-muted-foreground">{row.label}</dt>
                <dd className="shrink-0 text-sm tabular-nums">{formatCurrency(row.value)}</dd>
              </div>
            </div>
          ))
        )}
      </dl>

      <div className="flex items-baseline justify-between gap-3 border-t border-border/60 bg-muted/40 px-3 py-2">
        <span className="text-2xs font-semibold tracking-wider uppercase">{totalLabel}</span>
        <span className="font-semibold tabular-nums">{formatCurrency(total)}</span>
      </div>

      {note ? (
        <p className="border-t border-border/40 px-3 py-2 text-2xs text-muted-foreground italic">
          {note}
        </p>
      ) : null}
    </section>
  )
}

/** Tiga blok rincian berdampingan: dana masuk, pengeluaran, fee terapis. */
export function MonthlyBreakdown({ report }: { report: MonthlyData }) {
  const { t } = useTrans()

  return (
    <div className="grid gap-3 lg:grid-cols-3">
      <BreakdownCard
        icon={Invoice01Icon}
        accent="border-chart-cat-1/25 bg-chart-cat-1/12"
        bar="bg-chart-cat-1"
        title={t("report.fund_breakdown")}
        rows={report.payments.map((payment) => ({
          label: payment.method_label,
          value: payment.total,
        }))}
        totalLabel={t("report.total_fund")}
        total={report.payments.reduce((sum, payment) => sum + payment.total, 0)}
        emptyText={t("report.no_payment")}
      />

      <BreakdownCard
        icon={Wallet01Icon}
        accent="border-chart-cat-2/25 bg-chart-cat-2/12"
        bar="bg-chart-cat-2"
        title={t("report.expense_breakdown")}
        rows={report.expenses.by_category.map((expense) => ({
          label: expense.category_label,
          value: expense.total,
        }))}
        totalLabel={t("report.total_expense")}
        total={report.expenses.total}
        emptyText={t("report.no_expense")}
      />

      <BreakdownCard
        icon={Award01Icon}
        accent="border-chart-cat-4/30 bg-chart-cat-4/14"
        bar="bg-chart-cat-4"
        title={t("report.fee_breakdown")}
        rows={report.commission.rows.map((row) => ({
          label: row.therapist_name,
          value: row.total,
        }))}
        totalLabel={t("report.total_fee")}
        total={report.commission.total}
        emptyText={t("report.no_fee")}
        note={t("report.fee_note")}
      />
    </div>
  )
}

/**
 * Penutup laporan: satu angka yang paling dicari, plus cara memperolehnya.
 * Rumusnya ditulis apa adanya supaya tidak ada yang menebak-nebak apakah fee
 * terapis sudah ikut dipotong atau belum.
 */
export function MonthlyNetProfit({ report }: { report: MonthlyData }) {
  const { t } = useTrans()

  return (
    <section className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-primary px-4 py-3 text-primary-foreground">
      <div>
        <p className="text-2xs font-semibold tracking-wider text-primary-foreground/80 uppercase">
          {t("report.net_profit")}
        </p>
        <p className="mt-0.5 text-xs text-primary-foreground/70">
          {t("report.income")} {formatCurrency(report.totals.revenue)} −{" "}
          {t("report.expense").toLowerCase()} {formatCurrency(report.expenses.total)}
        </p>
      </div>
      <p className="text-2xl font-semibold tabular-nums">
        {formatCurrency(report.net_profit)}
      </p>
    </section>
  )
}

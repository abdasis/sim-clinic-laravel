import { EmptyState } from "#/components/ui/empty-state.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatAmount, formatDate } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import type { MonthlyRow, MonthlyTotals } from "./monthly-types.ts"

/** Nilai nol ditulis sebagai garis: nol dan "tidak ada" beda arti di laporan. */
function Amount({ value, className }: { value: number; className?: string }) {
  if (!value) {
    return <span className="text-muted-foreground/60">—</span>
  }

  return <span className={cn("tabular-nums", className)}>{formatAmount(value)}</span>
}

/** Daftar panjang dipotong; isi penuhnya tetap bisa dibaca lewat tooltip. */
function Items({ value }: { value: string }) {
  if (!value) {
    return <span className="text-muted-foreground/60">—</span>
  }

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className="line-clamp-2 cursor-default">{value}</span>
      </TooltipTrigger>
      <TooltipContent className="max-w-72 text-pretty">{value}</TooltipContent>
    </Tooltip>
  )
}

/**
 * Rincian per kunjungan — inti laporan klinik.
 *
 * Susunan kolomnya sengaja mengikuti lembar yang biasa dipakai klinik supaya
 * yang membacanya tidak perlu belajar tata letak baru: tanggal, terapis,
 * pasien, cara bayar, apa yang dikerjakan, lalu deret uang dari treatment
 * sampai total bersih.
 */
export function MonthlyVisitsTable({
  rows,
  totals,
}: {
  rows: MonthlyRow[]
  totals: MonthlyTotals
}) {
  const { t } = useTrans()

  if (rows.length === 0) {
    return (
      <EmptyState
        className="py-10"
        illustration="reports"
        title={t("report.empty")}
        description={t("report.empty_desc")}
      />
    )
  }

  const headCell = "px-3 py-2 text-2xs font-semibold tracking-wider uppercase"
  const numHead = cn(headCell, "text-right")
  const cell = "px-3 py-2 align-top"

  return (
    <section className="overflow-hidden rounded-lg border border-border/60">
      <header className="flex items-baseline justify-between gap-3 border-b border-border/60 bg-muted/40 px-3 py-2">
        <h3 className="text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
          {t("report.patient_rows")}
        </h3>
        <p className="text-xs text-muted-foreground tabular-nums">
          {rows.length} {t("report.visits").toLowerCase()}
        </p>
      </header>

      <div className="overflow-x-auto">
        <table className="w-full min-w-5xl border-collapse text-sm">
          <thead>
            <tr className="bg-primary text-primary-foreground">
              <th className={cn(headCell, "w-10 text-center")}>#</th>
              <th className={cn(headCell, "text-left")}>{t("expense.spent_at")}</th>
              <th className={cn(headCell, "text-left")}>{t("commission.therapist")}</th>
              <th className={cn(headCell, "text-left")}>{t("pos.patient")}</th>
              <th className={cn(headCell, "text-left")}>{t("report.payment_col")}</th>
              <th className={cn(headCell, "text-left")}>{t("report.services_col")}</th>
              <th className={cn(headCell, "text-left")}>{t("report.products_col")}</th>
              <th className={numHead}>{t("report.treatment_amount")}</th>
              <th className={numHead}>{t("report.product_amount")}</th>
              <th className={numHead}>{t("pos.total")}</th>
              <th className={numHead}>{t("report.fee_col")}</th>
              <th className={numHead}>{t("report.net_col")}</th>
            </tr>
          </thead>

          <tbody>
            {rows.map((row, index) => (
              <tr
                key={row.invoice_number ?? index}
                className="border-b border-border/50 transition-colors duration-100 odd:bg-card even:bg-muted/25 hover:bg-primary/5"
              >
                <td className={cn(cell, "text-center text-xs text-muted-foreground tabular-nums")}>
                  {index + 1}
                </td>
                <td className={cn(cell, "text-xs whitespace-nowrap tabular-nums")}>
                  {formatDate(row.issued_at)}
                </td>
                <td className={cn(cell, "whitespace-nowrap")}>
                  {row.therapist_name ?? <span className="text-muted-foreground/60">—</span>}
                </td>
                <td className={cn(cell, "font-medium whitespace-nowrap")}>
                  {row.patient_name ?? <span className="text-muted-foreground/60">—</span>}
                </td>
                <td className={cell}>
                  {row.payment_label ? (
                    <span className="inline-flex rounded-full border border-chart-cat-1/30 bg-chart-cat-1/10 px-2 py-0.5 text-2xs whitespace-nowrap">
                      {row.payment_label}
                    </span>
                  ) : (
                    <span className="text-muted-foreground/60">—</span>
                  )}
                </td>
                <td className={cn(cell, "max-w-56 text-xs")}>
                  <Items value={row.services} />
                </td>
                <td className={cn(cell, "max-w-56 text-xs")}>
                  <Items value={row.products} />
                </td>
                <td className={cn(cell, "text-right text-xs")}>
                  <Amount value={row.treatment_amount} />
                </td>
                <td className={cn(cell, "text-right text-xs")}>
                  <Amount value={row.product_amount} />
                </td>
                <td className={cn(cell, "text-right font-medium")}>
                  <Amount value={row.total} />
                </td>
                <td className={cn(cell, "text-right text-xs")}>
                  <Amount value={row.fee_amount} className="text-muted-foreground" />
                </td>
                <td className={cn(cell, "text-right font-medium")}>
                  <Amount value={row.net_amount} />
                </td>
              </tr>
            ))}
          </tbody>

          <tfoot>
            <tr className="border-t-2 border-primary bg-primary/5 font-semibold">
              <td className={cn(cell, "text-right text-2xs tracking-wider uppercase")} colSpan={7}>
                {t("pos.total")}
              </td>
              <td className={cn(cell, "text-right tabular-nums")}>
                {formatAmount(totals.treatment)}
              </td>
              <td className={cn(cell, "text-right tabular-nums")}>
                {formatAmount(totals.product)}
              </td>
              <td className={cn(cell, "text-right tabular-nums")}>
                {formatAmount(totals.revenue)}
              </td>
              <td className={cn(cell, "text-right tabular-nums")}>
                {formatAmount(totals.fee)}
              </td>
              <td className={cn(cell, "text-right tabular-nums")}>
                {formatAmount(totals.net)}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <p className="border-t border-border/60 bg-muted/20 px-3 py-2 text-2xs text-muted-foreground">
        {t("report.amount_note")}
      </p>
    </section>
  )
}

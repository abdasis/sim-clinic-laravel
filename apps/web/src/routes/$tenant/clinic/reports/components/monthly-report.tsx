import { useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { FileDownloadIcon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDownload, apiGet } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"

interface MonthlyRow {
  issued_at: string
  therapist_name?: string | null
  patient_name?: string | null
  services: string
  products: string
  treatment_amount: number
  product_amount: number
  total: number
}

interface MonthlyData {
  rows: MonthlyRow[]
  totals: { treatment: number; product: number; revenue: number }
  payments: { method: string; method_label: string; total: number }[]
  expenses: {
    by_category: { category: string; category_label: string; total: number }[]
    total: number
  }
  commission: { rows: { therapist_id: number; therapist_name: string; total: number }[]; total: number }
  net_profit: number
}

interface MonthlyReportProps {
  tenant: string
  from: string
  to: string
}

function BreakdownCard({
  title,
  rows,
  totalLabel,
  total,
  note,
}: {
  title: string
  rows: { label: string; value: number }[]
  totalLabel: string
  total: number
  note?: string
}) {
  return (
    <section className="rounded-lg border border-border/50 bg-card p-4">
      <h3 className="text-sm font-semibold">{title}</h3>
      <dl className="mt-2 space-y-1">
        {rows.map((row) => (
          <div key={row.label} className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="truncate text-muted-foreground">{row.label}</dt>
            <dd className="shrink-0 tabular-nums">{formatCurrency(row.value)}</dd>
          </div>
        ))}
      </dl>
      <div className="mt-2 flex items-baseline justify-between gap-3 border-t border-border/50 pt-2">
        <span className="text-sm font-medium">{totalLabel}</span>
        <span className="font-semibold tabular-nums">{formatCurrency(total)}</span>
      </div>
      {note ? <p className="mt-2 text-xs text-muted-foreground italic">{note}</p> : null}
    </section>
  )
}

/**
 * Laporan bulanan gabungan: baris per kunjungan, rincian dana, pengeluaran,
 * fee terapis, keuntungan bersih — plus unduhan Excel/PDF yang tata
 * letaknya mengikuti laporan spreadsheet klinik.
 */
export function MonthlyReport({ tenant, from, to }: MonthlyReportProps) {
  const { t } = useTrans()
  const [downloading, setDownloading] = useState<"xlsx" | "pdf" | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ["reports", tenant, "monthly", from, to],
    queryFn: () =>
      apiGet<{ data: MonthlyData; meta?: { empty?: boolean } }>(
        `/${tenant}/clinic/reports/monthly`,
        { from, to },
      ),
  })

  const download = async (format: "xlsx" | "pdf") => {
    setDownloading(format)
    try {
      await apiDownload(
        `/${tenant}/clinic/reports/monthly/export`,
        { from, to, format },
        `laporan-bulanan-${from}-sd-${to}.${format === "xlsx" ? "xlsx" : "pdf"}`,
      )
    } catch (err) {
      toast.error((err as ApiError).message)
    } finally {
      setDownloading(null)
    }
  }

  if (isLoading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  const report = data?.data

  if (!report) return null

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        {/* Keuntungan bersih adalah angka yang paling dicari — ditaruh paling
            depan, bukan disembunyikan di ujung tabel. */}
        <div>
          <p className="text-xs text-muted-foreground">{t("report.net_profit")}</p>
          <p className="text-2xl font-semibold tabular-nums">
            {formatCurrency(report.net_profit)}
          </p>
        </div>
        <div className="flex gap-2">
          {(["xlsx", "pdf"] as const).map((format) => (
            <Tooltip key={format}>
              <TooltipTrigger asChild>
                <Button
                  variant="outline"
                  size="sm"
                  className="gap-1.5"
                  disabled={downloading !== null}
                  onClick={() => void download(format)}
                >
                  <HugeiconsIcon
                    icon={FileDownloadIcon}
                    strokeWidth={2}
                    className="size-3.5"
                  />
                  {downloading === format
                    ? t("general.loading")
                    : format === "xlsx"
                      ? t("report.export_excel")
                      : t("report.export_pdf")}
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                {format === "xlsx" ? t("report.export_excel") : t("report.export_pdf")}
              </TooltipContent>
            </Tooltip>
          ))}
        </div>
      </div>

      {report.rows.length === 0 ? (
        <EmptyState
          className="py-10"
          illustration="reports"
          title={t("report.empty")}
          description={t("report.empty_desc")}
        />
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border/50">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t("expense.spent_at")}</TableHead>
                <TableHead>{t("commission.therapist")}</TableHead>
                <TableHead>{t("pos.patient")}</TableHead>
                <TableHead>{t("report.services_col")}</TableHead>
                <TableHead>{t("report.products_col")}</TableHead>
                <TableHead className="text-right">{t("report.treatment_amount")}</TableHead>
                <TableHead className="text-right">{t("report.product_amount")}</TableHead>
                <TableHead className="text-right">{t("pos.total")}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {report.rows.map((row, index) => (
                <TableRow key={index}>
                  <TableCell className="text-xs tabular-nums whitespace-nowrap">
                    {formatDate(row.issued_at)}
                  </TableCell>
                  <TableCell>{row.therapist_name ?? "-"}</TableCell>
                  <TableCell className="font-medium">{row.patient_name ?? "-"}</TableCell>
                  <TableCell className="max-w-52 truncate text-xs">{row.services || "-"}</TableCell>
                  <TableCell className="max-w-52 truncate text-xs">{row.products || "-"}</TableCell>
                  <TableCell className="text-right tabular-nums">
                    {formatCurrency(row.treatment_amount)}
                  </TableCell>
                  <TableCell className="text-right tabular-nums">
                    {formatCurrency(row.product_amount)}
                  </TableCell>
                  <TableCell className="text-right font-medium tabular-nums">
                    {formatCurrency(row.total)}
                  </TableCell>
                </TableRow>
              ))}
              <TableRow className="border-t-2 font-semibold">
                <TableCell colSpan={5} className="text-right">
                  {t("pos.total")}
                </TableCell>
                <TableCell className="text-right tabular-nums">
                  {formatCurrency(report.totals.treatment)}
                </TableCell>
                <TableCell className="text-right tabular-nums">
                  {formatCurrency(report.totals.product)}
                </TableCell>
                <TableCell className="text-right tabular-nums">
                  {formatCurrency(report.totals.revenue)}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-3">
        <BreakdownCard
          title={t("report.fund_breakdown")}
          rows={report.payments.map((payment) => ({
            label: payment.method_label,
            value: payment.total,
          }))}
          totalLabel={t("report.total_revenue")}
          total={report.payments.reduce((sum, payment) => sum + payment.total, 0)}
        />
        <BreakdownCard
          title={t("expense.summary")}
          rows={report.expenses.by_category.map((expense) => ({
            label: expense.category_label,
            value: expense.total,
          }))}
          totalLabel={t("report.total_expense")}
          total={report.expenses.total}
        />
        <BreakdownCard
          title={t("commission.title")}
          rows={report.commission.rows.map((row) => ({
            label: row.therapist_name,
            value: row.total,
          }))}
          totalLabel={t("report.total_fee")}
          total={report.commission.total}
          note={t("report.fee_note")}
        />
      </div>
    </div>
  )
}

import { useCallback, useEffect, useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { FileDownloadIcon, Loading03Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDownload, apiGet } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatDate } from "#/lib/format.ts"
import { MonthlyBreakdown, MonthlyNetProfit } from "./monthly-breakdown.tsx"
import { MonthlySummary } from "./monthly-summary.tsx"
import { MonthlyVisitsTable } from "./monthly-visits-table.tsx"
import type { MonthlyData } from "./monthly-types.ts"

type ExportFormat = "xlsx" | "pdf"

/** Huruf tunggal, sepola dengan `r` untuk muat ulang di halaman yang sama. */
const SHORTCUT: Record<ExportFormat, string> = { xlsx: "e", pdf: "p" }

interface MonthlyReportProps {
  tenant: string
  from: string
  to: string
}

/**
 * Pintasan huruf tunggal tanpa modifier: tidak bentrok dengan bawaan browser
 * dan mati saat kursor sedang berada di kolom isian — halaman ini punya dua
 * kolom tanggal tepat di atasnya.
 */
function useExportShortcuts(onExport: (format: ExportFormat) => void) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.metaKey || event.ctrlKey || event.altKey) return

      const target = event.target as HTMLElement | null
      if (target?.isContentEditable) return
      if (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) return

      const match = (Object.keys(SHORTCUT) as ExportFormat[]).find(
        (format) => SHORTCUT[format] === event.key,
      )

      if (match) onExport(match)
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [onExport])
}

function ExportButton({
  format,
  label,
  busy,
  disabled,
  onClick,
}: {
  format: ExportFormat
  label: string
  busy: boolean
  disabled: boolean
  onClick: () => void
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className="gap-1.5"
          disabled={disabled}
          onClick={onClick}
        >
          <HugeiconsIcon
            icon={busy ? Loading03Icon : FileDownloadIcon}
            strokeWidth={2}
            className={busy ? "size-3.5 animate-spin" : "size-3.5"}
          />
          {label}
        </Button>
      </TooltipTrigger>
      <TooltipContent className="flex items-center gap-2">
        {label}
        <Kbd>{SHORTCUT[format]}</Kbd>
      </TooltipContent>
    </Tooltip>
  )
}

function MonthlySkeleton() {
  return (
    <div className="space-y-3">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {[0, 1, 2, 3].map((index) => (
          <Skeleton key={index} className="h-[104px] w-full rounded-lg" />
        ))}
      </div>
      <Skeleton className="h-20 w-full rounded-lg" />
      <Skeleton className="h-72 w-full rounded-lg" />
    </div>
  )
}

/**
 * Laporan bulanan gabungan, mengikuti susunan lembar yang selama ini dipakai
 * klinik: angka pembuka, rincian per kunjungan lengkap dengan fee terapis dan
 * total bersih, rincian dana masuk, pengeluaran, fee, lalu keuntungan bersih —
 * plus unduhan Excel/PDF dengan tata letak yang sama.
 */
export function MonthlyReport({ tenant, from, to }: MonthlyReportProps) {
  const { t } = useTrans()
  const [downloading, setDownloading] = useState<ExportFormat | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ["reports", tenant, "monthly", from, to],
    queryFn: () =>
      apiGet<{ data: MonthlyData; meta?: { empty?: boolean } }>(
        `/${tenant}/clinic/reports/monthly`,
        { from, to },
      ),
  })

  const download = useCallback(
    async (format: ExportFormat) => {
      setDownloading((current) => current ?? format)

      try {
        await apiDownload(
          `/${tenant}/clinic/reports/monthly/export`,
          { from, to, format },
          `laporan-bulanan-${from}-sd-${to}.${format}`,
        )
      } catch (err) {
        toast.error((err as ApiError).message)
      } finally {
        setDownloading(null)
      }
    },
    [tenant, from, to],
  )

  const startDownload = useCallback(
    (format: ExportFormat) => {
      // Satu unduhan pada satu waktu: dua permintaan berbarengan menghasilkan
      // dua berkas bernama sama dan menutupi yang mana yang gagal.
      if (downloading !== null) return

      void download(format)
    },
    [download, downloading],
  )

  useExportShortcuts(startDownload)

  if (isLoading) return <MonthlySkeleton />

  if (isError) {
    return (
      <p className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm">
        {t("general.error")}
      </p>
    )
  }

  const report = data?.data

  if (!report) return null

  return (
    <div className="space-y-3">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold">{t("report.monthly_title")}</h2>
          <p className="text-xs text-muted-foreground tabular-nums">
            {formatDate(report.from)} — {formatDate(report.to)}
          </p>
        </div>

        <div className="flex gap-2">
          <ExportButton
            format="xlsx"
            label={t("report.export_excel")}
            busy={downloading === "xlsx"}
            disabled={downloading !== null}
            onClick={() => startDownload("xlsx")}
          />
          <ExportButton
            format="pdf"
            label={t("report.export_pdf")}
            busy={downloading === "pdf"}
            disabled={downloading !== null}
            onClick={() => startDownload("pdf")}
          />
        </div>
      </header>

      <MonthlySummary report={report} />
      <MonthlyVisitsTable rows={report.rows} totals={report.totals} />
      <MonthlyBreakdown report={report} />
      <MonthlyNetProfit report={report} />
    </div>
  )
}

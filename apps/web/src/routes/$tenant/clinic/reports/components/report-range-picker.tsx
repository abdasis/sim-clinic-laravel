import { useState } from "react"
import {
  endOfMonth,
  format,
  startOfMonth,
  subDays,
  subMonths,
} from "date-fns"
import { Calendar03Icon } from "@hugeicons/core-free-icons"
import { HugeiconsIcon } from "@hugeicons/react"

import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { cn } from "#/lib/utils.ts"
import { useTrans } from "#/hooks/use-trans.ts"

export interface ReportRange {
  from: string
  to: string
}

const iso = (date: Date) => format(date, "yyyy-MM-dd")

/**
 * Rentang yang benar-benar ditanyakan berulang, jadi tidak perlu diketik.
 *
 * Urutannya dari yang paling sering: pemilik klinik menengok bulan berjalan
 * hampir tiap hari, dan bulan lalu sekali saat menutup buku.
 */
function presets(): Array<{ key: string; range: ReportRange }> {
  const today = new Date()
  const lastMonth = subMonths(today, 1)

  return [
    { key: "this_month", range: { from: iso(startOfMonth(today)), to: iso(today) } },
    {
      key: "last_month",
      range: {
        from: iso(startOfMonth(lastMonth)),
        to: iso(endOfMonth(lastMonth)),
      },
    },
    { key: "last_7", range: { from: iso(subDays(today, 6)), to: iso(today) } },
    { key: "last_30", range: { from: iso(subDays(today, 29)), to: iso(today) } },
    { key: "today", range: { from: iso(today), to: iso(today) } },
  ]
}

interface ReportRangePickerProps {
  applied: ReportRange | null
  onApply: (range: ReportRange) => void
}

/**
 * Pemilih rentang laporan.
 *
 * Sebelumnya dua kolom tanggal kosong dan satu tombol: membuka laporan bulan
 * berjalan berarti mengetik dua tanggal, tiap kali. Pilihan cepat menutup
 * jalur itu, sementara kolom tanggalnya tetap ada untuk rentang yang tidak
 * jatuh di pola mana pun.
 */
export function ReportRangePicker({
  applied,
  onApply,
}: ReportRangePickerProps) {
  const { t } = useTrans()
  const [from, setFrom] = useState(applied?.from ?? "")
  const [to, setTo] = useState(applied?.to ?? "")

  const quick = presets()
  const custom = { from, to }
  const complete = from !== "" && to !== ""
  // Rentang terbalik ditolak di sini, bukan dibiarkan jadi galat dari server.
  const valid = complete && from <= to

  const apply = (range: ReportRange) => {
    setFrom(range.from)
    setTo(range.to)
    onApply(range)
  }

  return (
    <section className="mb-4 rounded-lg border border-border/60 bg-card p-3">
      <div className="flex flex-wrap items-center gap-1.5">
        <HugeiconsIcon
          icon={Calendar03Icon}
          className="size-4 shrink-0 text-muted-foreground"
        />
        {quick.map((item) => {
          const active =
            applied?.from === item.range.from && applied?.to === item.range.to

          return (
            <button
              key={item.key}
              type="button"
              aria-pressed={active}
              onClick={() => apply(item.range)}
              className={cn(
                "rounded-md border px-2.5 py-1 text-xs transition-colors",
                "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                active
                  ? "border-border bg-muted font-medium text-foreground"
                  : "border-border/50 text-muted-foreground hover:bg-muted/60 hover:text-foreground",
              )}
            >
              {t(`report.preset_${item.key}`)}
            </button>
          )
        })}
      </div>

      <form
        className="mt-3 flex flex-wrap items-end gap-3 border-t border-border/50 pt-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (valid) apply(custom)
        }}
      >
        <div className="grid gap-1.5">
          <Label htmlFor="report-from" className="text-xs">
            {t("report.from")}
          </Label>
          <Input
            id="report-from"
            type="date"
            value={from}
            max={to || undefined}
            onChange={(e) => setFrom(e.target.value)}
            className="h-8 w-auto text-xs"
          />
        </div>
        <div className="grid gap-1.5">
          <Label htmlFor="report-to" className="text-xs">
            {t("report.to")}
          </Label>
          <Input
            id="report-to"
            type="date"
            value={to}
            min={from || undefined}
            onChange={(e) => setTo(e.target.value)}
            className="h-8 w-auto text-xs"
          />
        </div>

        <Tooltip>
          <TooltipTrigger asChild>
            {/* span pembungkus: tombol nonaktif tidak memancarkan event, jadi
                tooltip alasannya tidak akan pernah muncul tanpa ini. */}
            <span>
              <Button type="submit" size="sm" disabled={!valid}>
                {t("report.generate")}
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent>
            {complete && !valid
              ? t("report.range_backwards")
              : t("report.generate_hint")}
          </TooltipContent>
        </Tooltip>
      </form>
    </section>
  )
}

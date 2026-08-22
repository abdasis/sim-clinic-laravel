import {
  ArrowLeft01Icon,
  ArrowRight01Icon,
} from "@hugeicons/core-free-icons"
import { HugeiconsIcon } from "@hugeicons/react"

import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { cn } from "#/lib/utils.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import type { ScheduleView } from "./schedule-range.ts"

interface ScheduleToolbarProps {
  date: string
  view: ScheduleView
  label: string
  onDateChange: (date: string) => void
  onViewChange: (view: ScheduleView) => void
  onStep: (step: number) => void
  onToday: () => void
  /** Aksi milik halaman (setelan, tambah booking) menempel di ujung kanan. */
  children?: React.ReactNode
}

/**
 * Kemudi jadwal: periode di kiri, pilihan tampilan di tengah, aksi di kanan.
 *
 * Sebelumnya hanya ada kolom tanggal dan dua tombol, jadi berpindah minggu
 * berarti menghitung tanggal sendiri. Panah maju-mundur dan tombol "Hari ini"
 * membuat perpindahan yang paling sering dipakai jadi satu ketukan.
 */
export function ScheduleToolbar({
  date,
  view,
  label,
  onDateChange,
  onViewChange,
  onStep,
  onToday,
  children,
}: ScheduleToolbarProps) {
  const { t } = useTrans()

  const views: Array<{ value: ScheduleView; label: string; hint: string }> = [
    { value: "day", label: t("booking.view_day"), hint: "1" },
    { value: "week", label: t("booking.view_week"), hint: "2" },
    { value: "month", label: t("booking.view_month"), hint: "3" },
  ]

  return (
    <div className="mb-4 flex flex-wrap items-center gap-2">
      <div className="flex items-center gap-1">
        <StepButton
          direction="prev"
          label={t("booking.period_prev")}
          onClick={() => onStep(-1)}
        />
        <StepButton
          direction="next"
          label={t("booking.period_next")}
          onClick={() => onStep(1)}
        />
        <Tooltip>
          <TooltipTrigger asChild>
            <Button variant="outline" size="sm" onClick={onToday}>
              {t("booking.today")}
            </Button>
          </TooltipTrigger>
          <TooltipContent className="flex items-center gap-2">
            {t("booking.today")}
            <span className="flex items-center gap-0.5">
              <Kbd>g</Kbd>
              <Kbd>t</Kbd>
            </span>
          </TooltipContent>
        </Tooltip>
      </div>

      <div className="min-w-0">
        <h1 className="truncate text-base font-semibold tracking-tight">
          {label}
        </h1>
      </div>

      <div className="ms-auto flex flex-wrap items-center gap-2">
        <Input
          type="date"
          value={date}
          onChange={(e) => onDateChange(e.target.value)}
          aria-label={t("booking.start_at")}
          className="h-8 w-auto text-xs"
        />

        <div
          role="group"
          aria-label={t("booking.schedule")}
          className="flex items-center gap-0.5 rounded-md border border-border/60 bg-muted/40 p-0.5"
        >
          {views.map((item) => {
            const active = view === item.value

            return (
              <Tooltip key={item.value}>
                <TooltipTrigger asChild>
                  <button
                    type="button"
                    aria-pressed={active}
                    onClick={() => onViewChange(item.value)}
                    className={cn(
                      "rounded-sm px-2.5 py-1 text-xs transition-colors",
                      "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                      active
                        ? "bg-background font-medium text-foreground shadow-sm"
                        : "text-muted-foreground hover:text-foreground",
                    )}
                  >
                    {item.label}
                  </button>
                </TooltipTrigger>
                <TooltipContent className="flex items-center gap-2">
                  {item.label}
                  <Kbd>{item.hint}</Kbd>
                </TooltipContent>
              </Tooltip>
            )
          })}
        </div>

        {children}
      </div>
    </div>
  )
}

function StepButton({
  direction,
  label,
  onClick,
}: {
  direction: "prev" | "next"
  label: string
  onClick: () => void
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          variant="outline"
          size="icon"
          className="size-8"
          aria-label={label}
          onClick={onClick}
        >
          <HugeiconsIcon
            icon={direction === "prev" ? ArrowLeft01Icon : ArrowRight01Icon}
            className="size-4"
          />
        </Button>
      </TooltipTrigger>
      <TooltipContent className="flex items-center gap-2">
        {label}
        <span className="flex items-center gap-0.5">
          <Kbd>g</Kbd>
          <Kbd>{direction === "prev" ? "j" : "k"}</Kbd>
        </span>
      </TooltipContent>
    </Tooltip>
  )
}

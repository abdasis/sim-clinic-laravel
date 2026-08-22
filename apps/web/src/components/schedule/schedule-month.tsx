import { useMemo } from "react"
import {
  eachDayOfInterval,
  endOfMonth,
  endOfWeek,
  format,
  isSameDay,
  isSameMonth,
  parseISO,
  startOfMonth,
  startOfWeek,
} from "date-fns"

import { Card } from "#/components/ui/card.tsx"
import { cn } from "#/lib/utils.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import type { ScheduleBooking } from "./schedule-grid.tsx"

/** Senin dulu, seperti kalender dinding klinik. */
const WEEK_OPTIONS = { weekStartsOn: 1 } as const

/** Selebihnya diringkas jadi "+n lagi" — satu sel tidak muat lebih dari ini. */
const VISIBLE_PER_DAY = 3

const weekdayLabel = new Intl.DateTimeFormat("id-ID", { weekday: "short" })

const STATUS_DOT: Record<string, string> = {
  pending: "bg-amber-500",
  confirmed: "bg-emerald-500",
  done: "bg-muted-foreground",
  cancelled: "bg-destructive",
}

interface ScheduleMonthProps {
  data: ScheduleBooking[]
  /** Tanggal mana pun di bulan yang sedang dilihat. */
  date: string
  /** Menekan satu hari membuka jadwal harinya, bukan sekadar menyorot. */
  onSelectDay: (date: string) => void
}

/**
 * Kalender sebulan penuh: kepadatan jadwal terbaca sekali lihat.
 *
 * Tampilan harian dan mingguan menjawab "siapa jam berapa", pertanyaan yang
 * muncul saat harinya tiba. Yang ditanyakan admin sebelum itu berbeda:
 * minggu mana yang sudah padat, dan tanggal mana yang masih longgar untuk
 * ditawarkan ke pasien. Sebulan sekaligus adalah bentuk yang menjawabnya.
 */
export function ScheduleMonth({
  data,
  date,
  onSelectDay,
}: ScheduleMonthProps) {
  const { t } = useTrans()

  const { days, byDay } = useMemo(() => {
    const base = parseISO(date)
    const days = eachDayOfInterval({
      start: startOfWeek(startOfMonth(base), WEEK_OPTIONS),
      end: endOfWeek(endOfMonth(base), WEEK_OPTIONS),
    })

    const byDay = new Map<string, ScheduleBooking[]>()

    for (const booking of data) {
      const key = format(parseISO(booking.start_at), "yyyy-MM-dd")
      const list = byDay.get(key)

      if (list) list.push(booking)
      else byDay.set(key, [booking])
    }

    for (const list of byDay.values()) {
      list.sort((a, b) => a.start_at.localeCompare(b.start_at))
    }

    return { days, byDay }
  }, [data, date])

  const viewedMonth = parseISO(date)
  const today = new Date()

  return (
    <Card className="gap-0 overflow-hidden py-0">
      <div className="grid grid-cols-7 border-b bg-muted/40">
        {days.slice(0, 7).map((day) => (
          <div
            key={day.toISOString()}
            className="px-2 py-1.5 text-center text-2xs font-medium tracking-wide text-muted-foreground uppercase"
          >
            {weekdayLabel.format(day)}
          </div>
        ))}
      </div>

      <div className="grid grid-cols-7">
        {days.map((day) => {
          const key = format(day, "yyyy-MM-dd")
          const items = byDay.get(key) ?? []
          const outside = !isSameMonth(day, viewedMonth)
          const isToday = isSameDay(day, today)

          return (
            <button
              key={key}
              type="button"
              onClick={() => onSelectDay(key)}
              aria-label={`${t("booking.schedule")} ${key}`}
              className={cn(
                "group flex min-h-24 flex-col gap-1 border-r border-b p-1.5 text-left transition-colors",
                // Garis tepi kanan dan baris terakhir dilepas supaya tidak
                // menggandakan border kartu.
                "[&:nth-child(7n)]:border-r-0 [&:nth-last-child(-n+7)]:border-b-0",
                "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:z-10",
                outside ? "bg-muted/20" : "hover:bg-muted/40",
              )}
            >
              <span className="flex items-center justify-between gap-1">
                <span
                  className={cn(
                    "inline-flex size-5 items-center justify-center rounded-full text-xs tabular-nums",
                    outside && "text-muted-foreground/60",
                    isToday && "bg-primary font-semibold text-primary-foreground",
                    !isToday && !outside && "font-medium",
                  )}
                >
                  {format(day, "d")}
                </span>
                {items.length > 0 ? (
                  <span className="text-xxs tabular-nums text-muted-foreground">
                    {items.length}
                  </span>
                ) : null}
              </span>

              {items.slice(0, VISIBLE_PER_DAY).map((booking) => (
                <span
                  key={booking.id}
                  title={`${booking.patient_name} - ${booking.service_name}`}
                  className={cn(
                    "flex items-center gap-1 rounded-sm px-1 py-0.5 text-2xs",
                    "bg-background ring-1 ring-border/60",
                    outside && "opacity-60",
                  )}
                >
                  <span
                    className={cn(
                      "size-1.5 shrink-0 rounded-full",
                      STATUS_DOT[booking.status] ?? "bg-muted-foreground",
                    )}
                  />
                  <span className="shrink-0 tabular-nums text-muted-foreground">
                    {format(parseISO(booking.start_at), "HH:mm")}
                  </span>
                  <span className="truncate">{booking.patient_name}</span>
                </span>
              ))}

              {items.length > VISIBLE_PER_DAY ? (
                <span className="px-1 text-xxs text-muted-foreground">
                  +{items.length - VISIBLE_PER_DAY} {t("booking.more_bookings")}
                </span>
              ) : null}
            </button>
          )
        })}
      </div>
    </Card>
  )
}

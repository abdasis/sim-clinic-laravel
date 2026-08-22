import { useMemo } from "react"
import { format, isSameDay, parseISO } from "date-fns"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Card } from "#/components/ui/card.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { cn } from "#/lib/utils.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import type { ScheduleBooking } from "#/components/schedule/schedule-grid.tsx"
import { BookingStatusAction } from "./booking-status-action.tsx"

const dayHeading = new Intl.DateTimeFormat("id-ID", {
  weekday: "long",
  day: "numeric",
  month: "long",
})

const STATUS_ACCENT: Record<string, string> = {
  pending: "bg-amber-500",
  confirmed: "bg-emerald-500",
  done: "bg-muted-foreground/50",
  cancelled: "bg-destructive",
}

interface BookingListProps {
  tenant: string
  data: ScheduleBooking[]
  conflictingIds: Set<number>
  onEdit: (id: number) => void
}

/**
 * Daftar booking periode berjalan, dikelompokkan per hari.
 *
 * Sebelumnya satu daftar datar yang cukup untuk sehari, tapi tampilan bulanan
 * membuatnya berpuluh baris tanpa penanda batas hari — tanggal hanya terbaca
 * dari angka kecil di tiap baris. Judul hari yang menempel saat digulir
 * membuat posisi tetap jelas sejauh apa pun daftarnya turun.
 */
export function BookingList({
  tenant,
  data,
  conflictingIds,
  onEdit,
}: BookingListProps) {
  const { t } = useTrans()

  const groups = useMemo(() => {
    const byDay = new Map<string, ScheduleBooking[]>()

    for (const booking of data) {
      const key = format(parseISO(booking.start_at), "yyyy-MM-dd")
      const list = byDay.get(key)

      if (list) list.push(booking)
      else byDay.set(key, [booking])
    }

    return [...byDay.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([day, items]) => ({
        day,
        items: items.sort((a, b) => a.start_at.localeCompare(b.start_at)),
      }))
  }, [data])

  if (groups.length === 0) {
    return (
      <Card className="py-10">
        <EmptyState
          illustration="bookings"
          title={t("booking.empty_title")}
          description={t("booking.empty_desc")}
        />
      </Card>
    )
  }

  const today = new Date()

  return (
    <Card className="gap-0 overflow-hidden py-0">
      {groups.map(({ day, items }) => (
        <section key={day}>
          <header className="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-border/60 bg-muted/60 px-3 py-1.5 backdrop-blur-sm">
            <span className="text-xs font-medium">
              {dayHeading.format(parseISO(day))}
              {isSameDay(parseISO(day), today) ? (
                <span className="ms-2 text-2xs font-normal text-muted-foreground">
                  {t("booking.today")}
                </span>
              ) : null}
            </span>
            <span className="text-2xs tabular-nums text-muted-foreground">
              {items.length}
            </span>
          </header>

          {items.map((booking) => (
            <div
              key={booking.id}
              className="flex flex-wrap items-center gap-3 border-b border-border/40 px-3 py-2 transition-colors last:border-b-0 hover:bg-muted/30"
            >
              <span
                aria-hidden
                className={cn(
                  "h-8 w-0.5 shrink-0 rounded-full",
                  STATUS_ACCENT[booking.status] ?? "bg-muted-foreground/50",
                )}
              />

              <span className="w-24 shrink-0 text-xs tabular-nums text-muted-foreground">
                {format(parseISO(booking.start_at), "HH:mm")} –{" "}
                {format(parseISO(booking.end_at), "HH:mm")}
              </span>

              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium">
                  {booking.patient_name}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                  {booking.service_name} · {booking.assignee_name}
                </span>
              </span>

              <span className="flex items-center gap-2">
                {conflictingIds.has(booking.id) ? (
                  <Badge variant="destructive">{t("booking.conflict")}</Badge>
                ) : null}
                <Badge variant="outline" className="font-normal">
                  {t(`clinic.booking_status.${booking.status}`)}
                </Badge>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => onEdit(booking.id)}
                >
                  {t("general.edit")}
                </Button>
                <BookingStatusAction tenant={tenant} booking={booking} />
              </span>
            </div>
          ))}
        </section>
      ))}
    </Card>
  )
}

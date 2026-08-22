import { createFileRoute, useParams } from "@tanstack/react-router"
import { useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { format, parseISO } from "date-fns"
import { AlarmClockIcon, CalendarCheckIcon } from "@hugeicons/core-free-icons"
import { HugeiconsIcon } from "@hugeicons/react"
import { IndexCta } from "#/components/stats/index-cta.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useStats } from "#/hooks/use-stats.ts"
import { Button } from "#/components/ui/button.tsx"
import { Card } from "#/components/ui/card.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { ScheduleGrid } from "#/components/schedule/schedule-grid.tsx"
import type { ScheduleBooking } from "#/components/schedule/schedule-grid.tsx"
import { ScheduleMonth } from "#/components/schedule/schedule-month.tsx"
import { ScheduleToolbar } from "#/components/schedule/schedule-toolbar.tsx"
import {
  computeRange,
  periodLabel,
  shiftPeriod,
} from "#/components/schedule/schedule-range.ts"
import type { ScheduleView } from "#/components/schedule/schedule-range.ts"
import { useDigitShortcut, useGoToShortcut } from "#/hooks/use-go-to-shortcut.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { BookingFormDialog } from "./components/booking-form-dialog.tsx"
import { BookingList } from "./components/booking-list.tsx"
import { ReminderSettingsDialog } from "./components/reminder-settings-dialog.tsx"

export const Route = createFileRoute("/$tenant/clinic/bookings/")({
  component: BookingsPage,
})

const today = () => format(new Date(), "yyyy-MM-dd")

function BookingsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/bookings/" })
  const { t } = useTrans()
  // Bulanan sebagai bawaan: yang pertama ditanyakan admin saat membuka
  // jadwal adalah seberapa padat bulan ini, bukan siapa jam berapa hari ini.
  const [view, setView] = useState<ScheduleView>("month")
  const [date, setDate] = useState(today)
  const [formOpen, setFormOpen] = useState(false)
  const stats = useStats({ tenant, module: "bookings" })
  const [editingId, setEditingId] = useState<number | undefined>(undefined)
  const [reminderOpen, setReminderOpen] = useState(false)

  const step = (delta: number) => setDate((d) => shiftPeriod(d, view, delta))

  useGoToShortcut("r", () => setReminderOpen(true))
  useGoToShortcut("t", () => setDate(today()))
  useGoToShortcut("j", () => step(-1))
  useGoToShortcut("k", () => step(1))
  useDigitShortcut("1", () => setView("day"))
  useDigitShortcut("2", () => setView("week"))
  useDigitShortcut("3", () => setView("month"))

  const { from, to } = computeRange(date, view)

  const { data, isLoading } = useQuery({
    queryKey: ["bookings-schedule", tenant, from, to, view],
    queryFn: () =>
      apiGet<{ data: ScheduleBooking[] }>(
        `/${tenant}/clinic/bookings/schedule`,
        { from, to, view },
      ),
  })

  const bookings = data?.data ?? []

  // Bentrokan: penanggung jawab sama dan rentang waktunya saling menimpa.
  const conflictingIds = findConflictingIds(bookings)

  const openForm = (id?: number) => {
    setEditingId(id)
    setFormOpen(true)
  }

  return (
    <div>
      <IndexCta
        icon={CalendarCheckIcon}
        tone="palm"
        mascot="clinic"
        title={t("cta.bookings.title")}
        description={t("cta.bookings.description")}
        actionLabel={t("cta.bookings.action")}
        onAction={() => openForm()}
      />

      <StatsSection
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
      />

      <ScheduleToolbar
        date={date}
        view={view}
        label={periodLabel(date, view)}
        onDateChange={setDate}
        onViewChange={setView}
        onStep={step}
        onToday={() => setDate(today())}
      >
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="outline"
              size="icon"
              className="size-8"
              aria-label={t("booking.message_settings")}
              onClick={() => setReminderOpen(true)}
            >
              <HugeiconsIcon icon={AlarmClockIcon} className="size-4" />
            </Button>
          </TooltipTrigger>
          <TooltipContent className="flex items-center gap-2">
            {t("booking.message_settings")}
            <span className="flex items-center gap-0.5">
              <Kbd>g</Kbd>
              <Kbd>r</Kbd>
            </span>
          </TooltipContent>
        </Tooltip>
        <Button size="sm" onClick={() => openForm()}>
          {t("booking.add")}
        </Button>
      </ScheduleToolbar>

      <BookingFormDialog
        tenant={tenant}
        bookingId={editingId}
        open={formOpen}
        onOpenChange={setFormOpen}
      />

      <ReminderSettingsDialog
        tenant={tenant}
        open={reminderOpen}
        onOpenChange={setReminderOpen}
      />

      {isLoading ? (
        <Card className="items-center justify-center py-10">
          <Spinner />
        </Card>
      ) : view === "month" ? (
        <ScheduleMonth
          data={bookings}
          date={date}
          // Menekan satu tanggal turun ke jadwal jamnya: kalender menjawab
          // "kapan longgar", tampilan harian menjawab "siapa jam berapa".
          onSelectDay={(day) => {
            setDate(day)
            setView("day")
          }}
        />
      ) : (
        <ScheduleGrid data={bookings} view={view} />
      )}

      <h2 className="mt-6 mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
        {t("booking.title")}
      </h2>

      <BookingList
        tenant={tenant}
        data={bookings}
        conflictingIds={conflictingIds}
        onEdit={openForm}
      />
    </div>
  )
}

/**
 * Dua booking dianggap bentrok bila penanggung jawabnya sama dan rentang
 * waktunya saling menimpa. Booking batal tidak dihitung.
 */
function findConflictingIds(bookings: ScheduleBooking[]): Set<number> {
  const conflicting = new Set<number>()
  const active = bookings.filter((b) => b.status !== "cancelled")

  for (let i = 0; i < active.length; i++) {
    for (let j = i + 1; j < active.length; j++) {
      const a = active[i]
      const b = active[j]

      if (a.assignee_id !== b.assignee_id) continue

      const overlap =
        parseISO(a.start_at) < parseISO(b.end_at) &&
        parseISO(a.end_at) > parseISO(b.start_at)

      if (overlap) {
        conflicting.add(a.id)
        conflicting.add(b.id)
      }
    }
  }

  return conflicting
}

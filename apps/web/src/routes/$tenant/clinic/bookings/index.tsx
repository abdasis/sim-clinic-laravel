import { createFileRoute, useParams } from "@tanstack/react-router"
import { useState } from "react"
import { useQuery } from "@tanstack/react-query"
import {
  format,
  parseISO,
  startOfWeek,
  endOfWeek,
} from "date-fns"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Card } from "#/components/ui/card.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { ScheduleGrid } from "#/components/schedule/schedule-grid.tsx"
import type { ScheduleBooking } from "#/components/schedule/schedule-grid.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { BookingFormModal } from "./components/booking-form-modal.tsx"
import { BookingStatusAction } from "./components/booking-status-action.tsx"

export const Route = createFileRoute("/$tenant/clinic/bookings/")({
  component: BookingsPage,
})

type View = "day" | "week"

function computeRange(date: string, view: View): { from: string; to: string } {
  const base = parseISO(date)
  if (view === "week") {
    return {
      from: format(startOfWeek(base, { weekStartsOn: 1 }), "yyyy-MM-dd"),
      to: format(endOfWeek(base, { weekStartsOn: 1 }), "yyyy-MM-dd"),
    }
  }
  return { from: date, to: date }
}

function BookingsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/bookings/" })
  const { t } = useTrans()
  const [view, setView] = useState<View>("day")
  const [date, setDate] = useState(() => format(new Date(), "yyyy-MM-dd"))

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

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/services", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("booking.title") },
        ]}
      />
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-xl font-semibold">{t("booking.schedule")}</h1>
        <div className="flex items-center gap-2">
          <Input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className="w-auto"
          />
          <div className="flex overflow-hidden rounded-md border">
            <Button
              variant={view === "day" ? "default" : "ghost"}
              size="sm"
              className="rounded-none"
              onClick={() => setView("day")}
            >
              {t("booking.view_day")}
            </Button>
            <Button
              variant={view === "week" ? "default" : "ghost"}
              size="sm"
              className="rounded-none"
              onClick={() => setView("week")}
            >
              {t("booking.view_week")}
            </Button>
          </div>
          <BookingFormModal tenant={tenant} />
        </div>
      </div>

      {isLoading ? (
        <Card className="items-center justify-center py-10">
          <Spinner />
        </Card>
      ) : (
        <ScheduleGrid data={bookings} view={view} />
      )}

      <h2 className="mt-6 mb-2 text-sm font-semibold text-muted-foreground">
        {t("booking.title")}
      </h2>
      <Card className="divide-y py-0">
        {bookings.length === 0 ? (
          <div className="p-4 text-sm text-muted-foreground">
            {t("general.no_data")}
          </div>
        ) : (
          bookings.map((booking) => (
            <div
              key={booking.id}
              className="flex flex-wrap items-center justify-between gap-2 p-3"
            >
              <div className="min-w-0">
                <div className="text-sm font-medium">
                  {booking.patient_name}
                </div>
                <div className="text-xs text-muted-foreground">
                  {booking.service_name} · {booking.assignee_name}
                </div>
                <div className="text-xs text-muted-foreground">
                  {format(parseISO(booking.start_at), "dd/MM HH:mm")} –{" "}
                  {format(parseISO(booking.end_at), "HH:mm")}
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Badge variant="outline">
                  {t(`clinic.booking_status.${booking.status}`)}
                </Badge>
                <BookingStatusAction tenant={tenant} booking={booking} />
              </div>
            </div>
          ))
        )}
      </Card>
    </div>
  )
}

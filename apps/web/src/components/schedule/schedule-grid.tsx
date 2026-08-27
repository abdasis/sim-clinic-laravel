import { format, parseISO, getHours } from "date-fns"
import { Card } from "#/components/ui/card.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import { EmptyState } from "#/components/ui/empty-state.tsx"

export interface ScheduleBooking {
  id: number
  patient_name: string
  service_name: string
  assignee_id: number
  assignee_name: string
  start_at: string
  end_at: string
  status: string
  /** Booking yang sudah punya rekam medis atau nota tidak boleh dihapus. */
  can_delete?: boolean
}

interface ScheduleGridProps {
  data: ScheduleBooking[]
  view: "day" | "week"
}

interface GridColumn {
  key: string
  label: string
}

const DEFAULT_START_HOUR = 8
const DEFAULT_END_HOUR = 18

/**
 * Warna tepi kiri kartu, bukan lencana penuh: di sel sesempit ini lencana
 * memakan ruang nama pasien, sedangkan garis tipis tetap terbaca sekilas.
 */
const STATUS_ACCENT: Record<string, string> = {
  pending: "border-l-amber-500",
  confirmed: "border-l-emerald-500",
  done: "border-l-muted-foreground/40",
  cancelled: "border-l-destructive",
}

/** Kunci kolom sebuah booking sesuai mode tampilan (T-schedule). */
function columnKeyOf(booking: ScheduleBooking, view: "day" | "week"): string {
  return view === "day"
    ? String(booking.assignee_id)
    : format(parseISO(booking.start_at), "yyyy-MM-dd")
}

/** Kolom unik (assignee untuk day, hari untuk week) terurut. */
function buildColumns(
  data: ScheduleBooking[],
  view: "day" | "week",
): GridColumn[] {
  const seen = new Map<string, GridColumn>()
  for (const booking of data) {
    const key = columnKeyOf(booking, view)
    if (seen.has(key)) continue
    const label =
      view === "day"
        ? booking.assignee_name
        : format(parseISO(booking.start_at), "EEE dd/MM")
    seen.set(key, { key, label })
  }
  return [...seen.values()].sort((a, b) => a.key.localeCompare(b.key))
}

/** Rentang jam yang perlu ditampilkan berdasarkan data (fallback 8–18). */
function buildHours(data: ScheduleBooking[]): number[] {
  let min = DEFAULT_START_HOUR
  let max = DEFAULT_END_HOUR
  if (data.length > 0) {
    min = 23
    max = 0
    for (const booking of data) {
      const startHour = getHours(parseISO(booking.start_at))
      const endHour = getHours(parseISO(booking.end_at))
      if (startHour < min) min = startHour
      if (endHour > max) max = endHour
    }
    if (min > max) {
      min = DEFAULT_START_HOUR
      max = DEFAULT_END_HOUR
    }
  }
  const hours: number[] = []
  for (let h = min; h <= max; h += 1) hours.push(h)
  return hours
}

export function ScheduleGrid({ data, view }: ScheduleGridProps) {
  const { t } = useTrans()
  const columns = buildColumns(data, view)
  const hours = buildHours(data)

  if (columns.length === 0) {
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

  const cells = new Map<string, ScheduleBooking[]>()
  for (const booking of data) {
    const key = `${getHours(parseISO(booking.start_at))}|${columnKeyOf(
      booking,
      view,
    )}`
    const list = cells.get(key)
    if (list) list.push(booking)
    else cells.set(key, [booking])
  }

  const gridTemplateColumns = `64px repeat(${columns.length}, minmax(140px, 1fr))`

  return (
    <Card className="gap-0 overflow-x-auto py-0">
      <div className="min-w-max">
        <div
          className="sticky top-0 z-10 grid border-b bg-muted/40 backdrop-blur-sm"
          style={{ gridTemplateColumns }}
        >
          <div className="px-2 py-1.5 text-2xs font-medium tracking-wide text-muted-foreground uppercase">
            {t("booking.hour")}
          </div>
          {columns.map((column) => (
            <div
              key={column.key}
              className="truncate border-l border-border/60 px-2 py-1.5 text-xs font-medium"
              title={column.label}
            >
              {column.label}
            </div>
          ))}
        </div>
        {hours.map((hour) => (
          <div
            key={hour}
            className="grid border-b border-border/50 last:border-b-0"
            style={{ gridTemplateColumns }}
          >
            <div className="px-2 py-1.5 text-2xs tabular-nums text-muted-foreground">
              {String(hour).padStart(2, "0")}:00
            </div>
            {columns.map((column) => {
              const items = cells.get(`${hour}|${column.key}`) ?? []
              return (
                <div
                  key={column.key}
                  className="min-h-14 space-y-1 border-l border-border/60 p-1"
                >
                  {items.map((booking) => (
                    <ScheduleCell key={booking.id} booking={booking} />
                  ))}
                </div>
              )
            })}
          </div>
        ))}
      </div>
    </Card>
  )
}

function ScheduleCell({ booking }: { booking: ScheduleBooking }) {
  const { t } = useTrans()

  return (
    <div
      title={`${booking.patient_name} - ${booking.service_name}`}
      className={cn(
        "rounded-sm border border-l-2 border-border/60 bg-background p-1.5 text-xs",
        "shadow-sm transition-colors hover:bg-muted/40",
        STATUS_ACCENT[booking.status] ?? "border-l-muted-foreground/40",
      )}
    >
      <div className="truncate font-medium">{booking.patient_name}</div>
      <div className="truncate text-muted-foreground">
        {booking.service_name}
      </div>
      <div className="mt-1 flex items-center justify-between gap-1">
        <span className="tabular-nums text-xxs text-muted-foreground">
          {format(parseISO(booking.start_at), "HH:mm")}–
          {format(parseISO(booking.end_at), "HH:mm")}
        </span>
        <span className="text-xxs text-muted-foreground">
          {t(`clinic.booking_status.${booking.status}`)}
        </span>
      </div>
    </div>
  )
}

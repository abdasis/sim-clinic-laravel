import { Link } from "@tanstack/react-router"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { ScheduleEntry } from "#/hooks/use-dashboard.ts"

type BadgeVariant = React.ComponentProps<typeof Badge>["variant"]

const STATUS_VARIANTS: Record<string, BadgeVariant> = {
  pending: "outline",
  confirmed: "secondary",
  done: "default",
}

export function TodaySchedule({
  tenant,
  entries,
}: {
  tenant: string
  entries: ScheduleEntry[]
}) {
  const { t } = useTrans()

  if (entries.length === 0) {
    return (
      <div className="py-10 text-center">
        <p className="text-sm text-muted-foreground">
          {t("dashboard.empty_schedule")}
        </p>
        <Button asChild variant="outline" size="sm" className="mt-3">
          <Link to="/$tenant/clinic/bookings" params={{ tenant }}>
            {t("dashboard.view_all")}
          </Link>
        </Button>
      </div>
    )
  }

  return (
    <ul className="divide-y divide-border/50">
      {entries.map((entry) => (
        <li
          key={entry.id}
          className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0"
        >
          <span className="w-12 shrink-0 text-sm font-medium tabular-nums">
            {clockOf(entry.start_at)}
          </span>

          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-medium">
              {entry.patient_name ?? "-"}
            </p>
            <p className="truncate text-xs text-muted-foreground">
              {entry.service_name ?? "-"}
              {entry.assignee_name ? ` · ${entry.assignee_name}` : ""}
            </p>
          </div>

          <Badge
            variant={STATUS_VARIANTS[entry.status] ?? "secondary"}
            className="shrink-0"
          >
            {entry.status_label ?? entry.status}
          </Badge>
        </li>
      ))}
    </ul>
  )
}

/** Hanya jam dan menit; tanggalnya sudah pasti hari ini. */
function clockOf(value?: string | null): string {
  if (!value) return "-"

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return "-"

  return date.toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  })
}

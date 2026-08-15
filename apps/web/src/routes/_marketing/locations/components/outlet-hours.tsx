import { HugeiconsIcon } from "@hugeicons/react"
import { Clock01Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import {
  useContentLocale,
  useContentText,
} from "#/components/company-profile/locale-context.tsx"
import { useIsMounted } from "#/hooks/use-is-mounted.ts"
import { cn } from "#/lib/utils.ts"
import type { OpeningHour } from "#/lib/clinic-outlet.ts"
import { isOpenAt, todayIndex } from "./opening-hours.ts"

export function OutletHours({ hours }: { hours: OpeningHour[] }) {
  const text = useContentText()
  const isEnglish = useContentLocale() === "en"
  // ponytail: status dihitung dari jam perangkat, jadi hanya boleh dirender
  // setelah mount — server dan klien bisa berada di zona waktu berbeda.
  const mounted = useIsMounted()

  const now = new Date()
  const index = todayIndex(now)
  const isOpen = isOpenAt(hours, now)

  return (
    <section className="rounded-lg border border-border/50 bg-background p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h2 className="flex items-center gap-2 text-sm font-semibold tracking-tight">
          <HugeiconsIcon
            icon={Clock01Icon}
            strokeWidth={2}
            className="size-4 text-muted-foreground"
          />
          {isEnglish ? "Opening hours" : "Jam operasional"}
        </h2>

        {mounted ? (
          <Badge
            variant="outline"
            className={cn(
              "gap-1.5 px-2 text-2xs font-medium",
              isOpen
                ? "border-primary/40 text-primary"
                : "border-border/60 text-muted-foreground",
            )}
          >
            <span
              className={cn(
                "size-1.5 rounded-full",
                isOpen ? "bg-primary" : "bg-muted-foreground/60",
              )}
            />
            {isOpen
              ? isEnglish
                ? "Open now"
                : "Buka sekarang"
              : isEnglish
                ? "Closed now"
                : "Sedang tutup"}
          </Badge>
        ) : null}
      </div>

      <dl className="divide-y divide-border/40">
        {hours.map((entry, entryIndex) => {
          const isToday = mounted && entryIndex === index

          return (
            <div
              key={text(entry.day)}
              className={cn(
                "flex items-center justify-between gap-4 py-2 text-sm",
                isToday && "font-medium",
              )}
            >
              <dt className={cn(!isToday && "text-muted-foreground")}>
                {text(entry.day)}
                {isToday ? (
                  <span className="ml-1.5 text-2xs font-normal text-muted-foreground">
                    {isEnglish ? "today" : "hari ini"}
                  </span>
                ) : null}
              </dt>
              <dd
                className={cn(
                  "tabular-nums",
                  entry.hours ? "" : "text-muted-foreground",
                  !isToday && entry.hours && "text-muted-foreground",
                )}
              >
                {entry.hours ?? (isEnglish ? "Closed" : "Tutup")}
              </dd>
            </div>
          )
        })}
      </dl>
    </section>
  )
}

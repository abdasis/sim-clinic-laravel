import { HugeiconsIcon } from "@hugeicons/react"
import {
  Call02Icon,
  Clock01Icon,
  Location01Icon,
} from "@hugeicons/core-free-icons"

import type {
  CompanySettings,
  OperatingHour,
} from "#/hooks/use-company-profile.ts"
import { cn } from "#/lib/utils.ts"

interface InfoBarLabels {
  openNow: string
  openToday: string
  closedToday: string
}

/** Kunci hari pada setelan klinik, urut mengikuti Date#getDay(). */
const DAY_KEYS = [
  "sunday",
  "monday",
  "tuesday",
  "wednesday",
  "thursday",
  "friday",
  "saturday",
]

/**
 * Pita fakta tepat di bawah hero: buka atau tidak hari ini, di mana, dan
 * nomor mana yang bisa dihubungi.
 *
 * Ini tiga hal yang paling sering dicari pengunjung klinik, dan sebelumnya
 * ketiganya baru ketemu setelah menggulir sampai kaki halaman. Statusnya
 * dihitung dari jam operasional yang tersimpan — bukan klaim yang diketik
 * sekali lalu basi.
 */
export function ClinicInfoBar({
  settings,
  labels,
  className,
}: {
  settings: CompanySettings | null
  labels: InfoBarLabels
  className?: string
}) {
  if (!settings) return null

  const today = todayHours(settings.operating_hours ?? null)
  const hasContact = Boolean(settings.address || settings.phone)

  if (today === null && ! hasContact) return null

  return (
    <div className={cn("border-b border-border/50 bg-background", className)}>
      <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center gap-x-8 gap-y-2.5 px-4 py-3 text-sm sm:px-6">
        {today !== null ? (
          <span className="flex items-center gap-2">
            {/* Titik status, bukan sekadar teks: keadaan buka/tutup terbaca
                dari sudut mata sebelum kalimatnya sempat dibaca. */}
            <span
              aria-hidden="true"
              className={cn(
                "size-2 shrink-0 rounded-full",
                today.isOpen ? "bg-emerald-500" : "bg-muted-foreground/40",
              )}
            />
            <span
              className={cn(
                "font-medium",
                today.isOpen ? "text-foreground" : "text-muted-foreground",
              )}
            >
              {today.isOpen
                ? `${labels.openToday} ${today.range}`
                : labels.closedToday}
            </span>
          </span>
        ) : null}

        {settings.address ? (
          <span className="flex min-w-0 items-center gap-2 text-muted-foreground">
            <HugeiconsIcon
              icon={Location01Icon}
              strokeWidth={1.8}
              className="size-4 shrink-0 text-foreground/40"
            />
            <span className="truncate">{settings.address}</span>
          </span>
        ) : null}

        {settings.phone ? (
          <a
            href={`tel:${settings.phone.replace(/[^\d+]/g, "")}`}
            className="flex items-center gap-2 text-muted-foreground transition-colors hover:text-foreground"
          >
            <HugeiconsIcon
              icon={Call02Icon}
              strokeWidth={1.8}
              className="size-4 shrink-0 text-foreground/40"
            />
            <span className="tabular-nums">{settings.phone}</span>
          </a>
        ) : null}

        {today !== null && ! today.isOpen ? (
          <span className="flex items-center gap-2 text-muted-foreground">
            <HugeiconsIcon
              icon={Clock01Icon}
              strokeWidth={1.8}
              className="size-4 shrink-0 text-foreground/40"
            />
            <span>{labels.openNow}</span>
          </span>
        ) : null}
      </div>
    </div>
  )
}

/**
 * Jam buka hari ini, atau null bila kliniknya belum mengisi jadwal.
 *
 * Rentangnya ditulis dengan titik seperti kebiasaan jam di Indonesia
 * ("09.00–20.00"), bukan titik dua yang terbaca sebagai label.
 */
function todayHours(
  hours: Record<string, OperatingHour> | null,
): { isOpen: boolean; range: string } | null {
  if (!hours) return null

  const day = hours[DAY_KEYS[new Date().getDay()]]

  if (!day) return null

  if (! day.is_open || ! day.open || ! day.close) {
    return { isOpen: false, range: "" }
  }

  return {
    isOpen: true,
    range: `${day.open.replace(":", ".")}–${day.close.replace(":", ".")}`,
  }
}

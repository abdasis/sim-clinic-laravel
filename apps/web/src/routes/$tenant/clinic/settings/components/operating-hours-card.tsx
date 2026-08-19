import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Switch } from "#/components/ui/switch.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"

export interface DayHours {
  is_open: boolean
  open: string
  close: string
}

export type OperatingHours = Record<string, DayHours>

/**
 * Kunci hari sengaja bahasa Inggris: itu yang tersimpan di basis data dan
 * dibaca `CompanyProfileSetting::isOpenAt()`. Labelnya saja yang diterjemahkan.
 */
export const DAY_KEYS = [
  "monday",
  "tuesday",
  "wednesday",
  "thursday",
  "friday",
  "saturday",
  "sunday",
] as const

export const DEFAULT_DAY: DayHours = { is_open: false, open: "09:00", close: "17:00" }

export function emptyOperatingHours(): OperatingHours {
  return Object.fromEntries(DAY_KEYS.map((day) => [day, { ...DEFAULT_DAY }]))
}

/**
 * Gabungkan jam tersimpan dengan tujuh hari bawaan.
 *
 * Hari yang belum pernah disimpan tetap harus punya baris — kalau tidak,
 * klinik yang dulu hanya mengisi Senin akan mendapati enam hari lainnya hilang
 * dari layar dan tidak bisa diisi sama sekali.
 */
export function mergeOperatingHours(saved?: Partial<OperatingHours> | null): OperatingHours {
  const base = emptyOperatingHours()

  if (!saved) return base

  for (const day of DAY_KEYS) {
    const stored = saved[day]
    if (!stored) continue

    base[day] = {
      is_open: Boolean(stored.is_open),
      open: stored.open || DEFAULT_DAY.open,
      close: stored.close || DEFAULT_DAY.close,
    }
  }

  return base
}

/**
 * Tujuh baris jam buka-tutup, satu rentang per hari.
 *
 * Hari tutup tetap ditampilkan barisnya, hanya kolom jamnya yang dimatikan —
 * menyembunyikannya membuat orang mengira harinya hilang, dan menyalakannya
 * kembali jadi tidak jelas caranya.
 */
export function OperatingHoursCard({
  value,
  onChange,
  disabled,
}: {
  value: OperatingHours
  onChange: (next: OperatingHours) => void
  disabled?: boolean
}) {
  const { t } = useTrans()

  const update = (day: string, patch: Partial<DayHours>) =>
    onChange({ ...value, [day]: { ...value[day], ...patch } })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("company_profile.operating_hours")}</CardTitle>
        <p className="mt-1 text-xs text-pretty text-muted-foreground">
          {t("company_profile.operating_hours_hint")}
        </p>
      </CardHeader>

      <CardContent className="divide-y divide-border/50 py-0">
        {DAY_KEYS.map((day) => {
          const hours = value[day]
          const closed = !hours.is_open

          return (
            <div
              key={day}
              className="flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5 transition-colors duration-150"
            >
              {/* Pemicu tooltipnya span pembungkus, bukan Switch-nya sendiri:
                  `asChild` menimpa data-state milik Switch dengan status buka-
                  tutup tooltip, dan warna saklarnya justru bergantung pada
                  atribut itu — hasilnya saklar transparan yang tak terlihat. */}
              <Tooltip>
                <TooltipTrigger asChild>
                  <span className="inline-flex">
                    <Switch
                      checked={hours.is_open}
                      disabled={disabled}
                      aria-label={t(`company_profile.days.${day}`)}
                      onCheckedChange={(next) => update(day, { is_open: next })}
                    />
                  </span>
                </TooltipTrigger>
                <TooltipContent>{t("company_profile.operating_toggle_hint")}</TooltipContent>
              </Tooltip>

              <span
                className={cn(
                  "w-20 shrink-0 text-sm font-medium transition-colors duration-150",
                  closed && "text-muted-foreground",
                )}
              >
                {t(`company_profile.days.${day}`)}
              </span>

              {closed ? (
                <span className="text-xs text-muted-foreground italic">
                  {t("company_profile.operating_closed_day")}
                </span>
              ) : (
                <div className="flex items-center gap-2">
                  <Input
                    type="time"
                    value={hours.open}
                    disabled={disabled}
                    aria-label={`${t("company_profile.operating_open")} — ${t(`company_profile.days.${day}`)}`}
                    className="h-8 w-32 tabular-nums"
                    onChange={(event) => update(day, { open: event.target.value })}
                  />
                  <span aria-hidden className="text-xs text-muted-foreground">
                    &ndash;
                  </span>
                  <Input
                    type="time"
                    value={hours.close}
                    disabled={disabled}
                    aria-label={`${t("company_profile.operating_close")} — ${t(`company_profile.days.${day}`)}`}
                    className="h-8 w-32 tabular-nums"
                    onChange={(event) => update(day, { close: event.target.value })}
                  />
                </div>
              )}
            </div>
          )
        })}
      </CardContent>
    </Card>
  )
}

import type { OpeningHour } from "#/lib/clinic-outlet.ts"

/** "09.00–20.00" atau "09:00 - 20:00" -> menit sejak tengah malam. */
export function parseRange(range: string): { open: number; close: number } | null {
  const match = /(\d{1,2})[.:](\d{2})\s*[–-]\s*(\d{1,2})[.:](\d{2})/.exec(range)
  if (!match) return null

  return {
    open: Number(match[1]) * 60 + Number(match[2]),
    close: Number(match[3]) * 60 + Number(match[4]),
  }
}

/**
 * Daftar jam dimulai dari Senin, sedangkan `getDay()` menaruh Minggu di 0 —
 * pergeseran ini yang menyelaraskan keduanya.
 */
export function todayIndex(now: Date): number {
  return (now.getDay() + 6) % 7
}

/** Apakah outlet sedang buka pada waktu tertentu. */
export function isOpenAt(hours: OpeningHour[], now: Date): boolean {
  const today = hours[todayIndex(now)]
  if (!today?.hours) return false

  const range = parseRange(today.hours)
  if (!range) return false

  const minutes = now.getHours() * 60 + now.getMinutes()

  return minutes >= range.open && minutes < range.close
}

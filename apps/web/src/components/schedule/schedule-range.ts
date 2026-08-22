import {
  addDays,
  addMonths,
  addWeeks,
  endOfMonth,
  endOfWeek,
  format,
  parseISO,
  startOfMonth,
  startOfWeek,
} from "date-fns"

export type ScheduleView = "day" | "week" | "month"

export interface ScheduleRange {
  from: string
  to: string
}

/** Senin, seperti kalender dinding klinik — bukan Minggu ala bawaan Amerika. */
const WEEK_OPTIONS = { weekStartsOn: 1 } as const

const iso = (date: Date) => format(date, "yyyy-MM-dd")

const monthLabel = new Intl.DateTimeFormat("id-ID", {
  month: "long",
  year: "numeric",
})

const dayLabel = new Intl.DateTimeFormat("id-ID", {
  weekday: "long",
  day: "numeric",
  month: "long",
  year: "numeric",
})

const dayShort = new Intl.DateTimeFormat("id-ID", {
  day: "numeric",
  month: "short",
})

/**
 * Rentang tanggal yang perlu diambil dari server untuk sebuah tampilan.
 *
 * Tampilan bulan sengaja meminta lebih dari sebulan: kotak kalender selalu
 * dimulai Senin dan berakhir Minggu, jadi hari titipan dari bulan sebelum dan
 * sesudahnya ikut terisi — kalau tidak, baris pertama dan terakhir terlihat
 * kosong padahal ada bookingnya.
 */
export function computeRange(date: string, view: ScheduleView): ScheduleRange {
  const base = parseISO(date)

  if (view === "month") {
    return {
      from: iso(startOfWeek(startOfMonth(base), WEEK_OPTIONS)),
      to: iso(endOfWeek(endOfMonth(base), WEEK_OPTIONS)),
    }
  }

  if (view === "week") {
    return {
      from: iso(startOfWeek(base, WEEK_OPTIONS)),
      to: iso(endOfWeek(base, WEEK_OPTIONS)),
    }
  }

  return { from: date, to: date }
}

/** Maju atau mundur satu periode sesuai tampilan yang sedang dipakai. */
export function shiftPeriod(
  date: string,
  view: ScheduleView,
  step: number,
): string {
  const base = parseISO(date)

  if (view === "month") return iso(addMonths(base, step))
  if (view === "week") return iso(addWeeks(base, step))

  return iso(addDays(base, step))
}

/**
 * Judul periode yang dibaca staf, mis. "Agustus 2026" atau "12 - 18 Agu".
 * Ditulis lengkap supaya tidak perlu menghitung sendiri dari kolom tanggal.
 */
export function periodLabel(date: string, view: ScheduleView): string {
  const base = parseISO(date)

  if (view === "month") return monthLabel.format(base)

  if (view === "week") {
    const from = startOfWeek(base, WEEK_OPTIONS)
    const to = endOfWeek(base, WEEK_OPTIONS)

    return `${dayShort.format(from)} - ${dayShort.format(to)} ${format(to, "yyyy")}`
  }

  return dayLabel.format(base)
}

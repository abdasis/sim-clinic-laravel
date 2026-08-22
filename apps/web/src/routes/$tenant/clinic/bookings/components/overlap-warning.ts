import { formatDate } from "#/lib/format.ts"

export interface OverlapWarning {
  booking_id?: number
  patient_name?: string | null
  /** Cap waktu ISO8601 dari server. */
  start_at: string
  end_at: string
}

/**
 * Rentang satu booking yang bentrok, dalam satu baris pendek.
 *
 * Tanggalnya ditulis sekali dan hanya jam selesainya yang menyusul: bentrok
 * selalu terjadi di hari yang sama, jadi mengulang tanggalnya dua kali hanya
 * memanjangkan teks yang dibaca sekilas di dalam toast.
 */
export function overlapWhen(warning: OverlapWarning): string {
  const start = new Date(warning.start_at)
  const end = new Date(warning.end_at)

  if (Number.isNaN(start.getTime())) return "-"

  const time = (value: Date) =>
    Number.isNaN(value.getTime())
      ? "-"
      : value.toLocaleTimeString("id-ID", {
          hour: "2-digit",
          minute: "2-digit",
        })

  return `${formatDate(warning.start_at)}, ${time(start)} - ${time(end)}`
}

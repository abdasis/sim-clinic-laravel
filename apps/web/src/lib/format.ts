const idr = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
})

/** Format angka ke Rupiah (tanpa desimal). */
export function formatCurrency(value: number): string {
  return idr.format(Number.isFinite(value) ? value : 0)
}

const dateOnly = new Intl.DateTimeFormat("id-ID", {
  day: "2-digit",
  month: "short",
  year: "numeric",
})

const dateWithTime = new Intl.DateTimeFormat("id-ID", {
  day: "2-digit",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
})

/** Tanggal saja, mis. "14 Agu 2026". Nilai kosong/rusak jadi "-". */
export function formatDate(value?: string | null): string {
  return formatWith(dateOnly, value)
}

/** Tanggal dan jam, mis. "14 Agu 2026, 09.30". Nilai kosong/rusak jadi "-". */
export function formatDateTime(value?: string | null): string {
  return formatWith(dateWithTime, value)
}

function formatWith(formatter: Intl.DateTimeFormat, value?: string | null): string {
  if (!value) return "-"

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? "-" : formatter.format(date)
}

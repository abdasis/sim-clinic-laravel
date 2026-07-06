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

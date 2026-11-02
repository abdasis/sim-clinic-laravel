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

/**
 * Satuan ringkas Indonesia, dari yang terbesar. "M" dan "T" ditulis kapital
 * sebagaimana lazim di laporan keuangan; "rb" dan "jt" huruf kecil.
 */
const SATUAN = [
  { batas: 1e12, lambang: "T" },
  { batas: 1e9, lambang: "M" },
  { batas: 1e6, lambang: "jt" },
  { batas: 1e3, lambang: "rb" },
] as const

const satuDesimal = new Intl.NumberFormat("id-ID", {
  minimumFractionDigits: 0,
  maximumFractionDigits: 1,
})

/**
 * Rupiah yang dipendekkan: "Rp1,2 jt" alih-alih "Rp 1.234.567".
 *
 * Dipakai hanya saat kartunya terlalu sempit untuk nominal penuh. Di kartu
 * KPI selebar 140px — yang terjadi saat lima kartu berbagi area 700px —
 * "Rp 12.450.000" membungkus jadi dua baris dan tingginya ikut meloncat,
 * sehingga sebaris kartu tidak lagi rata.
 *
 * Yang dipendekkan hanya tampilannya. Angka pastinya tetap ada di `title`
 * elemennya dan di laporan, karena nominal yang dibulatkan tidak boleh jadi
 * satu-satunya yang bisa dibaca.
 *
 * ponytail: pembulatannya satu angka di belakang koma, cukup untuk kartu
 * ringkas. Kalau nanti dipakai di tempat yang menuntut ketelitian lebih,
 * tambahkan parameter jumlah desimalnya - jangan dibulatkan lebih kasar.
 */
export function formatCurrencyCompact(value: number): string {
  const angka = Number.isFinite(value) ? value : 0
  const besaran = Math.abs(angka)

  for (const { batas, lambang } of SATUAN) {
    if (besaran >= batas) {
      const tanda = angka < 0 ? "-" : ""

      return `${tanda}Rp${satuDesimal.format(besaran / batas)} ${lambang}`
    }
  }

  // Di bawah seribu tidak ada yang bisa dipendekkan; nominalnya sudah pendek.
  return formatCurrency(angka)
}

const plainAmount = new Intl.NumberFormat("id-ID", {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
})

/**
 * Angka berpemisah ribuan tanpa "Rp" — dipakai di nota, yang sudah menyebut
 * mata uangnya sekali di kepala kolom sehingga tiap baris tidak perlu mengulang.
 */
export function formatAmount(value: number): string {
  return plainAmount.format(Number.isFinite(value) ? value : 0)
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

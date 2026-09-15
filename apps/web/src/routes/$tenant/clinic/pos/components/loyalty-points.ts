/**
 * Hitungan poin loyalitas di sisi kasir, sebelum notanya disimpan.
 *
 * Meniru App\Support\LoyaltyPoints: floor, bukan pembulatan — dengan tarif
 * Rp10.000, belanja Rp19.000 tetap 1 poin karena belum genap Rp20.000.
 * Murni tampilan; server menghitung ulang sendiri dengan tarif yang sama,
 * jadi angka di sini bisa berbeda kalau pembayarannya nanti dicicil.
 *
 * Tarifnya diterima sebagai parameter, bukan konstanta: tiap klinik menyetel
 * angkanya sendiri (lihat LoyaltySetting di server) dan layar kasir
 * mengambilnya lewat API.
 */
export interface LoyaltyRates {
  /** Rupiah belanja untuk mendapat satu poin. */
  earn_rate: number
  /** Rupiah potongan dari satu poin yang ditukar. */
  redeem_rate: number
  /** Tukar paling sedikit sekian poin. */
  min_redeem: number
}

/**
 * Dipakai selama tarif klinik belum selesai diambil.
 *
 * Nilainya sama dengan bawaan di server supaya layar tidak pernah menampilkan
 * angka yang tidak pernah berlaku di mana pun — dan begitu jawabannya tiba,
 * angkanya diganti tanpa kasir sempat menekan simpan.
 */
export const DEFAULT_RATES: LoyaltyRates = {
  earn_rate: 10_000,
  redeem_rate: 1_000,
  min_redeem: 10,
}

/** Tarif nol akan membuat pembagian meledak; jatuh ke bawaan bila ada. */
function safeRate(rate: number, fallback: number): number {
  return Number.isFinite(rate) && rate > 0 ? rate : fallback
}

export function loyaltyPointsPreview(
  payableTotal: number,
  rates: LoyaltyRates = DEFAULT_RATES,
): number {
  return Math.floor(
    Math.max(0, payableTotal) / safeRate(rates.earn_rate, DEFAULT_RATES.earn_rate),
  )
}

/** Nilai rupiah dari sejumlah poin yang ditukar. */
export function redeemValue(
  points: number,
  rates: LoyaltyRates = DEFAULT_RATES,
): number {
  return (
    Math.max(0, Math.floor(points)) *
    safeRate(rates.redeem_rate, DEFAULT_RATES.redeem_rate)
  )
}

/**
 * Pangkas penukaran supaya tidak melebihi tagihannya.
 *
 * Poin memotong yang harus dibayar, ia bukan uang yang bisa diambil pulang
 * sebagai kembalian — jadi kelebihannya dipangkas, dan sisanya tetap
 * tersimpan di saldo pasien.
 */
export function capToBill(
  points: number,
  payable: number,
  rates: LoyaltyRates = DEFAULT_RATES,
): number {
  return Math.min(
    Math.max(0, Math.floor(points)),
    Math.floor(
      Math.max(0, payable) /
        safeRate(rates.redeem_rate, DEFAULT_RATES.redeem_rate),
    ),
  )
}

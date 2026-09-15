/**
 * Perkiraan poin loyalitas dari satu nota, sebelum disimpan.
 *
 * Meniru App\Support\LoyaltyPoints: floor, bukan pembulatan — Rp19.000
 * tetap 1 poin, bukan 2. Murni tampilan; server menghitung ulang sendiri
 * tepat saat nota benar-benar lunas (lihat PayTransactionAction), jadi
 * angka di sini bisa berbeda kalau pembayarannya nanti dicicil.
 *
 * ponytail: kedua tarif menggandakan konstanta di App\Support\LoyaltyPoints.
 * Duplikasi disengaja supaya kasir melihat angkanya sebelum menekan simpan,
 * tanpa menunggu jawaban server. Mengubah tarif di sana wajib diikutkan ke
 * sini; saat tarifnya jadi setelan per klinik, nilainya ikut turun lewat API
 * dan berkas ini tinggal membacanya.
 */
const RATE = 10_000

/** Rupiah potongan per satu poin yang ditukar. */
const REDEEM_RATE = 1_000

/** Tukar paling sedikit segini — menahan penukaran receh. */
export const MIN_REDEEM = 10

export function loyaltyPointsPreview(payableTotal: number): number {
  return Math.floor(Math.max(0, payableTotal) / RATE)
}

/** Nilai rupiah dari sejumlah poin yang ditukar. */
export function redeemValue(points: number): number {
  return Math.max(0, Math.floor(points)) * REDEEM_RATE
}

/**
 * Pangkas penukaran supaya tidak melebihi tagihannya.
 *
 * Poin memotong yang harus dibayar, ia bukan uang yang bisa diambil pulang
 * sebagai kembalian — jadi kelebihannya dipangkas, dan sisanya tetap
 * tersimpan di saldo pasien.
 */
export function capToBill(points: number, payable: number): number {
  return Math.min(
    Math.max(0, Math.floor(points)),
    Math.floor(Math.max(0, payable) / REDEEM_RATE),
  )
}

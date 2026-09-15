/**
 * Perkiraan poin loyalitas dari satu nota, sebelum disimpan.
 *
 * Meniru App\Support\LoyaltyPoints: floor, bukan pembulatan — Rp19.000
 * tetap 1 poin, bukan 2. Murni tampilan; server menghitung ulang sendiri
 * tepat saat nota benar-benar lunas (lihat PayTransactionAction), jadi
 * angka di sini bisa berbeda kalau pembayarannya nanti dicicil.
 */
const RATE = 10_000

export function loyaltyPointsPreview(payableTotal: number): number {
  return Math.floor(Math.max(0, payableTotal) / RATE)
}

import type { LineItem } from "../hooks/use-pos-cart.ts"

/**
 * Keanggotaan pasien terpilih, sebagaimana dikirim endpoint pasien.
 * Sengaja dibaca dari `patient.membership`, bukan `membership_tier_id`
 * mentah: yang dipakai adalah keanggotaan yang benar-benar berlaku hari
 * ini (tingkat nonaktif atau masa berlaku lewat pulang sebagai null).
 */
export interface MembershipInfo {
  id: number
  name: string
  discount_type: "percent" | "fixed"
  discount_value: number | string
  stacks_with_promo: boolean
}

/**
 * Perkiraan potongan member di layar kasir, sebelum nota disimpan.
 *
 * Meniru aturan yang sama di `App\Support\MemberDiscount` — baris yang
 * sedang promo dilewati kecuali tingkatnya memang boleh menumpuk — supaya
 * angka yang dilihat kasir sebelum menekan bayar sama dengan yang akan
 * tersimpan di nota. Server tetap menghitung ulang sendiri saat menyimpan;
 * ini murni tampilan, bukan sumber kebenaran.
 */
export function memberDiscountAmount(
  items: LineItem[],
  membership: MembershipInfo | null,
): number {
  if (membership === null) return 0

  const base = items.reduce((sum, item) => {
    const onPromo = item.basePrice !== null

    if (onPromo && !membership.stacks_with_promo) return sum

    return sum + item.unitPrice * item.qty
  }, 0)

  if (base <= 0) return 0

  const value = Number(membership.discount_value)
  const raw =
    membership.discount_type === "percent" ? (base * value) / 100 : value

  return Math.min(Math.max(0, Math.round(raw * 100) / 100), base)
}

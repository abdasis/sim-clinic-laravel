import { HugeiconsIcon } from "@hugeicons/react"
import { Calendar03Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { formatDate } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import type { DormantPromo } from "../hooks/use-pos-catalog.ts"

/**
 * Keterangan promo yang terpasang tapi sedang di luar periodenya.
 *
 * Lahir dari satu keluhan: "promo 50% kok hasil akhirnya tidak sesuai".
 * Hitungannya benar — promonya sudah lewat tanggalnya — tapi di layar kasir
 * yang terlihat hanya harga penuh tanpa sepatah keterangan, jadi yang menagih
 * menyimpulkan sistemnya salah.
 *
 * Sengaja redup dan tanpa warna: ini penjelasan, bukan tawaran. Lencana promo
 * yang sedang berlaku harus tetap satu-satunya yang menarik mata di layar ini.
 */
export function PromoDormantNote({
  promo,
  className,
}: {
  promo: DormantPromo
  className?: string
}) {
  const { t } = useTrans()

  const belumMulai = promo.state === "upcoming"
  const tanggal = belumMulai ? promo.startsAt : promo.endsAt

  const teks = t(belumMulai ? "pos.promo_upcoming" : "pos.promo_ended")
    .replace(":name", promo.name)
    .replace(":date", formatDate(tanggal))

  return (
    <p
      className={cn(
        "flex items-center gap-1 text-2xs text-muted-foreground",
        className,
      )}
      // Tanggalnya ikut dibacakan utuh; bagi pembaca layar "berakhir 10 Okt"
      // tanpa konteks baris hanya jadi potongan kalimat.
      title={teks}
    >
      <HugeiconsIcon
        icon={Calendar03Icon}
        strokeWidth={2}
        className="size-3 shrink-0"
      />
      <span className="truncate">{teks}</span>
    </p>
  )
}

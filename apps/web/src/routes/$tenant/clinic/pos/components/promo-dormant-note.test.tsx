import type { ReactElement } from "react"
import { describe, expect, it } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { PromoDormantNote } from "./promo-dormant-note.tsx"
import { toEntry } from "../hooks/use-pos-catalog.ts"

setTranslations({
  pos: {
    promo_ended: "Promo berakhir :date · :name",
    promo_upcoming: "Promo mulai :date · :name",
  },
})

function tampil(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

/**
 * Keterangan promo yang terpasang tapi sedang di luar periodenya.
 *
 * Keluhan yang melahirkannya: "promo 50% kok hasil akhirnya tidak sesuai".
 * Hitungannya benar — promonya sudah lewat tanggalnya — tapi kasir hanya
 * melihat harga penuh tanpa sepatah keterangan.
 */
describe("PromoDormantNote", () => {
  it("menyebut nama promo dan tanggal berakhirnya", () => {
    tampil(
      <PromoDormantNote
        promo={{
          name: "PROMO HEMAT 10.10",
          state: "ended",
          startsAt: "2026-10-01",
          endsAt: "2026-10-10",
        }}
      />,
    )

    const teks = screen.getByText(/PROMO HEMAT 10\.10/).textContent ?? ""

    expect(teks).toContain("berakhir")
    expect(teks).toContain("10 Okt 2026")
    cleanup()
  })

  /** Yang belum mulai dibaca berbeda: itu peluang, bukan penjelasan. */
  it("menyebut tanggal mulai untuk promo yang belum berjalan", () => {
    tampil(
      <PromoDormantNote
        promo={{
          name: "Promo Akhir Tahun",
          state: "upcoming",
          startsAt: "2026-12-01",
          endsAt: "2026-12-31",
        }}
      />,
    )

    const teks = screen.getByText(/Promo Akhir Tahun/).textContent ?? ""

    expect(teks).toContain("Promo mulai")
    expect(teks).toContain("1 Des 2026")
    cleanup()
  })
})

/**
 * Pemetaan dari jawaban server. Harga tidak boleh ikut berubah sedikit pun —
 * keterangan ini semata penjelasan, bukan potongan.
 */
describe("pemetaan promo tidak berlaku dari katalog", () => {
  const baris = {
    id: 7,
    name: "Paket Rejuvanation",
    price: "1000000",
    promo: null,
    promo_dormant: {
      id: 3,
      name: "PROMO HEMAT 10.10",
      state: "ended" as const,
      starts_at: "2026-10-01",
      ends_at: "2026-10-10",
    },
  }

  it("membawa promo tidak berlaku tanpa menyentuh harganya", () => {
    const entry = toEntry(baris, "service")

    expect(entry.price).toBe(1_000_000)
    // Tidak ada harga coret: tidak ada potongan yang terjadi.
    expect(entry.basePrice).toBeNull()
    expect(entry.promoName).toBeNull()
    expect(entry.dormantPromo).toEqual({
      name: "PROMO HEMAT 10.10",
      state: "ended",
      startsAt: "2026-10-01",
      endsAt: "2026-10-10",
    })
  })

  it("mengosongkannya saat server tidak menyebut apa pun", () => {
    const entry = toEntry({ ...baris, promo_dormant: null }, "service")

    expect(entry.dormantPromo).toBeNull()
  })
})

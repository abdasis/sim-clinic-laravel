import { afterEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"
import { RedeemPointsField } from "./redeem-points-field.tsx"

setTranslations({
  pos: {
    points_redeem: "Tukar Poin",
    points_redeem_all: "Tukar semua",
    points_redeem_hint: "Tiap poin memotong Rp1.000 dari tagihan.",
    points_capped: "Poin yang dipakai menyesuaikan tagihan.",
    loyalty_points_unit: "poin",
  },
})

function renderField(props: Partial<React.ComponentProps<typeof RedeemPointsField>>) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <RedeemPointsField
          value=""
          onChange={() => {}}
          balance={50}
          payable={200_000}
          {...props}
        />
      </TooltipProvider>
    </QueryClientProvider>,
  )
}

/**
 * Kolom penukaran poin di meja kasir. Yang diuji di sini bukan gayanya
 * melainkan batas-batasnya: kapan ia muncul, berapa yang benar-benar terpakai,
 * dan apakah kasir diberi tahu saat angkanya dipangkas.
 */
describe("RedeemPointsField", () => {
  afterEach(cleanup)

  it("menawarkan penukaran saat poinnya cukup", () => {
    renderField({})

    expect(screen.getByLabelText("Tukar Poin")).toBeTruthy()
  })

  /**
   * Menawarkan penukaran kepada pasien bersaldo nol membuat kasir menjelaskan
   * hal yang sama berulang-ulang di depan antrean.
   */
  it("tidak muncul sama sekali saat saldonya di bawah minimum", () => {
    const { container } = renderField({ balance: 5 })

    expect(container.textContent).toBe("")
  })

  /** Tagihan kecil membuat saldo besar pun tidak seluruhnya bisa dipakai. */
  it("tidak muncul saat tagihannya tidak menampung penukaran minimum", () => {
    const { container } = renderField({ balance: 50, payable: 5_000 })

    expect(container.textContent).toBe("")
  })

  it("menunjukkan nilai rupiah dari poin yang diketik", () => {
    renderField({ value: "30" })

    expect(screen.getByText(/30\.000/)).toBeTruthy()
  })

  /** "Tukar semua" berarti sebanyak yang muat di tagihan ini, bukan seluruh saldo. */
  it("mengisi sebanyak yang muat di tagihan saat tukar semua ditekan", () => {
    const onChange = vi.fn()
    renderField({ balance: 500, payable: 200_000, onChange })

    fireEvent.click(screen.getByRole("button", { name: /Tukar semua/ }))

    expect(onChange).toHaveBeenCalledWith("200")
  })

  /**
   * Angka yang dipangkas harus diberitahukan sebelum kasir menyebutkannya ke
   * pasien — potongan yang lebih kecil dari yang dijanjikan baru ketahuan
   * setelah nota tercetak.
   */
  it("memberi tahu saat poin yang diketik dipangkas tagihannya", () => {
    renderField({ value: "500", balance: 500, payable: 200_000 })

    expect(screen.getByText("Poin yang dipakai menyesuaikan tagihan.")).toBeTruthy()
    expect(screen.getByText(/200\.000/)).toBeTruthy()
  })

  it("tidak mengeluh saat angkanya masih muat", () => {
    renderField({ value: "30" })

    expect(screen.queryByText("Poin yang dipakai menyesuaikan tagihan.")).toBeNull()
  })
})

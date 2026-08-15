import { describe, expect, it, vi } from "vitest"
import { fireEvent, render } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { PaymentPanel, type PaymentData } from "./payment-panel.tsx"

setTranslations({
  pos: {
    payment: "Pembayaran",
    method: "Metode",
    amount: "Jumlah Bayar",
    total: "Total",
    paid_amount: "Sudah Dibayar",
    outstanding: "Sisa Bayar",
  },
})

function renderPanel(total: number, onChange = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { enabled: false } } })
  const utils = render(
    <QueryClientProvider client={client}>
      <PaymentPanel total={total} onChange={onChange} />
    </QueryClientProvider>,
  )

  const input = utils.container.querySelector("#pos-amount") as HTMLInputElement

  return { ...utils, input, onChange }
}

/** Nominal terakhir yang dilaporkan ke halaman kasir. */
function lastAmount(onChange: ReturnType<typeof vi.fn>): number {
  const calls = onChange.mock.calls
  return (calls[calls.length - 1][0] as PaymentData).amount
}

/**
 * Field ini pernah menyisakan angka nol di depan begitu kasir mengetik, karena
 * nilainya bertipe number dan dimulai dari 0. Yang diuji di sini adalah apa
 * yang benar-benar terbaca kasir di layar dan angka yang dikirim ke transaksi.
 */
describe("PaymentPanel — jumlah bayar", () => {
  it("mulai kosong, bukan angka nol yang menempel di depan", () => {
    const { input } = renderPanel(720000)

    expect(input.value).toBe("")
  })

  it("tidak pernah menyisakan nol di depan saat kasir mengetik", () => {
    const { input, onChange } = renderPanel(720000)

    // Persis kejadian di lapangan: field berisi "0" lalu diketik nominalnya.
    fireEvent.change(input, { target: { value: "05000000" } })

    expect(input.value).toBe("5.000.000")
    expect(lastAmount(onChange)).toBe(5000000)
  })

  it("memisahkan ribuan supaya nominal besar terbaca", () => {
    const { input } = renderPanel(720000)

    fireEvent.change(input, { target: { value: "720000" } })

    expect(input.value).toBe("720.000")
  })

  it("mengabaikan ketikan yang bukan angka", () => {
    const { input, onChange } = renderPanel(720000)

    fireEvent.change(input, { target: { value: "Rp 250rb" } })

    expect(input.value).toBe("250")
    expect(lastAmount(onChange)).toBe(250)
  })

  it("kembali kosong dan nol saat isinya dihapus", () => {
    const { input, onChange } = renderPanel(720000)

    fireEvent.change(input, { target: { value: "500000" } })
    fireEvent.change(input, { target: { value: "" } })

    expect(input.value).toBe("")
    expect(lastAmount(onChange)).toBe(0)
  })

  it("menandai lunas hanya ketika bayarannya menutup tagihan", () => {
    const { input, onChange } = renderPanel(720000)

    fireEvent.change(input, { target: { value: "700000" } })
    expect((onChange.mock.calls.at(-1)?.[0] as PaymentData).covers).toBe(false)

    fireEvent.change(input, { target: { value: "720000" } })
    expect((onChange.mock.calls.at(-1)?.[0] as PaymentData).covers).toBe(true)
  })
})

import type { ReactElement } from "react"
import { describe, expect, it } from "vitest"
import { render } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { RevenueSummary } from "./revenue-summary.tsx"

setTranslations({
  report: {
    total_revenue: "Total Omzet",
    total_revenue_hint: "Nilai seluruh transaksi lunas",
    paid_transactions: "Transaksi Lunas",
    paid_transactions_count: "Jumlah transaksi lunas",
    average_transaction: "Rata-rata per Transaksi",
    average_transaction_hint: "Omzet dibagi jumlah transaksi",
  },
})

function renderWithProviders(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

/**
 * Angka pembuka tab omzet — layar yang paling sering dibuka pemilik klinik,
 * dan sebelumnya menampilkan nilai rupiah sebagai desimal mentah.
 */
describe("RevenueSummary", () => {
  it("menulis omzet sebagai Rupiah, bukan desimal mentah", () => {
    const { container } = renderWithProviders(
      <RevenueSummary
        data={{ total_revenue: "1500000.00", paid_transactions_count: 3 }}
      />,
    )

    const text = container.textContent ?? ""

    expect(text).toContain("1.500.000")
    expect(text).not.toContain("1500000.00")
  })

  /**
   * Rata-rata dihitung di sini karena server tidak mengirimkannya, padahal
   * justru itu yang dibandingkan antar periode.
   */
  it("menghitung rata-rata per transaksi dari dua angka yang ada", () => {
    const { container } = renderWithProviders(
      <RevenueSummary
        data={{ total_revenue: "1500000.00", paid_transactions_count: 3 }}
      />,
    )

    expect(container.textContent).toContain("500.000")
  })

  /** Periode tanpa transaksi tidak boleh berakhir sebagai pembagian nol. */
  it("tidak membagi nol saat belum ada transaksi lunas", () => {
    const { container } = renderWithProviders(
      <RevenueSummary
        data={{ total_revenue: "0.00", paid_transactions_count: 0 }}
      />,
    )

    const text = container.textContent ?? ""

    expect(text).not.toContain("NaN")
    expect(text).not.toContain("∞")
  })

  /** Nilai yang tidak terbaca jatuh ke nol, bukan merusak seluruh kartu. */
  it("menahan nilai rusak dari server", () => {
    const { container } = renderWithProviders(
      <RevenueSummary
        data={{ total_revenue: "entah", paid_transactions_count: 2 }}
      />,
    )

    expect(container.textContent).not.toContain("NaN")
  })
})

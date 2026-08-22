import type { ReactElement } from "react"
import { describe, expect, it } from "vitest"
import { render, screen, within } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { SalesTable } from "./sales-table.tsx"
import type { SalesRow } from "./sales-table.tsx"

setTranslations({
  report: {
    qty_sold: "Jumlah Terjual",
    revenue: "Omzet",
    total: "Total",
  },
})

function renderWithProviders(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

const rows: SalesRow[] = [
  { id: 1, name: "Facial Glow", qty_sold: 4, revenue: "1200000.00" },
  { id: 2, name: "Peeling", qty_sold: 10, revenue: "3000000.00" },
  { id: 3, name: "Totok Wajah", qty_sold: 2, revenue: "400000.00" },
]

/** Baris isi saja, tanpa baris kepala dan baris total. */
function bodyRows() {
  const [body] = screen.getAllByRole("rowgroup").slice(1)

  return within(body).getAllByRole("row")
}

/**
 * Peringkat penjualan: pertanyaannya bukan "apa saja yang terjual", melainkan
 * "mana yang paling menghidupi klinik".
 */
describe("SalesTable", () => {
  it("mengurutkan dari omzet terbesar, bukan urutan kiriman server", () => {
    renderWithProviders(<SalesTable rows={rows} nameLabel="Nama Layanan" />)

    const names = bodyRows().map((row) => within(row).getAllByRole("cell")[1].textContent)

    expect(names).toEqual(["Peeling", "Facial Glow", "Totok Wajah"])
  })

  it("menomori peringkatnya sesuai urutan baru", () => {
    renderWithProviders(<SalesTable rows={rows} nameLabel="Nama Layanan" />)

    const first = within(bodyRows()[0]).getAllByRole("cell")

    expect(first[0].textContent).toBe("1")
    expect(first[1].textContent).toBe("Peeling")
  })

  /**
   * Nilai uang datang sebagai desimal berpresisi dan sebelumnya tercetak
   * mentah — "3000000.00" di laporan yang dibaca pemilik klinik.
   */
  it("menulis nilai uang sebagai Rupiah, bukan desimal mentah", () => {
    renderWithProviders(<SalesTable rows={rows} nameLabel="Nama Layanan" />)

    const text = bodyRows()[0].textContent ?? ""

    expect(text).toContain("Rp")
    expect(text).not.toContain("3000000.00")
  })

  it("menutup dengan baris total supaya tidak perlu menjumlah sendiri", () => {
    renderWithProviders(<SalesTable rows={rows} nameLabel="Nama Layanan" />)

    const footer = screen.getAllByRole("rowgroup").at(-1)
    const text = footer?.textContent ?? ""

    // 4 + 10 + 2 kuantitas, dan total omzetnya.
    expect(text).toContain("16")
    expect(text).toContain("4.600.000")
  })

  it("tetap utuh saat datanya kosong", () => {
    renderWithProviders(<SalesTable rows={[]} nameLabel="Nama Layanan" />)

    const footer = screen.getAllByRole("rowgroup").at(-1)

    expect(footer?.textContent).toContain("0")
  })

  /** Nilai yang tidak terbaca dihitung nol, bukan bikin seluruh tabel NaN. */
  it("tidak menularkan nilai rusak ke baris total", () => {
    renderWithProviders(
      <SalesTable
        nameLabel="Nama Layanan"
        rows={[
          { id: 1, name: "Rusak", qty_sold: 1, revenue: "entah" },
          { id: 2, name: "Waras", qty_sold: 1, revenue: "500000.00" },
        ]}
      />,
    )

    const footer = screen.getAllByRole("rowgroup").at(-1)

    expect(footer?.textContent).toContain("500.000")
    expect(footer?.textContent).not.toContain("NaN")
  })
})

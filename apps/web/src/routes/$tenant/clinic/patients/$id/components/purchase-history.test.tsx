import type { ReactElement } from "react"
import { describe, expect, it, vi } from "vitest"
import { fireEvent, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { PurchaseHistory } from "./purchase-history.tsx"
import type { PurchaseRow } from "./record-types.ts"

setTranslations({
  general: { loading: "Memuat..." },
  medical_record: {
    load_older_purchases: "Muat pembelian sebelumnya",
    purchase_standalone: "Tanpa kunjungan",
    purchases_empty: "Belum ada pembelian produk",
    purchases_empty_desc: "Produk yang ditebus pasien akan terkumpul di sini.",
  },
})

function renderWithProviders(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function purchase(overrides: Partial<PurchaseRow> = {}): PurchaseRow {
  return {
    transaction_id: 1,
    invoice_number: "INV-001",
    purchased_at: "2026-08-20T11:00:00",
    linked_to_visit: true,
    items: [
      { name: "Serum Vitamin C", qty: 1, unit_price: 250000, subtotal: 250000 },
    ],
    ...overrides,
  }
}

const noop = {
  isLoading: false,
  hasNextPage: false,
  isFetchingNextPage: false,
  onLoadMore: () => {},
}

/**
 * Riwayat pembelian produk pasien — jawaban atas "skincare apa yang sedang
 * dipakai pasien ini di rumah", pertanyaan yang menentukan boleh tidaknya
 * sebuah tindakan.
 */
describe("PurchaseHistory", () => {
  it("menampilkan produk beserta nilainya dalam Rupiah", () => {
    const { container } = renderWithProviders(
      <PurchaseHistory rows={[purchase()]} {...noop} />,
    )

    const text = container.textContent ?? ""

    expect(text).toContain("Serum Vitamin C")
    expect(text).toContain("250.000")
    expect(text).not.toContain("250000.00")
  })

  /**
   * Pembelian yang berdiri sendiri ditandai, bukan disamarkan: dokter perlu
   * tahu bedanya antara produk yang ditebus saat kunjungan dan yang dibeli
   * terpisah.
   */
  it("menandai pembelian yang tidak menempel ke kunjungan", () => {
    renderWithProviders(
      <PurchaseHistory
        rows={[purchase({ linked_to_visit: false })]}
        {...noop}
      />,
    )

    expect(screen.getByText("Tanpa kunjungan")).toBeTruthy()
  })

  it("tidak menandai pembelian yang lahir dari kunjungan", () => {
    renderWithProviders(<PurchaseHistory rows={[purchase()]} {...noop} />)

    expect(screen.queryByText("Tanpa kunjungan")).toBeNull()
  })

  it("menyebut jumlah hanya bila lebih dari satu", () => {
    const { container } = renderWithProviders(
      <PurchaseHistory
        rows={[
          purchase({
            transaction_id: 7,
            items: [
              { name: "Sunscreen", qty: 3, unit_price: 100000, subtotal: 300000 },
              { name: "Toner", qty: 1, unit_price: 90000, subtotal: 90000 },
            ],
          }),
        ]}
        {...noop}
      />,
    )

    const text = container.textContent ?? ""

    expect(text).toContain("×3")
    expect(text).not.toContain("×1")
  })

  it("menjelaskan keadaan kosong, bukan menampilkan kartu hampa", () => {
    renderWithProviders(<PurchaseHistory rows={[]} {...noop} />)

    expect(screen.getByText("Belum ada pembelian produk")).toBeTruthy()
  })

  it("meminta halaman berikutnya saat masih ada sisanya", () => {
    const onLoadMore = vi.fn()

    renderWithProviders(
      <PurchaseHistory
        rows={[purchase()]}
        {...noop}
        hasNextPage
        onLoadMore={onLoadMore}
      />,
    )

    fireEvent.click(screen.getByText("Muat pembelian sebelumnya"))

    expect(onLoadMore).toHaveBeenCalledTimes(1)
  })

  /** Tombol mati di ujung riwayat cuma bikin orang menebak. */
  it("menyembunyikan tombolnya di ujung riwayat", () => {
    renderWithProviders(<PurchaseHistory rows={[purchase()]} {...noop} />)

    expect(screen.queryByText("Muat pembelian sebelumnya")).toBeNull()
  })
})

import type { ReactElement } from "react"
import { describe, expect, it } from "vitest"
import { render } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { MonthlyUpsell } from "./monthly-upsell.tsx"
import type { CommissionRow } from "./monthly-types.ts"

setTranslations({
  report: {
    upsell_title: "Upselling Penjualan",
    upsell_note: "Omzet dihitung dari baris nota yang penawarnya tercatat.",
    upsell_progress: "Progres target",
    upsell_reached: "Tercapai",
    upsell_short: "Kurang :amount",
    upsell_of_target: "dari :amount",
    upsell_no_target: "Belum ada target yang disetel.",
    total_upsell: "Total komisi upselling",
    no_upsell: "Belum ada penjualan yang ditawarkan staf bulan ini.",
  },
})

function renderWithProviders(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function row(overrides: Partial<CommissionRow> = {}): CommissionRow {
  return {
    therapist_id: 1,
    therapist_name: "Rina",
    revenue: 4_000_000,
    visits: 1,
    new_patients: 0,
    fee_amount: 5_000,
    commission_amount: 200_000,
    upsell_target: 3_000_000,
    upsell_percent: 5,
    upsell_reached: true,
    total: 205_000,
    ...overrides,
  }
}

function textOf(rows: CommissionRow[]): string {
  return renderWithProviders(<MonthlyUpsell rows={rows} />).container.textContent ?? ""
}

/**
 * Keluhan yang memicu blok ini: "upselling penjualan tidak muncul di web".
 * Komisinya sudah lama dihitung benar, tapi melebur jadi satu angka total per
 * orang — tanpa omzet, tanpa target, tanpa keterangan tercapai atau belum.
 */
describe("MonthlyUpsell", () => {
  it("menyebut omzet, target, persentase, dan komisinya", () => {
    const text = textOf([row()])

    expect(text).toContain("Rina")
    expect(text).toContain("4.000.000") // omzet
    expect(text).toContain("3.000.000") // target
    expect(text).toContain("5%")
    expect(text).toContain("200.000") // komisi
    expect(text).toContain("Tercapai")
  })

  /** Rp0 tanpa keterangan tidak bisa dijelaskan ke terapis yang bertanya. */
  it("menyebut kurang berapa lagi untuk yang belum tercapai", () => {
    const text = textOf([
      row({
        revenue: 1_000_000,
        commission_amount: 0,
        upsell_reached: false,
        total: 5_000,
      }),
    ])

    expect(text).toContain("Kurang")
    expect(text).toContain("2.000.000")
    expect(text).not.toContain("Tercapai")
  })

  it("menjumlahkan komisi seluruh staf", () => {
    const text = textOf([
      row({ therapist_id: 1, commission_amount: 200_000 }),
      row({ therapist_id: 2, therapist_name: "Sari", commission_amount: 150_000 }),
    ])

    expect(text).toContain("350.000")
  })

  /** Yang tidak menawarkan apa pun tidak punya cerita upselling. */
  it("melewati staf tanpa omzet", () => {
    const text = textOf([
      row({ therapist_id: 1, therapist_name: "Rina" }),
      row({
        therapist_id: 2,
        therapist_name: "Tanpa Jualan",
        revenue: 0,
        commission_amount: 0,
      }),
    ])

    expect(text).toContain("Rina")
    expect(text).not.toContain("Tanpa Jualan")
  })

  it("mengurutkan dari omzet terbesar", () => {
    const text = textOf([
      row({ therapist_id: 1, therapist_name: "Kecil", revenue: 1_000_000 }),
      row({ therapist_id: 2, therapist_name: "Besar", revenue: 9_000_000 }),
    ])

    expect(text.indexOf("Besar")).toBeLessThan(text.indexOf("Kecil"))
  })

  it("mengakui klinik yang belum menyetel target", () => {
    const text = textOf([
      row({ upsell_target: null, upsell_percent: null, upsell_reached: false }),
    ])

    expect(text).toContain("Belum ada target")
  })

  it("mengatakan apa adanya saat tidak ada yang berjualan", () => {
    expect(textOf([])).toContain("Belum ada penjualan yang ditawarkan staf")
  })
})

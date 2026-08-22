import type { ReactElement } from "react"
import { describe, expect, it, vi } from "vitest"
import { fireEvent, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"
import { ReportRangePicker } from "./report-range-picker.tsx"

setTranslations({
  report: {
    from: "Dari Tanggal",
    to: "Sampai Tanggal",
    generate: "Tampilkan laporan",
    generate_hint: "Menampilkan laporan untuk rentang tanggal yang diisi.",
    range_backwards: "Tanggal awal melewati tanggal akhir.",
    preset_this_month: "Bulan ini",
    preset_last_month: "Bulan lalu",
    preset_last_7: "7 hari",
    preset_last_30: "30 hari",
    preset_today: "Hari ini",
  },
})

function renderWithProviders(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(
    <QueryClientProvider client={client}>
      <TooltipProvider>{ui}</TooltipProvider>
    </QueryClientProvider>,
  )
}

const submitButton = () =>
  screen.getByText("Tampilkan laporan") as HTMLButtonElement

/**
 * Pemilih rentang laporan: sebelumnya dua kolom tanggal kosong, jadi membuka
 * laporan bulan berjalan berarti mengetik dua tanggal tiap kali.
 */
describe("ReportRangePicker", () => {
  it("menerapkan rentang cepat dalam satu ketukan", () => {
    const onApply = vi.fn()

    renderWithProviders(<ReportRangePicker applied={null} onApply={onApply} />)
    fireEvent.click(screen.getByText("Hari ini"))

    expect(onApply).toHaveBeenCalledTimes(1)

    const range = onApply.mock.calls[0][0]

    expect(range.from).toBe(range.to)
    expect(range.from).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })

  it("mengisi kolom tanggal dari rentang cepat yang dipilih", () => {
    renderWithProviders(<ReportRangePicker applied={null} onApply={() => {}} />)
    fireEvent.click(screen.getByText("Bulan lalu"))

    const from = screen.getByLabelText("Dari Tanggal") as HTMLInputElement
    const to = screen.getByLabelText("Sampai Tanggal") as HTMLInputElement

    expect(from.value).not.toBe("")
    expect(from.value < to.value).toBe(true)
    // Bulan lalu selalu berakhir di hari terakhirnya, bukan hari ini.
    expect(to.value.slice(0, 7)).toBe(from.value.slice(0, 7))
  })

  it("menandai rentang yang sedang dipakai", () => {
    const today = new Date()
    const iso = (d: Date) =>
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`

    renderWithProviders(
      <ReportRangePicker
        applied={{ from: iso(today), to: iso(today) }}
        onApply={() => {}}
      />,
    )

    expect(screen.getByText("Hari ini").getAttribute("aria-pressed")).toBe("true")
    expect(screen.getByText("7 hari").getAttribute("aria-pressed")).toBe("false")
  })

  it("belum bisa ditampilkan selagi tanggalnya kosong", () => {
    renderWithProviders(<ReportRangePicker applied={null} onApply={() => {}} />)

    expect(submitButton().disabled).toBe(true)
  })

  /** Rentang terbalik ditolak di layar, bukan dikirim dulu ke server. */
  it("menolak tanggal awal yang melewati tanggal akhir", () => {
    const onApply = vi.fn()

    renderWithProviders(<ReportRangePicker applied={null} onApply={onApply} />)

    fireEvent.change(screen.getByLabelText("Dari Tanggal"), {
      target: { value: "2026-08-31" },
    })
    fireEvent.change(screen.getByLabelText("Sampai Tanggal"), {
      target: { value: "2026-08-01" },
    })

    expect(submitButton().disabled).toBe(true)
    expect(onApply).not.toHaveBeenCalled()
  })

  it("menerima rentang yang urutannya benar", () => {
    const onApply = vi.fn()

    renderWithProviders(<ReportRangePicker applied={null} onApply={onApply} />)

    fireEvent.change(screen.getByLabelText("Dari Tanggal"), {
      target: { value: "2026-08-01" },
    })
    fireEvent.change(screen.getByLabelText("Sampai Tanggal"), {
      target: { value: "2026-08-31" },
    })
    fireEvent.click(screen.getByText("Tampilkan laporan"))

    expect(onApply).toHaveBeenCalledWith({
      from: "2026-08-01",
      to: "2026-08-31",
    })
  })
})

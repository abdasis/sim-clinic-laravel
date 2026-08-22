import type { ReactElement } from "react"
import { describe, expect, it, vi } from "vitest"
import { fireEvent, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import type { ScheduleBooking } from "./schedule-grid.tsx"
import { ScheduleMonth } from "./schedule-month.tsx"

setTranslations({
  booking: {
    schedule: "Jadwal",
    more_bookings: "lagi",
  },
})

function renderWithProviders(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

let nextId = 1

/**
 * Waktu ditulis tanpa offset supaya terbaca sebagai waktu setempat — persis
 * seperti yang dilihat staf setelah browser mengonversi kiriman server.
 */
function booking(
  start: string,
  overrides: Partial<ScheduleBooking> = {},
): ScheduleBooking {
  const startAt = `${start}:00`

  return {
    id: nextId++,
    patient_name: "Ani Lestari",
    service_name: "Facial Glow",
    assignee_id: 1,
    assignee_name: "Jasmin",
    start_at: startAt,
    end_at: startAt,
    status: "confirmed",
    ...overrides,
  }
}

/**
 * Kalender sebulan penuh: bentuk yang menjawab "tanggal mana yang masih
 * longgar", pertanyaan yang muncul jauh sebelum harinya tiba.
 */
describe("ScheduleMonth", () => {
  it("menampilkan seluruh hari bulan itu, termasuk baris minggu penuh", () => {
    renderWithProviders(
      <ScheduleMonth data={[]} date="2026-08-22" onSelectDay={() => {}} />,
    )

    // Agustus 2026 punya 31 hari; kotaknya selalu kelipatan tujuh karena
    // baris pertama dan terakhir dilengkapi hari titipan bulan tetangga.
    const cells = screen.getAllByRole("button")

    expect(cells.length % 7).toBe(0)
    expect(cells.length).toBeGreaterThanOrEqual(31)
  })

  it("meletakkan booking di tanggalnya sendiri", () => {
    renderWithProviders(
      <ScheduleMonth
        data={[booking("2026-08-12T09:00")]}
        date="2026-08-22"
        onSelectDay={() => {}}
      />,
    )

    const cell = screen.getByLabelText("Jadwal 2026-08-12").textContent ?? ""
    const next = screen.getByLabelText("Jadwal 2026-08-13").textContent ?? ""

    expect(cell).toContain("Ani Lestari")
    expect(cell).toContain("09:00")
    expect(next).not.toContain("Ani Lestari")
  })

  /**
   * Hari padat tidak boleh memanjangkan barisnya sampai kalendernya berubah
   * bentuk; sisanya diringkas jadi hitungan.
   */
  it("meringkas hari yang terlalu padat jadi hitungan sisa", () => {
    const data = [
      booking("2026-08-12T09:00", { patient_name: "Pasien A" }),
      booking("2026-08-12T10:00", { patient_name: "Pasien B" }),
      booking("2026-08-12T11:00", { patient_name: "Pasien C" }),
      booking("2026-08-12T13:00", { patient_name: "Pasien D" }),
      booking("2026-08-12T14:00", { patient_name: "Pasien E" }),
    ]

    renderWithProviders(
      <ScheduleMonth data={data} date="2026-08-22" onSelectDay={() => {}} />,
    )

    const cell = screen.getByLabelText("Jadwal 2026-08-12").textContent ?? ""

    expect(cell).toContain("Pasien A")
    expect(cell).toContain("+2 lagi")
    // Yang diringkas benar-benar tidak ikut tampil, bukan sekadar tersembunyi.
    expect(cell).not.toContain("Pasien D")
    // Jumlah utuhnya tetap terbaca walau isinya diringkas.
    expect(cell).toContain("5")
  })

  it("mengurutkan isi satu hari dari jam paling awal", () => {
    const data = [
      booking("2026-08-12T15:00", { patient_name: "Sore" }),
      booking("2026-08-12T08:00", { patient_name: "Pagi" }),
    ]

    renderWithProviders(
      <ScheduleMonth data={data} date="2026-08-22" onSelectDay={() => {}} />,
    )

    const text = screen.getByLabelText("Jadwal 2026-08-12").textContent ?? ""

    expect(text.indexOf("Pagi")).toBeLessThan(text.indexOf("Sore"))
  })

  it("menyerahkan tanggal yang ditekan supaya bisa dibuka per jam", () => {
    const onSelectDay = vi.fn()

    renderWithProviders(
      <ScheduleMonth data={[]} date="2026-08-22" onSelectDay={onSelectDay} />,
    )

    fireEvent.click(screen.getByLabelText("Jadwal 2026-08-19"))

    expect(onSelectDay).toHaveBeenCalledWith("2026-08-19")
  })
})

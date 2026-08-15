import type { ReactElement } from "react"
import { describe, expect, it, vi } from "vitest"
import { render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"

import { setTranslations } from "#/utils/trans.ts"
import type { StatsPayload } from "#/hooks/use-stats.ts"
import { StatsSection } from "./stats-section.tsx"
import { IndexCta } from "./index-cta.tsx"

// Label datang dari backend, jadi cukup dua kunci yang dipakai komponen.
setTranslations({
  stats: {
    title: "Ringkasan",
    last_days: "Data :days hari terakhir",
    refresh: "Muat ulang statistik",
    refresh_hint: "Ambil ulang angka terbaru",
    chart_hint: "Arahkan kursor",
    empty_title: "Belum ada aktivitas",
    empty_description: "Menunggu data pertama",
    error_title: "Statistik belum bisa ditampilkan",
    error_description: "Coba lagi sebentar",
    retry: "Coba lagi",
    loading: "Menyiapkan angka...",
  },
})

/** useTrans memakai React Query; komponen butuh provider walau datanya lokal. */
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

const payload: StatsPayload = {
  kpis: [
    { key: "total", label: "Total pasien", value: 12, unit: "count" },
    { key: "revenue", label: "Omzet lunas", value: 250000, unit: "currency" },
  ],
  trend: Array.from({ length: 30 }, (_, index) => ({
    date: `2026-07-${String(index + 1).padStart(2, "0")}`,
    value: index,
  })),
  trend_metric: "patients_new",
  trend_label: "Pasien baru per hari",
}

describe("StatsSection", () => {
  it("menampilkan label KPI dan judul tren", () => {
    renderWithProviders(<StatsSection stats={payload} />)

    expect(screen.getByText("Total pasien")).toBeTruthy()
    expect(screen.getByText("Omzet lunas")).toBeTruthy()
    expect(screen.getByText("Pasien baru per hari")).toBeTruthy()
    expect(screen.getByText("Data 30 hari terakhir")).toBeTruthy()
  })

  it("menawarkan tombol coba lagi saat gagal", () => {
    const onRefresh = vi.fn()
    renderWithProviders(<StatsSection isError onRefresh={onRefresh} />)

    expect(screen.getByText("Statistik belum bisa ditampilkan")).toBeTruthy()
    screen.getByRole("button", { name: "Coba lagi" }).click()
    expect(onRefresh).toHaveBeenCalledOnce()
  })

  it("menunggu dengan kerangka selagi data dimuat", () => {
    const { container } = renderWithProviders(<StatsSection isLoading />)

    expect(container.querySelector("[aria-busy]")).toBeTruthy()
  })
})

describe("IndexCta", () => {
  it("tidak merender apa pun tanpa aksi", () => {
    const { container } = renderWithProviders(
      <IndexCta
        icon={[]}
        title="Judul"
        description="Keterangan"
      />,
    )

    expect(container.firstChild).toBeNull()
  })

  it("memanggil onAction saat tombolnya ditekan", () => {
    const onAction = vi.fn()
    renderWithProviders(
      <IndexCta
        icon={[]}
        title="Judul"
        description="Keterangan"
        actionLabel="Tambah"
        onAction={onAction}
      />,
    )

    screen.getByRole("button", { name: "Tambah" }).click()
    expect(onAction).toHaveBeenCalledOnce()
  })
})

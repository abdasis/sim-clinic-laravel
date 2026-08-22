import type { ReactElement } from "react"
import { describe, expect, it } from "vitest"
import { render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { PatientClinicalSummary } from "./patient-clinical-summary.tsx"
import type { RecordRow } from "./record-types.ts"

setTranslations({
  medical_record: {
    allergy_alert: "Riwayat alergi pasien",
    total_visits: "Kunjungan",
    last_visit: "Terakhir",
    total_treatments: "Tindakan",
    total_treatments_hint: "Seluruh tindakan yang tercatat",
    products_used: "Produk Dipakai",
    products_used_hint: "Jumlah item yang pernah ditebus",
    product_spend: "Belanja Produk",
    product_spend_hint: "Nilai seluruh pembelian produk",
  },
})

function renderWithProviders(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function record(overrides: Partial<RecordRow> = {}): RecordRow {
  return {
    id: Math.floor(Math.random() * 1e6),
    created_at: "2026-08-20T09:00:00",
    treatments: [],
    ...overrides,
  }
}

/**
 * Ringkasan klinis di atas riwayat pasien. Yang paling penting di sini bukan
 * angkanya melainkan peringatan alerginya: kolom itu sebelumnya tidak muncul
 * sama sekali di layar ini, padahal justru itu yang harus terbaca sebelum
 * tindakan apa pun diputuskan.
 */
describe("PatientClinicalSummary", () => {
  it("menaikkan riwayat alergi jadi peringatan", () => {
    renderWithProviders(
      <PatientClinicalSummary
        records={[record({ allergy_history: "Alergi paraben" })]}
        productSpend={0}
        productCount={0}
      />,
    )

    const alert = screen.getByRole("alert")

    expect(alert.textContent).toContain("Alergi paraben")
  })

  /** Alergi ditulis ulang tiap kunjungan; yang sama tidak diulang. */
  it("tidak mengulang alergi yang sama dari beberapa kunjungan", () => {
    renderWithProviders(
      <PatientClinicalSummary
        records={[
          record({ allergy_history: "Alergi paraben" }),
          record({ allergy_history: "Alergi paraben" }),
          record({ allergy_history: "Alergi alkohol" }),
        ]}
        productSpend={0}
        productCount={0}
      />,
    )

    const alert = screen.getByRole("alert").textContent ?? ""

    expect(alert.split("Alergi paraben").length - 1).toBe(1)
    expect(alert).toContain("Alergi alkohol")
  })

  /** Pasien tanpa alergi tidak diberi peringatan kosong. */
  it("tidak memunculkan peringatan saat tidak ada alergi", () => {
    renderWithProviders(
      <PatientClinicalSummary
        records={[record()]}
        productSpend={0}
        productCount={0}
      />,
    )

    expect(screen.queryByRole("alert")).toBeNull()
  })

  it("menghitung kunjungan dan tindakan dari catatannya", () => {
    const { container } = renderWithProviders(
      <PatientClinicalSummary
        records={[
          record({
            treatments: [
              { id: 1, service_name: "Facial", notes: null },
              { id: 2, service_name: "Peeling", notes: null },
            ],
          }),
          record({ treatments: [{ id: 3, service_name: "Totok", notes: null }] }),
        ]}
        productSpend={0}
        productCount={0}
      />,
    )

    const text = container.textContent ?? ""

    // 2 kunjungan, 3 tindakan.
    expect(text).toContain("2")
    expect(text).toContain("3")
  })

  it("menulis belanja produk sebagai Rupiah", () => {
    const { container } = renderWithProviders(
      <PatientClinicalSummary
        records={[record()]}
        productSpend={750000}
        productCount={3}
      />,
    )

    expect(container.textContent).toContain("750.000")
  })

  /** Kunjungan terakhir diambil dari yang paling belakang, bukan yang pertama. */
  it("menyebut kunjungan terakhir, bukan yang terlama", () => {
    const { container } = renderWithProviders(
      <PatientClinicalSummary
        records={[
          record({ created_at: "2026-01-05T09:00:00" }),
          record({ created_at: "2026-08-20T09:00:00" }),
        ]}
        productSpend={0}
        productCount={0}
      />,
    )

    const text = container.textContent ?? ""

    expect(text).toContain("Agu")
    expect(text).not.toContain("Jan")
  })

  /** Tanggal kunjungan menang atas tanggal catatannya ditulis. */
  it("memakai waktu kunjungan bila catatannya lahir dari booking", () => {
    const { container } = renderWithProviders(
      <PatientClinicalSummary
        records={[
          record({
            created_at: "2026-08-25T09:00:00",
            booking: { id: 1, start_at: "2026-08-20T09:00:00" },
          }),
        ]}
        productSpend={0}
        productCount={0}
      />,
    )

    expect(container.textContent).toContain("20 Agu")
  })
})

import type { ReactElement } from "react"
import { describe, expect, it } from "vitest"
import { render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { RecipientPreview } from "./recipient-preview.tsx"
import type { AudiencePreview } from "./recipient-preview.tsx"

setTranslations({
  broadcast: {
    preview_count: ":count penerima",
    without_phone: ":count tanpa nomor",
    opted_out: ":count menolak promosi",
    recipients_hint: "Sudah dikurangi nomor ganda.",
    no_recipients: "Tidak ada satu pun penerima untuk pilihan ini.",
  },
})

function renderWithProviders(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function preview(overrides: Partial<AudiencePreview> = {}): AudiencePreview {
  return {
    count: 2,
    without_phone: 0,
    opted_out: 0,
    recipients: [
      { patient_id: 1, name: "Ani Lestari", phone: "6281100000001" },
      { patient_id: 2, name: "Budi Santoso", phone: "6281100000002" },
    ],
    ...overrides,
  }
}

/**
 * Panel ini dulu hanya menyebut jumlahnya. Server memang mengirim tiga nama
 * contoh, tapi layarnya tidak pernah menampilkannya — jadi admin menekan
 * kirim ke ratusan orang tanpa pernah melihat satu pun nama.
 */
describe("RecipientPreview", () => {
  it("menampilkan nama tiap penerima, bukan hanya jumlahnya", () => {
    const { container } = renderWithProviders(
      <RecipientPreview data={preview()} isLoading={false} />,
    )

    const text = container.textContent ?? ""

    expect(text).toContain("Ani Lestari")
    expect(text).toContain("Budi Santoso")
    expect(text).toContain("2 penerima")
  })

  it("menyebut nomornya juga supaya bisa diperiksa sebelum kirim", () => {
    const { container } = renderWithProviders(
      <RecipientPreview data={preview()} isLoading={false} />,
    )

    expect(container.textContent).toContain("6281100000001")
  })

  /** Daftar panjang tetap ditampilkan utuh, tidak dipotong di tiga baris. */
  it("tidak memotong daftar yang panjang", () => {
    const many = Array.from({ length: 25 }, (_, index) => ({
      patient_id: index + 1,
      name: `Pasien ${index + 1}`,
      phone: `62811000000${index}`,
    }))

    const { container } = renderWithProviders(
      <RecipientPreview
        data={preview({ count: 25, recipients: many })}
        isLoading={false}
      />,
    )

    const text = container.textContent ?? ""

    expect(text).toContain("Pasien 1")
    expect(text).toContain("Pasien 25")
  })

  /** Dua sebab tak terjangkau dibedakan: data rusak vs pilihan pasien. */
  it("membedakan nomor tak terbaca dari yang menolak promosi", () => {
    const { container } = renderWithProviders(
      <RecipientPreview
        data={preview({ without_phone: 3, opted_out: 2 })}
        isLoading={false}
      />,
    )

    const text = container.textContent ?? ""

    expect(text).toContain("3 tanpa nomor")
    expect(text).toContain("2 menolak promosi")
  })

  it("menyembunyikan keterangan yang angkanya nol", () => {
    const { container } = renderWithProviders(
      <RecipientPreview data={preview()} isLoading={false} />,
    )

    const text = container.textContent ?? ""

    expect(text).not.toContain("tanpa nomor")
    expect(text).not.toContain("menolak promosi")
  })

  /** Sasaran yang tidak menjangkau siapa pun dikatakan, bukan dibiarkan kosong. */
  it("menjelaskan saat tidak ada penerima sama sekali", () => {
    renderWithProviders(
      <RecipientPreview
        data={preview({ count: 0, recipients: [] })}
        isLoading={false}
      />,
    )

    expect(
      screen.getByText("Tidak ada satu pun penerima untuk pilihan ini."),
    ).toBeTruthy()
  })
})

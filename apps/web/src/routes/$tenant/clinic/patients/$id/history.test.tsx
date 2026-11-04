import type { ReactElement } from "react"
import { describe, expect, it, vi } from "vitest"
import { render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"




const entries = [
  {
    date: "2026-08-22T10:00:00+07:00",
    service_name: "Facial Glow",
    status: "confirmed",
    assignee_name: "Jasmin",
    type: "booking",
  },
  {
    date: "2026-08-23T14:00:00+07:00",
    service_name: "Peeling",
    status: "cancelled",
    assignee_name: "Mutia",
    type: "booking",
  },
]

vi.mock("#/lib/api.ts", () => ({
  apiGet: vi.fn(() =>
    Promise.resolve({
      data: entries,
    }),
  ),
}))

vi.mock("#/components/breadcrumb-tail.tsx", () => ({
  useBreadcrumbTail: () => {},
}))

vi.mock("@tanstack/react-router", () => ({
  createFileRoute: () => (config: unknown) => config,
  useParams: () => ({ tenant: "klinik-uji", id: "1" }),
}))

// createFileRoute dimock jadi identitas, sehingga Route di sini adalah objek
// konfigurasi rutenya sendiri — bukan Route bertipe TanStack seperti aslinya.
const { Route } = await import("./history.tsx")
const PatientHistoryPage = (Route as unknown as {
  component: () => ReactElement
}).component

function renderPage() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(
    <QueryClientProvider client={client}>
      <PatientHistoryPage />
    </QueryClientProvider>,
  )
}

/**
 * Riwayat kunjungan pasien — layar klinis yang dibaca di depan pasiennya,
 * dan sebelumnya mencetak dua nilai mentah dari server apa adanya.
 */
describe("PatientHistoryPage", () => {
  it("menulis tanggalnya terbaca, bukan cap waktu ISO", async () => {
    const { container } = renderPage()

    await waitFor(() => expect(screen.getByText("Facial Glow")).toBeTruthy())

    const text = container.textContent ?? ""

    expect(text).not.toContain("2026-08-22T10:00:00")
    expect(text).toContain("Agu")
  })

  it("menerjemahkan status kunjungan, bukan menampilkan nilai enum", async () => {
    const { container } = renderPage()

    await waitFor(() => expect(screen.getByText("Facial Glow")).toBeTruthy())

    const text = container.textContent ?? ""

    expect(text).toContain("Dikonfirmasi")
    expect(text).toContain("Dibatalkan")
    expect(text).not.toContain("confirmed")
    expect(text).not.toContain("cancelled")
  })
})

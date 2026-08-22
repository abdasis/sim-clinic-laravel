import type { ReactElement } from "react"
import { describe, expect, it, vi } from "vitest"
import { render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"

const TRANSLATIONS = {
  patient: {
    history: "Riwayat Kunjungan",
    history_empty: "Belum ada riwayat kunjungan",
    history_empty_desc: "Riwayat akan terkumpul setelah kunjungan pertamanya.",
    history_type: { booking: "Booking", treatment: "Treatment" },
  },
  clinic: {
    booking_status: {
      pending: "Menunggu",
      confirmed: "Dikonfirmasi",
      done: "Selesai",
      cancelled: "Dibatalkan",
    },
  },
}

setTranslations(TRANSLATIONS)

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

// useTrans menembak /translations lewat apiGet yang sama, jadi mocknya harus
// membedakan alamat — kalau tidak, store terjemahan tertimpa data riwayat dan
// seluruh label jatuh kembali ke nama kuncinya.
vi.mock("#/lib/api.ts", () => ({
  apiGet: vi.fn((url: string) =>
    Promise.resolve({
      data: url.includes("/translations") ? TRANSLATIONS : entries,
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

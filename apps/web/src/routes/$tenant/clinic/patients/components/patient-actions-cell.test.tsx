import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import {
  PatientActionsCell,
  type PatientActionsRow,
} from "./patient-actions-cell.tsx"

const TRANSLATIONS = {
  patient: {
    delete: "Hapus",
    delete_confirm: "Data pasien ini dihapus permanen. Tindakan ini tidak bisa dibatalkan.",
    archive_confirm: "Pasien ini sudah punya riwayat kunjungan, jadi datanya tidak dihapus permanen — hanya diarsipkan supaya rekam medis dan notanya tetap utuh.",
  },
  general: { edit: "Ubah", actions: "Aksi", cancel: "Batal" },
}

vi.mock("@tanstack/react-router", () => ({
  useNavigate: () => vi.fn(),
}))

/**
 * Radix membuka menunya lewat pointerdown, bukan click; di jsdom keduanya
 * tidak saling memicu.
 */
function openMenu() {
  fireEvent.pointerDown(
    screen.getByLabelText("Aksi"),
    new PointerEvent("pointerdown", { bubbles: true, button: 0, ctrlKey: false }),
  )
}

function wrap(patient: PatientActionsRow) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <PatientActionsCell tenant="demo" patient={patient} />
    </QueryClientProvider>,
  )
}

const base: PatientActionsRow = { id: 3, name: "Ani Wijaya", can_delete: true }

describe("PatientActionsCell", () => {
  beforeEach(() => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => ({
        ok: true,
        status: 200,
        json: async () => ({ data: TRANSLATIONS }),
      })),
    )
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it("menawarkan ubah dan hapus", async () => {
    wrap(base)
    openMenu()

    await waitFor(() => expect(screen.getByText("Ubah")).toBeTruthy())
    expect(screen.getByText("Hapus")).toBeTruthy()
  })

  it("mengatakan penghapusannya permanen untuk pasien tanpa riwayat", async () => {
    wrap(base)
    openMenu()

    await waitFor(() => expect(screen.getByText("Hapus")).toBeTruthy())
    fireEvent.click(screen.getByText("Hapus"))

    await waitFor(() =>
      expect(screen.getByText("Data pasien ini dihapus permanen. Tindakan ini tidak bisa dibatalkan.")).toBeTruthy(),
    )
  })

  it("mengatakan pasien berriwayat hanya diarsipkan, sebelum tombol ditekan", async () => {
    wrap({ ...base, can_delete: false })
    openMenu()

    await waitFor(() => expect(screen.getByText("Hapus")).toBeTruthy())
    fireEvent.click(screen.getByText("Hapus"))

    await waitFor(() =>
      expect(
        screen.getByText(
          "Pasien ini sudah punya riwayat kunjungan, jadi datanya tidak dihapus permanen — hanya diarsipkan supaya rekam medis dan notanya tetap utuh.",
        ),
      ).toBeTruthy(),
    )
  })
})

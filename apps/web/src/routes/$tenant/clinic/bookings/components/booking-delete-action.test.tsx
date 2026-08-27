import type { ReactElement } from "react"
import { describe, expect, it, vi } from "vitest"
import { fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"

const TRANSLATIONS = {
  general: { cancel: "Batal", loading: "Memuat..." },
  booking: {
    delete: "Hapus booking",
    deleted: "Booking berhasil dihapus.",
    delete_confirm: "Jadwal ini dihapus permanen beserta pengingat WhatsApp-nya.",
    has_history_short: "Sudah punya rekam medis atau nota - gunakan Batalkan.",
  },
}

setTranslations(TRANSLATIONS)

const apiDelete = vi.fn((_path: string) => Promise.resolve({}))

vi.mock("#/lib/api.ts", () => ({
  apiDelete: (path: string) => apiDelete(path),
  apiGet: () => Promise.resolve({ data: TRANSLATIONS }),
}))

vi.mock("sonner", () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}))

const { BookingDeleteAction } = await import("./booking-delete-action.tsx")

function renderAction(canDelete?: boolean): ReactElement {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })

  render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <BookingDeleteAction
          tenant="klinik-uji"
          booking={{ id: 7, patient_name: "Ani Lestari", can_delete: canDelete }}
        />
      </TooltipProvider>
    </QueryClientProvider>,
  )

  return <></>
}

/**
 * Menghapus jadwal dipisah dari membatalkannya: membatalkan menyisakan baris
 * di kalender sebagai catatan bahwa jadwalnya pernah ada, sedangkan menghapus
 * dipakai untuk jadwal yang memang tidak pernah ada — salah ketik atau dobel.
 */
describe("BookingDeleteAction", () => {
  it("meminta kepastian sebelum menghapus", () => {
    renderAction(true)

    fireEvent.click(screen.getByLabelText("Hapus booking"))

    expect(screen.getByText(/dihapus permanen/)).toBeTruthy()
    expect(apiDelete).not.toHaveBeenCalled()
  })

  it("menyebut nama pasiennya di kepala dialog", () => {
    renderAction(true)
    fireEvent.click(screen.getByLabelText("Hapus booking"))

    expect(screen.getByText(/Ani Lestari/)).toBeTruthy()
  })

  it("menghapus setelah dipastikan", async () => {
    apiDelete.mockClear()
    renderAction(true)

    fireEvent.click(screen.getByLabelText("Hapus booking"))
    fireEvent.click(
      screen.getAllByText("Hapus booking").at(-1) as HTMLElement,
    )

    await waitFor(() =>
      expect(apiDelete).toHaveBeenCalledWith("/klinik-uji/clinic/bookings/7"),
    )
  })

  /**
   * Booking yang sudah meninggalkan jejak tidak dibiarkan ditekan lalu
   * ditolak server — penilaiannya sudah ikut di tiap baris jadwal.
   */
  it("mematikan tombolnya untuk booking yang berjejak", () => {
    renderAction(false)

    const button = screen.getByLabelText("Hapus booking") as HTMLButtonElement

    expect(button.disabled).toBe(true)
  })

  /** Penanda yang belum dikirim server dianggap boleh; server tetap penjaga akhir. */
  it("membiarkan tombolnya hidup saat penandanya belum ada", () => {
    renderAction(undefined)

    const button = screen.getByLabelText("Hapus booking") as HTMLButtonElement

    expect(button.disabled).toBe(false)
  })
})

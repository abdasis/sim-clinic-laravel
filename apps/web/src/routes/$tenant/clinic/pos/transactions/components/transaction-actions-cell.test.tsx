import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import {
  RouterProvider,
  createMemoryHistory,
  createRootRoute,
  createRouter,
} from "@tanstack/react-router"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"
import {
  TransactionActionsCell,
  type TransactionActionsRow,
} from "./transaction-actions-cell.tsx"

const TRANSLATIONS = {
  pos: {
    delete: "Hapus Transaksi",
    delete_confirm: "Transaksi ini hilang dari daftar dan dari laporan.",
    deleted: "Transaksi berhasil dihapus.",
    cancel_action: "Batalkan Transaksi",
    cancel_confirm: "Stok produk pada transaksi ini dikembalikan.",
    cancelled: "Transaksi berhasil dibatalkan.",
  },
  invoice: { title: "Nota" },
  general: { actions: "Aksi", cancel: "Batal", loading: "Memuat..." },
}

function mockFetch(response: { ok: boolean; status: number; body: unknown }) {
  const mock = vi.fn(async (input: unknown, init?: RequestInit) => {
    void init

    if (String(input).includes("/translations")) {
      return { ok: true, status: 200, json: async () => ({ data: TRANSLATIONS }) }
    }

    return {
      ok: response.ok,
      status: response.status,
      json: async () => response.body,
    }
  })

  vi.stubGlobal("fetch", mock)

  return mock
}

const OK = { ok: true, status: 200, body: { data: null } }

/**
 * Sel ini memuat `Link`, jadi butuh router. Pohon rutenya dibuat seadanya —
 * yang diuji aksinya, bukan navigasinya.
 */
function wrap(transaction: TransactionActionsRow) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  const rootRoute = createRootRoute({
    component: () => (
      <QueryClientProvider client={client}>
        <TooltipProvider>
          <TransactionActionsCell tenant="klinik-uji" transaction={transaction} />
        </TooltipProvider>
      </QueryClientProvider>
    ),
  })

  const router = createRouter({
    routeTree: rootRoute,
    history: createMemoryHistory({ initialEntries: ["/"] }),
  })

  return render(<RouterProvider router={router as never} />)
}

async function openMenu() {
  const trigger = await screen.findByRole("button", { name: /Aksi/ })
  fireEvent.pointerDown(
    trigger,
    new PointerEvent("pointerdown", { bubbles: true, ctrlKey: false, button: 0 }),
  )

  return trigger
}

function requestsTo(mock: ReturnType<typeof mockFetch>, method: string) {
  return mock.mock.calls.filter(
    ([, init]) => (init as RequestInit | undefined)?.method === method,
  )
}

const LIVE: TransactionActionsRow = {
  id: 9,
  invoice_number: "INV-20260818-0001",
  cancelled_at: null,
}

describe("TransactionActionsCell", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations(TRANSLATIONS)
  })

  afterEach(cleanup)

  it("menawarkan hapus transaksi di menu aksi", async () => {
    mockFetch(OK)
    wrap(LIVE)
    await openMenu()

    expect(await screen.findByText("Hapus Transaksi")).toBeTruthy()
    expect(screen.getByText("Batalkan Transaksi")).toBeTruthy()
  })

  /** Menghapus tidak bisa dibatalkan dari layar, jadi harus dikonfirmasi. */
  it("meminta konfirmasi sebelum menghapus", async () => {
    const fetchMock = mockFetch(OK)
    wrap(LIVE)
    await openMenu()

    fireEvent.click(await screen.findByText("Hapus Transaksi"))

    expect(
      await screen.findByText("Transaksi ini hilang dari daftar dan dari laporan."),
    ).toBeTruthy()

    // Belum ada yang terkirim sampai konfirmasinya ditekan.
    expect(requestsTo(fetchMock, "DELETE")).toHaveLength(0)
  })

  it("mengirim DELETE ke transaksi yang benar setelah dikonfirmasi", async () => {
    const fetchMock = mockFetch(OK)
    wrap(LIVE)
    await openMenu()

    fireEvent.click(await screen.findByText("Hapus Transaksi"))
    fireEvent.click(
      await screen.findByRole("button", { name: "Hapus Transaksi" }),
    )

    await waitFor(() => {
      const calls = requestsTo(fetchMock, "DELETE")

      expect(calls).toHaveLength(1)
      expect(String(calls[0][0])).toContain("/klinik-uji/clinic/transactions/9")
    })
  })

  it("membatalkan lewat endpoint cancel, bukan delete", async () => {
    const fetchMock = mockFetch(OK)
    wrap(LIVE)
    await openMenu()

    fireEvent.click(await screen.findByText("Batalkan Transaksi"))
    fireEvent.click(
      await screen.findByRole("button", { name: "Batalkan Transaksi" }),
    )

    await waitFor(() => {
      const calls = requestsTo(fetchMock, "POST")

      expect(calls).toHaveLength(1)
      expect(String(calls[0][0])).toContain(
        "/klinik-uji/clinic/transactions/9/cancel",
      )
    })

    expect(requestsTo(fetchMock, "DELETE")).toHaveLength(0)
  })

  /**
   * Membatalkan dua kali akan mengembalikan stok berlipat; server menolaknya,
   * dan menu tidak perlu menawarkan aksi yang pasti gagal.
   */
  it("mematikan batalkan untuk transaksi yang sudah dibatalkan", async () => {
    mockFetch(OK)
    wrap({ ...LIVE, cancelled_at: "2026-08-18T10:00:00+07:00" })
    await openMenu()

    const item = await screen.findByText("Batalkan Transaksi")

    expect(item.closest("[data-disabled]")).not.toBeNull()

    // Hapus tetap ditawarkan: justru sesudah dibatalkan barisnya boleh hilang.
    expect(screen.getByText("Hapus Transaksi").closest("[data-disabled]")).toBeNull()
  })
})

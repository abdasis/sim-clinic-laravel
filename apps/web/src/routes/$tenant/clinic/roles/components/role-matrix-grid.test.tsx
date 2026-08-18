import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"
import type { RoleMatrixResponse } from "#/types/role-matrix.ts"
import { RoleMatrixGrid } from "./role-matrix-grid.tsx"

const TRANSLATIONS = {
  role: {
    module: "Modul",
    view: "Lihat",
    manage: "Kelola",
    view_hint: "Boleh membuka modul ini.",
    manage_hint: "Boleh mengubah di modul ini.",
    reset: "Izin peran dikembalikan ke bawaan.",
    reset_button: "Kembalikan ke bawaan",
    reset_confirm: "Penyesuaian akan hilang.",
  },
  general: { cancel: "Batal", loading: "Memuat..." },
}

const DATA: RoleMatrixResponse = {
  roles: [
    { key: "admin", label: "Admin" },
    { key: "cashier", label: "Kasir" },
  ],
  modules: [
    { key: "expense", label: "Pengeluaran" },
    { key: "patient", label: "Pasien" },
  ],
  matrix: {
    admin: {
      expense: { view: true, manage: true },
      patient: { view: true, manage: true },
    },
    cashier: {
      expense: { view: false, manage: false },
      patient: { view: true, manage: false },
    },
  },
}

function mockFetch() {
  const mock = vi.fn(async (input: unknown, init?: RequestInit) => {
    void init

    if (String(input).includes("/translations")) {
      return { ok: true, status: 200, json: async () => ({ data: TRANSLATIONS }) }
    }

    return { ok: true, status: 200, json: async () => ({ data: DATA }) }
  })

  vi.stubGlobal("fetch", mock)

  return mock
}

function wrap() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  // Aplikasi memasang TooltipProvider di root layout; di sini harus disiapkan
  // sendiri, kalau tidak yang gagal adalah tooltip-nya, bukan yang diuji.
  return render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <RoleMatrixGrid tenant="klinik-uji" data={DATA} />
      </TooltipProvider>
    </QueryClientProvider>,
  )
}

function sentModules(mock: ReturnType<typeof mockFetch>) {
  const put = mock.mock.calls.find(
    ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
  )

  return put ? JSON.parse(String((put[1] as RequestInit).body)).modules : null
}

function putUrl(mock: ReturnType<typeof mockFetch>) {
  const put = mock.mock.calls.find(
    ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
  )

  return put ? String(put[0]) : null
}

describe("RoleMatrixGrid", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations(TRANSLATIONS)
  })

  afterEach(cleanup)

  /**
   * Server menyimpan izin sebagai satu himpunan utuh. Mengirim hanya modul
   * yang baru saja disentuh akan menghapus seluruh izin lain peran itu.
   */
  it("mengirim seluruh izin peran, bukan hanya yang disentuh", async () => {
    const fetchMock = mockFetch()
    wrap()

    fireEvent.click(screen.getByLabelText("Kasir — Pengeluaran — Lihat"))

    await waitFor(() => {
      const modules = sentModules(fetchMock)

      expect(modules).toEqual({
        expense: { view: true, manage: false },
        patient: { view: true, manage: false },
      })
    })

    expect(putUrl(fetchMock)).toContain("/klinik-uji/clinic/roles/cashier")
  })

  /** Kelola butuh Lihat — dinyalakan bersama, bukan ditolak server. */
  it("menyalakan Lihat saat Kelola dinyalakan", async () => {
    const fetchMock = mockFetch()
    wrap()

    fireEvent.click(screen.getByLabelText("Kasir — Pengeluaran — Kelola"))

    await waitFor(() =>
      expect(sentModules(fetchMock).expense).toEqual({ view: true, manage: true }),
    )
  })

  /** Kebalikannya: mematikan Lihat ikut mematikan Kelola. */
  it("mematikan Kelola saat Lihat dimatikan", async () => {
    const fetchMock = mockFetch()
    wrap()

    fireEvent.click(screen.getByLabelText("Admin — Pengeluaran — Lihat"))

    await waitFor(() =>
      expect(sentModules(fetchMock).expense).toEqual({ view: false, manage: false }),
    )
  })

  it("mematikan Kelola tidak ikut mematikan Lihat", async () => {
    const fetchMock = mockFetch()
    wrap()

    fireEvent.click(screen.getByLabelText("Admin — Pengeluaran — Kelola"))

    await waitFor(() =>
      expect(sentModules(fetchMock).expense).toEqual({ view: true, manage: false }),
    )
  })

  it("tiap peran punya tombol kembalikan ke bawaan sendiri", () => {
    mockFetch()
    wrap()

    expect(screen.getByLabelText("Kembalikan ke bawaan — Admin")).toBeTruthy()
    expect(screen.getByLabelText("Kembalikan ke bawaan — Kasir")).toBeTruthy()
  })
})

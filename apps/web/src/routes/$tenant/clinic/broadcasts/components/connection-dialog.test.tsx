import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"
import { ConnectionDialog } from "./whatsapp-tools.tsx"

const TRANSLATIONS = {
  broadcast: {
    connection: "Koneksi WhatsApp",
    connection_hint: "Pindai QR untuk menyambungkan nomor klinik.",
    connected: "Tersambung",
    disconnected: "Belum tersambung",
    scan_hint: "Buka WhatsApp di ponsel, lalu pindai kodenya.",
    session_needs_start: "Sesi WhatsApp klinik sedang mati.",
    reconnect: "Sambungkan ulang",
    reconnect_hint: "Nyalakan ulang sesi WhatsApp klinik.",
    waha_error: "Server WAHA tidak merespons.",
    waha_session_missing: "Nama sesi belum diisi.",
  },
  general: { loading: "Memuat..." },
}

interface State {
  available: boolean
  connected: boolean
  number?: string | null
  name?: string | null
  qr?: string | null
  needs_start?: boolean
}

/**
 * Hitung tiap panggilan per endpoint, karena yang diuji justru berapa kali
 * masing-masing dipanggil — bukan apa jawabannya.
 */
function mockFetch(state: State) {
  const calls = { poll: 0, prepare: 0 }

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: unknown) => {
      const url = String(input)

      if (url.includes("/translations")) {
        return { ok: true, status: 200, json: async () => ({ data: TRANSLATIONS }) }
      }

      if (url.includes("/connection/prepare")) {
        calls.prepare++
      } else if (url.includes("/connection")) {
        calls.poll++
      }

      return { ok: true, status: 200, json: async () => ({ data: state }) }
    }),
  )

  return calls
}

function open(state: State) {
  const calls = mockFetch(state)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  // TooltipProvider dipasang di root aplikasi; di sini disediakan sendiri
  // supaya komponennya berdiri seperti saat dipakai.
  render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <ConnectionDialog tenant="klinik-uji" open onOpenChange={() => {}} />
      </TooltipProvider>
    </QueryClientProvider>,
  )

  return calls
}

describe("ConnectionDialog", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations(TRANSLATIONS)
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  /**
   * Inti keluhan dari klinik: sesi dinyalakan ulang tiap kali layarnya
   * menanya, jadi nomornya "keluar terus" dan tidak pernah sempat tersambung.
   * Menyalakan hanya sekali saat dialognya dibuka.
   */
  it("hanya sekali menyalakan sesi, berapa kali pun statusnya ditanya", async () => {
    const calls = open({ available: true, connected: false, qr: "data:image/png;base64,AAA" })

    await waitFor(() => expect(calls.prepare).toBe(1))
    await waitFor(() => expect(calls.poll).toBeGreaterThan(0))

    // Beri ruang bagi tanyaan berikutnya; yang tidak boleh bertambah cuma
    // jumlah penyalaannya.
    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(calls.prepare).toBe(1)
  })

  it("menampilkan nomor yang sedang tersambung", async () => {
    open({
      available: true,
      connected: true,
      number: "628123456789",
      name: "Meba Clinic",
    })

    await waitFor(() => expect(screen.getByText("Tersambung")).toBeTruthy())
    expect(screen.getByText("+628123456789")).toBeTruthy()
  })

  /** Sesi mati tidak menampilkan QR yang tidak akan terbit, tapi jalan keluarnya. */
  it("menawarkan sambung ulang saat sesinya mati", async () => {
    open({ available: true, connected: false, qr: null, needs_start: true })

    await waitFor(() =>
      expect(screen.getByText("Sesi WhatsApp klinik sedang mati.")).toBeTruthy(),
    )
    expect(screen.getByRole("button", { name: "Sambungkan ulang" })).toBeTruthy()
  })
})

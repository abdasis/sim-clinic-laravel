import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { ConnectionDialog } from "./whatsapp-tools.tsx"
import type { ConnectionState } from "./whatsapp-tools.tsx"

const TRANSLATIONS = {
  broadcast: {
    connection: "Koneksi WhatsApp",
    connection_hint: "Pindai QR untuk menyambungkan nomor klinik.",
    connected: "Terhubung",
    disconnected: "Belum terhubung",
    scan_hint: "Buka WhatsApp di ponsel, lalu pindai kodenya.",
    session_needs_start: "Sesi WhatsApp klinik sedang mati.",
    reconnect: "Sambungkan ulang",
    reconnect_hint: "Nyalakan ulang sesi WhatsApp klinik.",
    gateway_error: "Server gateway WhatsApp tidak merespons. Coba lagi sebentar, atau hubungi pengelola platform.",
    gateway_not_ready: "WhatsApp klinik belum terhubung ke gateway. Buka menu Koneksi WhatsApp lalu sambungkan nomornya dulu.",
    pairing_phone: "Nomor WhatsApp",
    pairing_request: "Minta kode pairing",
    pairing_code: "Kode pairing",
    pairing_expires: "Kode berlaku sampai :time",
  },
  general: { loading: "Memuat...", cancel: "Batal", delete: "Hapus" },
}

function state(overrides: Partial<ConnectionState>): ConnectionState {
  return {
    available: true,
    provisioned: true,
    connected: false,
    qr: null,
    number: null,
    name: null,
    error: false,
    ...overrides,
  }
}

/**
 * Hitung tiap panggilan per endpoint, karena yang diuji justru berapa kali
 * masing-masing dipanggil — bukan apa jawabannya.
 */
function mockFetch(current: ConnectionState) {
  const calls = { poll: 0, prepare: 0, pairing: 0, deleteSession: 0 }

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input)
      const method = init?.method ?? "GET"


      if (method === "DELETE" && url.endsWith("/clinic/broadcasts/connection")) {
        calls.deleteSession++
        return { ok: true, status: 204, json: async () => ({}) }
      }

      if (url.includes("/connection/prepare")) {
        calls.prepare++
        return { ok: true, status: 200, json: async () => ({ data: current }) }
      }

      if (url.includes("/connection/pairing")) {
        calls.pairing++
        return {
          ok: true,
          status: 202,
          json: async () => ({
            data: { pairing_code: "ABCD1234", expires_at: "2026-11-04T10:00:00+07:00" },
          }),
        }
      }

      if (url.includes("/connection")) {
        calls.poll++
        return { ok: true, status: 200, json: async () => ({ data: current }) }
      }

      return { ok: true, status: 200, json: async () => ({ data: {} }) }
    }),
  )

  return calls
}

function open(current: ConnectionState) {
  const calls = mockFetch(current)
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
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it("menampilkan QR gateway sebagai gambar dan mengirim kode pairing", async () => {
    const calls = open(
      state({ qr: "data:image/png;base64,AAA", provisioned: true }),
    )

    const image = await screen.findByAltText("QR WhatsApp")
    expect(image.getAttribute("src")).toBe("data:image/png;base64,AAA")

    fireEvent.change(screen.getByLabelText("Nomor WhatsApp"), {
      target: { value: "081234567890" },
    })
    fireEvent.click(screen.getByRole("button", { name: "Minta kode pairing" }))

    expect(await screen.findByText("ABCD1234")).toBeTruthy()
    expect(calls.pairing).toBe(1)
  })

  /**
   * Inti keluhan dari klinik: sesi dinyalakan ulang tiap kali layarnya
   * menanya, jadi nomornya "keluar terus" dan tidak pernah sempat tersambung.
   * Menyalakan hanya sekali saat dialognya dibuka.
   */
  it("hanya sekali menyalakan sesi, berapa kali pun statusnya ditanya", async () => {
    const calls = open(state({ qr: "data:image/png;base64,AAA" }))

    await waitFor(() => expect(calls.prepare).toBe(1))
    await waitFor(() => expect(calls.poll).toBeGreaterThan(0))

    // Beri ruang bagi tanyaan berikutnya; yang tidak boleh bertambah cuma
    // jumlah penyalaannya.
    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(calls.prepare).toBe(1)
  })

  it("menampilkan nomor yang sedang tersambung", async () => {
    open(
      state({
        connected: true,
        number: "628123456789",
        name: "Klinik Uji",
      }),
    )

    await waitFor(() => expect(screen.getByText("Terhubung")).toBeTruthy())
    expect(screen.getByText("+628123456789")).toBeTruthy()
  })

  it("memberi tahu saat gateway tidak tersedia", async () => {
    open(
      state({
        available: false,
        provisioned: false,
      }),
    )

    expect(await screen.findByText(TRANSLATIONS.broadcast.gateway_not_ready)).toBeTruthy()
  })

  it("menampilkan galat gateway tanpa menghentikan pilihan sambung ulang", async () => {
    open(state({ error: true, qr: null }))

    expect(
      await screen.findByText(TRANSLATIONS.broadcast.gateway_error),
    ).toBeTruthy()
    expect(screen.getByRole("button", { name: "Sambungkan ulang" })).toBeTruthy()
  })

  it("meminta konfirmasi sebelum menghapus sesi", async () => {
    const calls = open(
      state({ connected: true, number: "628123456789", name: "Klinik Uji" }),
    )

    fireEvent.click(await screen.findByRole("button", { name: "Hapus sesi" }))
    expect(calls.deleteSession).toBe(0)

    fireEvent.click(await screen.findByRole("button", { name: "Hapus" }))

    await waitFor(() => expect(calls.deleteSession).toBe(1))
  })
})

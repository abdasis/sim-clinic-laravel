import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { BroadcastSettingsDialog } from "./broadcast-settings-dialog.tsx"

const TRANSLATIONS = {
  broadcast: {
    settings: "Pengaturan Pengiriman",
    settings_saved: "Pengaturan pengiriman tersimpan.",
    session_label: "Nama Sesi WhatsApp",
    session_hint: "Pengelola platform mengatur alamat gateway.",
    session_format:
      "Nama sesi hanya boleh huruf kecil, angka, dan strip. Contoh: klinik-satu.",
    session_empty: "Dikosongkan berarti pengiriman otomatis mati.",
    session_locked: "Nama sesi tidak bisa diubah setelah sesi gateway dibuat. Putuskan lalu hapus sesinya bila ingin mengganti nama.",
    gateway_server_unconfigured:
      "Pengelola platform belum mengatur alamat gateway WhatsApp. Hubungi pengelola untuk menyambungkannya.",
  },
  general: { save: "Simpan" },
}

interface Settings {
  session?: string | null
  gateway_available: boolean
}

function mockFetch(settings: Settings, provisioned = true) {
  const mock = vi.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input)


    if (url.endsWith("/broadcasts/connection")) {
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            available: true,
            provisioned,
            connected: false,
            qr: null,
            number: null,
            name: null,
            error: false,
          },
        }),
      }
    }

    if ((init as RequestInit | undefined)?.method === "PUT") {
      return {
        ok: true,
        status: 200,
        json: async () => ({ data: { session: null }, meta: {} }),
      }
    }

    return { ok: true, status: 200, json: async () => ({ data: settings }) }
  })

  vi.stubGlobal("fetch", mock)

  return mock
}

function open(settings: Settings, provisioned = true) {
  const fetchMock = mockFetch(settings, provisioned)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  render(
    <QueryClientProvider client={client}>
      <BroadcastSettingsDialog tenant="klinik-uji" open onOpenChange={() => {}} />
    </QueryClientProvider>,
  )

  return fetchMock
}

describe("BroadcastSettingsDialog", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it("menampilkan nama sesi yang tersimpan", async () => {
    open({ session: "klinik-satu", gateway_available: true }, false)

    await waitFor(() =>
      expect(
        (screen.getByLabelText("Nama Sesi WhatsApp") as HTMLInputElement).value,
      ).toBe("klinik-satu"),
    )
  })

  it("mengirim PUT hanya berisi nama sesi", async () => {
    const fetchMock = open({ session: null, gateway_available: true }, false)

    const field = await screen.findByLabelText("Nama Sesi WhatsApp")
    fireEvent.change(field, { target: { value: "klinik-dua" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      const put = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      )

      expect(put).toBeDefined()
      expect(JSON.parse(String((put?.[1] as RequestInit).body))).toEqual({
        session: "klinik-dua",
      })
    })
  })

  /**
   * Gateway memetakan sesi lewat slug. Menggantinya setelah sesi dibuat
   * meninggalkan sesi lama yang masih tertaut nomor klinik.
   */
  it("mengunci nama sesi setelah sesi gateway dibuat", async () => {
    open({ session: "klinik-satu", gateway_available: true }, true)

    const field = (await screen.findByLabelText(
      "Nama Sesi WhatsApp",
    )) as HTMLInputElement

    await waitFor(() => expect(field.disabled).toBe(true))
    expect(screen.getByText(TRANSLATIONS.broadcast.session_locked)).toBeTruthy()
  })

  it("mengosongkan sesi mengirim null, bukan string kosong", async () => {
    const fetchMock = open({ session: "klinik-satu", gateway_available: true }, false)

    const field = await screen.findByLabelText("Nama Sesi WhatsApp")
    await waitFor(() =>
      expect((field as HTMLInputElement).value).toBe("klinik-satu"),
    )

    fireEvent.change(field, { target: { value: "" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      const put = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      )

      expect(JSON.parse(String((put?.[1] as RequestInit).body))).toEqual({
        session: null,
      })
    })
  })

  it("memberi tahu saat alamat gateway belum diatur pengelola", async () => {
    open({ session: "klinik-satu", gateway_available: false }, false)

    expect(
      await screen.findByText(
        TRANSLATIONS.broadcast.gateway_server_unconfigured,
      ),
    ).toBeTruthy()
  })

  it("tidak menampilkan peringatan gateway saat alamatnya sudah diatur", async () => {
    open({ session: "klinik-satu", gateway_available: true }, false)

    await screen.findByLabelText("Nama Sesi WhatsApp")

    expect(
      screen.queryByText(TRANSLATIONS.broadcast.gateway_server_unconfigured),
    ).toBeNull()
  })

  it("menolak nama sesi berhuruf besar dan berspasi sebelum dikirim", async () => {
    const fetchMock = open({ session: null, gateway_available: true }, false)

    const field = await screen.findByLabelText("Nama Sesi WhatsApp")
    fireEvent.change(field, { target: { value: "Klinik Satu" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    expect(
      await screen.findByText(TRANSLATIONS.broadcast.session_format),
    ).toBeTruthy()

    expect(
      fetchMock.mock.calls.some(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      ),
    ).toBe(false)
  })
})

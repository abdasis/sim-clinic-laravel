import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { BroadcastSettingsDialog } from "./broadcast-settings-dialog.tsx"

const TRANSLATIONS = {
  broadcast: {
    settings: "Pengaturan Pengiriman",
    settings_saved: "Pengaturan pengiriman tersimpan.",
    session_label: "Nama Sesi WAHA",
    session_hint: "Pengelola server mengatur WAHA di halaman platform.",
    session_format:
      "Nama sesi hanya boleh huruf kecil, angka, dan strip. Contoh: klinik-satu.",
    session_empty: "Dikosongkan berarti pengiriman otomatis mati.",
    phone_number_id_label: "Nomor Cloud API Meta",
    phone_number_id_hint:
      "Diisi hanya bila klinik membalas lewat WhatsApp Cloud API Meta.",
    phone_number_id_format: "Nomor Cloud API hanya boleh berisi angka.",
    waha_not_configured: "WAHA belum dikonfigurasi di platform.",
  },
  general: { save: "Simpan" },
}

interface Settings {
  session?: string | null
  phone_number_id?: string | null
  waha_available: boolean
}

/**
 * `useTrans` ikut memanggil /translations. Kalau jawabannya tidak dipisahkan,
 * kamusnya tertimpa dan label berubah jadi kunci mentah — test lalu gagal
 * karena alasan yang berbeda dari yang diuji.
 */
function mockFetch(settings: Settings) {
  const mock = vi.fn(async (input: unknown, init?: RequestInit) => {
    void init
    if (String(input).includes("/translations")) {
      return { ok: true, status: 200, json: async () => ({ data: TRANSLATIONS }) }
    }

    return { ok: true, status: 200, json: async () => ({ data: settings }) }
  })

  vi.stubGlobal("fetch", mock)

  return mock
}

function wrap(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function open(settings: Settings) {
  const fetchMock = mockFetch(settings)

  wrap(
    <BroadcastSettingsDialog tenant="klinik-uji" open onOpenChange={() => {}} />,
  )

  return fetchMock
}

describe("BroadcastSettingsDialog", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations(TRANSLATIONS)
  })

  afterEach(cleanup)

  it("menampilkan nama sesi yang tersimpan", async () => {
    open({ session: "klinik-satu", waha_available: true })

    await waitFor(() =>
      expect(
        (screen.getByLabelText("Nama Sesi WAHA") as HTMLInputElement).value,
      ).toBe("klinik-satu"),
    )
  })

  it("mengirim PUT berisi nama sesi", async () => {
    const fetchMock = open({ session: null, waha_available: true })

    const field = await screen.findByLabelText("Nama Sesi WAHA")
    fireEvent.change(field, { target: { value: "klinik-dua" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      const put = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      )

      expect(put).toBeDefined()
      expect(JSON.parse(String((put?.[1] as RequestInit).body))).toEqual({
        session: "klinik-dua",
        phone_number_id: null,
      })
    })
  })

  /**
   * WAHA menolak nama sesi di luar huruf kecil/angka/strip. Kalau ini lolos ke
   * server, yang muncul di layar admin bukan pesan yang bisa ditindaklanjuti.
   */
  it("menolak nama sesi berhuruf besar dan berspasi sebelum dikirim", async () => {
    const fetchMock = open({ session: null, waha_available: true })

    const field = await screen.findByLabelText("Nama Sesi WAHA")
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

  /**
   * Tanpa server WAHA, nama sesi seakurat apa pun tidak akan mengirim apa-apa.
   * Kliniknya perlu tahu itu urusan pengelola platform, bukan salah isian.
   */
  it("memberi tahu saat server WAHA belum disiapkan pengelola", async () => {
    open({ session: "klinik-satu", waha_available: false })

    expect(
      await screen.findByText(TRANSLATIONS.broadcast.waha_not_configured),
    ).toBeTruthy()
  })

  it("tidak menampilkan peringatan itu saat servernya sudah siap", async () => {
    open({ session: "klinik-satu", waha_available: true })

    await screen.findByLabelText("Nama Sesi WAHA")

    expect(
      screen.queryByText(TRANSLATIONS.broadcast.waha_not_configured),
    ).toBeNull()
  })

  it("mengosongkan sesi mengirim null, bukan string kosong", async () => {
    const fetchMock = open({ session: "klinik-satu", waha_available: true })

    const field = await screen.findByLabelText("Nama Sesi WAHA")
    await waitFor(() =>
      expect((field as HTMLInputElement).value).toBe("klinik-satu"),
    )

    fireEvent.change(field, { target: { value: "" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      const put = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      )

      expect(put).toBeDefined()
      expect(JSON.parse(String((put?.[1] as RequestInit).body))).toEqual({
        session: null,
        phone_number_id: null,
      })
    })
  })

  it("menampilkan nomor Cloud API yang tersimpan", async () => {
    open({
      session: "klinik-satu",
      phone_number_id: "109900000000000",
      waha_available: true,
    })

    await waitFor(() =>
      expect(
        (
          screen.getByLabelText(
            TRANSLATIONS.broadcast.phone_number_id_label,
          ) as HTMLInputElement
        ).value,
      ).toBe("109900000000000"),
    )
  })

  it("mengirim nomor Cloud API bersama nama sesi", async () => {
    const fetchMock = open({ session: "klinik-satu", waha_available: true })

    const field = await screen.findByLabelText(
      TRANSLATIONS.broadcast.phone_number_id_label,
    )
    fireEvent.change(field, { target: { value: "109900000000001" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      const put = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      )

      expect(JSON.parse(String((put?.[1] as RequestInit).body))).toEqual({
        session: "klinik-satu",
        phone_number_id: "109900000000001",
      })
    })
  })

  /** Meta memakai id numerik; huruf apa pun membuat webhook tidak menemukan kliniknya. */
  it("menolak nomor Cloud API yang bukan angka sebelum dikirim", async () => {
    const fetchMock = open({ session: "klinik-satu", waha_available: true })

    const field = await screen.findByLabelText(
      TRANSLATIONS.broadcast.phone_number_id_label,
    )
    fireEvent.change(field, { target: { value: "1099-abc" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    expect(
      await screen.findByText(TRANSLATIONS.broadcast.phone_number_id_format),
    ).toBeTruthy()

    expect(
      fetchMock.mock.calls.some(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      ),
    ).toBe(false)
  })
})

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { AutoReminderDialog } from "./auto-reminder-dialog.tsx"

const TRANSLATIONS = {
  broadcast: {
    auto_reminder_title: "Follow-up Otomatis",
    auto_reminder_hint: "Kirim pesan otomatis ke pasien yang sudah lama tidak datang.",
    auto_reminder_days: "Kirim setelah (hari)",
    auto_reminder_active: "Aktif",
    auto_reminder_saved: "Pengaturan follow-up otomatis tersimpan.",
    auto_reminder_template: "Template pesan",
    auto_reminder_default_template: "Pakai templat bawaan",
    auto_reminder_days_hint: "Dihitung dari rekam medis terakhir.",
  },
  general: { save: "Simpan" },
}

interface Setting {
  days: number | null
  message_template_id: number | null
  template_name: string | null
  is_active: boolean
}

function mockFetch(setting: Setting, templates: { id: number; name: string }[] = []) {
  const mock = vi.fn(async (input: unknown, init?: RequestInit) => {
    void init
    const url = String(input)

    if (url.includes("/translations")) {
      return { ok: true, status: 200, json: async () => ({ data: TRANSLATIONS }) }
    }

    if (url.includes("/message-templates")) {
      return { ok: true, status: 200, json: async () => ({ data: templates }) }
    }

    return { ok: true, status: 200, json: async () => ({ data: setting }) }
  })

  vi.stubGlobal("fetch", mock)

  return mock
}

function open(setting: Setting, templates: { id: number; name: string }[] = []) {
  const fetchMock = mockFetch(setting, templates)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  render(
    <QueryClientProvider client={client}>
      <AutoReminderDialog tenant="klinik-uji" open onOpenChange={() => {}} />
    </QueryClientProvider>,
  )

  return fetchMock
}

const EMPTY: Setting = {
  days: null,
  message_template_id: null,
  template_name: null,
  is_active: false,
}

function putBody(fetchMock: ReturnType<typeof mockFetch>) {
  const put = fetchMock.mock.calls.find(
    ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
  )

  return put ? JSON.parse(String((put[1] as RequestInit).body)) : null
}

describe("AutoReminderDialog", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations(TRANSLATIONS)
  })

  afterEach(cleanup)

  it("mengisi form dari pengaturan tersimpan", async () => {
    open({ days: 45, message_template_id: 7, template_name: "Ajakan", is_active: true }, [
      { id: 7, name: "Ajakan" },
    ])

    await waitFor(() =>
      expect(
        (screen.getByLabelText(/Kirim setelah/) as HTMLInputElement).value,
      ).toBe("45"),
    )

    expect(
      (screen.getByLabelText(/Template pesan/) as HTMLSelectElement).value,
    ).toBe("7")
  })

  it("mengirim angka, bukan teks, saat disimpan", async () => {
    const fetchMock = open(EMPTY)

    const days = await screen.findByLabelText(/Kirim setelah/)
    fireEvent.change(days, { target: { value: "60" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() =>
      expect(putBody(fetchMock)).toEqual({
        days: 60,
        message_template_id: null,
        is_active: false,
      }),
    )
  })

  it("mengirim id templat sebagai angka saat templat dipilih", async () => {
    const fetchMock = open(EMPTY, [{ id: 3, name: "Ajakan kembali" }])

    const days = await screen.findByLabelText(/Kirim setelah/)
    fireEvent.change(days, { target: { value: "30" } })
    fireEvent.change(screen.getByLabelText(/Template pesan/), {
      target: { value: "3" },
    })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() =>
      expect(putBody(fetchMock)).toEqual({
        days: 30,
        message_template_id: 3,
        is_active: false,
      }),
    )
  })

  /**
   * Ambangnya menentukan siapa yang dikirimi pesan. Nol hari berarti seluruh
   * pasien tersapu sekaligus, jadi ditolak sebelum sampai ke server.
   */
  it("menolak ambang hari di luar rentang sebelum dikirim", async () => {
    const fetchMock = open(EMPTY)

    const days = await screen.findByLabelText(/Kirim setelah/)
    const save = screen.getByRole("button", { name: "Simpan" })

    fireEvent.change(days, { target: { value: "0" } })
    fireEvent.click(save)
    await waitFor(() => expect(putBody(fetchMock)).toBeNull())

    fireEvent.change(days, { target: { value: "999" } })
    fireEvent.click(save)
    await waitFor(() => expect(putBody(fetchMock)).toBeNull())

    // Nilai sah dikirim dari tombol yang sama. Tanpa langkah ini, dua
    // pemeriksaan di atas bisa lolos hanya karena permintaannya belum sempat
    // berangkat — bukan karena ditolak.
    fireEvent.change(days, { target: { value: "30" } })
    fireEvent.click(save)

    await waitFor(() => expect(putBody(fetchMock)).toEqual({
      days: 30,
      message_template_id: null,
      is_active: false,
    }))
  })

  it("mengirim status aktif saat saklarnya dinyalakan", async () => {
    const fetchMock = open(EMPTY)

    const days = await screen.findByLabelText(/Kirim setelah/)
    fireEvent.change(days, { target: { value: "30" } })
    fireEvent.click(screen.getByRole("switch", { name: "Aktif" }))
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() =>
      expect(putBody(fetchMock)).toEqual({
        days: 30,
        message_template_id: null,
        is_active: true,
      }),
    )
  })
})

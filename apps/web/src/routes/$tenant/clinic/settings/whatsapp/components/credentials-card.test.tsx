import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { toast } from "sonner"

import { CredentialsCard } from "./credentials-card.tsx"

vi.mock("sonner", () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const SETTINGS = {
  gateway_available: true,
  api_key_configured: true,
  webhook_configured: false,
  gateway_base_url: "https://gateway.klinik.test",
  session: "klinik-satu",
  daily_message_limit: 500,
}

const SAVED_MESSAGE = "Kredensial gateway tersimpan."
const WEBHOOK_URL =
  "https://api.klinik.test/api/webhooks/whatsapp/klinik-uji"

function stubClipboard() {
  const writeText = vi.fn(async () => undefined)
  Object.defineProperty(navigator, "clipboard", {
    configurable: true,
    value: { writeText },
  })
  return writeText
}

function jsonResponse(body: unknown) {
  return {
    ok: true,
    status: 200,
    json: async () => body,
  }
}

function renderCard(settings: Record<string, unknown> = SETTINGS) {
  const fetchMock = vi.fn(async (_input: unknown, init?: RequestInit) => {
    if (init?.method === "PUT") {
      return jsonResponse({ data: settings, meta: { message: SAVED_MESSAGE } })
    }

    return jsonResponse({ data: settings })
  })
  vi.stubGlobal("fetch", fetchMock)

  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })

  render(
    <QueryClientProvider client={client}>
      <CredentialsCard tenant="klinik-uji" />
    </QueryClientProvider>,
  )

  return fetchMock
}

function putBody(fetchMock: ReturnType<typeof vi.fn>) {
  const put = fetchMock.mock.calls.find(
    ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
  )
  expect(put).toBeDefined()
  return JSON.parse(String((put?.[1] as RequestInit).body)) as Record<
    string,
    unknown
  >
}

describe("CredentialsCard", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    vi.mocked(toast.success).mockClear()
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
    Object.defineProperty(navigator, "clipboard", {
      configurable: true,
      value: undefined,
    })
  })

  it("menampilkan URL webhook bila sudah terdaftar", async () => {
    renderCard({ ...SETTINGS, webhook_configured: true, webhook_url: WEBHOOK_URL })

    expect(await screen.findByText(WEBHOOK_URL)).toBeTruthy()
    expect(
      screen.getByRole("button", { name: "Salin URL webhook" }),
    ).toBeTruthy()
  })

  it("menampilkan ajakan mendaftarkan webhook saat URL belum ada", async () => {
    renderCard({ ...SETTINGS, webhook_url: null })

    expect(
      await screen.findByText("Daftarkan webhook untuk mendapatkan URL."),
    ).toBeTruthy()
    expect(
      screen.queryByRole("button", { name: "Salin URL webhook" }),
    ).toBeNull()
  })

  it("menyalin URL webhook dan menampilkan umpan balik", async () => {
    const writeText = stubClipboard()
    renderCard({ ...SETTINGS, webhook_configured: true, webhook_url: WEBHOOK_URL })

    fireEvent.click(
      await screen.findByRole("button", { name: "Salin URL webhook" }),
    )

    await waitFor(() => expect(writeText).toHaveBeenCalledWith(WEBHOOK_URL))
    expect(
      await screen.findByRole("button", { name: "Tersalin" }),
    ).toBeTruthy()
  })

  it("menampilkan form kredensial yang sudah terisi alamat gateway", async () => {
    renderCard()

    const baseUrl = (await screen.findByLabelText(
      /Alamat gateway/,
    )) as HTMLInputElement
    await waitFor(() =>
      expect(baseUrl.value).toBe("https://gateway.klinik.test"),
    )

    expect(screen.getByLabelText("Kunci API")).toBeTruthy()
    expect(
      screen.getByRole("button", { name: "Simpan kredensial" }),
    ).toBeTruthy()
  })

  it("menyimpan alamat gateway dan kunci API baru", async () => {
    const fetchMock = renderCard()

    const apiKey = await screen.findByLabelText("Kunci API")
    fireEvent.change(apiKey, { target: { value: "kunci-baru" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan kredensial" }))

    await waitFor(() =>
      expect(putBody(fetchMock)).toEqual({
        base_url: "https://gateway.klinik.test",
        api_key: "kunci-baru",
        daily_message_limit: 500,
      }),
    )
  })

  it("mengirim batas pesan harian sebagai angka", async () => {
    const fetchMock = renderCard()

    const limit = await screen.findByLabelText(/Batas pesan harian/)
    await waitFor(() => expect((limit as HTMLInputElement).value).toBe("500"))
    fireEvent.change(limit, { target: { value: "150" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan kredensial" }))

    await waitFor(() =>
      expect(putBody(fetchMock).daily_message_limit).toBe(150),
    )
  })

  it("mengirim null saat batas pesan harian dikosongkan", async () => {
    const fetchMock = renderCard()

    const limit = await screen.findByLabelText(/Batas pesan harian/)
    await waitFor(() => expect((limit as HTMLInputElement).value).toBe("500"))
    fireEvent.change(limit, { target: { value: "" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan kredensial" }))

    await waitFor(() =>
      expect(putBody(fetchMock).daily_message_limit).toBeNull(),
    )
  })

  it("menampilkan saran batas aman pengiriman", async () => {
    renderCard()

    expect(
      await screen.findByText(/Saran aman: 50–100 pesan\/hari/),
    ).toBeTruthy()
    expect(screen.getByText(/risiko nomor diblokir WhatsApp/)).toBeTruthy()
  })

  it("tidak mengirim kunci API bila kolomnya kosong", async () => {
    const fetchMock = renderCard()

    await screen.findByLabelText("Kunci API")
    fireEvent.click(screen.getByRole("button", { name: "Simpan kredensial" }))

    await waitFor(() => {
      const body = putBody(fetchMock)
      expect(body.base_url).toBe("https://gateway.klinik.test")
      expect(body).not.toHaveProperty("api_key")
    })
  })

  it("menampilkan pesan server dan mengosongkan kolom kunci setelah sukses", async () => {
    renderCard()

    const apiKey = (await screen.findByLabelText("Kunci API")) as HTMLInputElement
    fireEvent.change(apiKey, { target: { value: "kunci-baru" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan kredensial" }))

    await waitFor(() =>
      expect(toast.success).toHaveBeenCalledWith(SAVED_MESSAGE),
    )
    await waitFor(() => expect(apiKey.value).toBe(""))
  })

  it("tetap menampilkan status gateway tanpa membocorkan nilai kunci", async () => {
    renderCard()

    expect(await screen.findByText("https://gateway.klinik.test")).toBeTruthy()
    expect(screen.getByText("klinik-satu")).toBeTruthy()
    expect(screen.getByText("500")).toBeTruthy()
    expect(screen.getByText("Terisi")).toBeTruthy()
    expect(screen.getByText("Belum")).toBeTruthy()
  })
})

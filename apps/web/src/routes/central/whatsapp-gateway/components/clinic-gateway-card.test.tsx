import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { ClinicGatewayCard } from "./clinic-gateway-card.tsx"
import type { ClinicGatewayRow } from "./gateway-types.ts"

const ROW: ClinicGatewayRow = {
  tenant_id: 3,
  name: "Klinik Cantik",
  slug: "klinik-cantik",
  session: null,
  daily_message_limit: null,
  api_key_configured: false,
  webhook_configured: false,
  webhook_token_set: false,
}

const WEBHOOK_URL =
  "https://api.klinik.test/api/webhooks/whatsapp/klinik-cantik"

function stubClipboard() {
  const writeText = vi.fn(async () => undefined)
  Object.defineProperty(navigator, "clipboard", {
    configurable: true,
    value: { writeText },
  })
  return writeText
}

function renderCard(row: ClinicGatewayRow = ROW) {
  vi.stubGlobal(
    "fetch",
    vi.fn(async () => ({
      ok: true,
      status: 200,
      json: async () => ({ data: {} }),
    })),
  )

  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  render(
    <QueryClientProvider client={client}>
      <ClinicGatewayCard row={row} />
    </QueryClientProvider>,
  )
}

describe("ClinicGatewayCard", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
    Object.defineProperty(navigator, "clipboard", {
      configurable: true,
      value: undefined,
    })
  })

  it("menampilkan URL webhook bila klinik sudah terdaftar", () => {
    renderCard({
      ...ROW,
      webhook_configured: true,
      webhook_url: WEBHOOK_URL,
    })

    expect(screen.getByText(WEBHOOK_URL)).toBeTruthy()
    expect(
      screen.getByRole("button", { name: "Salin URL webhook" }),
    ).toBeTruthy()
  })

  it("menampilkan status belum terdaftar saat URL webhook kosong", () => {
    renderCard()

    expect(screen.getByText("Belum terdaftar.")).toBeTruthy()
    expect(
      screen.queryByRole("button", { name: "Salin URL webhook" }),
    ).toBeNull()
  })

  it("menyalin URL webhook dan menampilkan umpan balik", async () => {
    const writeText = stubClipboard()
    renderCard({
      ...ROW,
      webhook_configured: true,
      webhook_url: WEBHOOK_URL,
    })

    fireEvent.click(screen.getByRole("button", { name: "Salin URL webhook" }))

    await waitFor(() => expect(writeText).toHaveBeenCalledWith(WEBHOOK_URL))
    expect(
      await screen.findByRole("button", { name: "Tersalin" }),
    ).toBeTruthy()
  })

  it("menonaktifkan pendaftaran webhook selama API key belum ada", () => {
    renderCard()

    const button = screen.getByRole("button", {
      name: "Daftarkan webhook",
    }) as HTMLButtonElement

    expect(button.disabled).toBe(true)
  })
})

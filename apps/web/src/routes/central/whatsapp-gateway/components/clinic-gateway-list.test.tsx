import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { ClinicGatewayList } from "./clinic-gateway-list.tsx"

const ROWS = [
  {
    tenant_id: 1,
    name: "Klinik Sehat",
    slug: "klinik-sehat",
    session: "klinik-satu",
    daily_message_limit: null,
    api_key_configured: true,
    webhook_configured: true,
    webhook_token_set: true,
  },
  {
    tenant_id: 2,
    name: "Klinik Cantik",
    slug: "klinik-cantik",
    session: null,
    daily_message_limit: 250,
    api_key_configured: false,
    webhook_configured: false,
    webhook_token_set: false,
  },
]

function renderList() {
  vi.stubGlobal(
    "fetch",
    vi.fn(async () => ({
      ok: true,
      status: 200,
      json: async () => ({ data: ROWS }),
    })),
  )

  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  render(
    <QueryClientProvider client={client}>
      <ClinicGatewayList />
    </QueryClientProvider>,
  )
}

describe("ClinicGatewayList", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it("menampilkan status gateway tiap klinik", async () => {
    renderList()

    expect(await screen.findByText("Klinik Sehat")).toBeTruthy()
    expect(screen.getByText("Klinik Cantik")).toBeTruthy()

    expect(screen.getByText("Terisi")).toBeTruthy()
    expect(screen.getByText("Terdaftar")).toBeTruthy()
    expect(screen.getByText("klinik-satu")).toBeTruthy()
    expect(screen.getByText("Bawaan")).toBeTruthy()

    expect(screen.getAllByText("Belum")).toHaveLength(2)
    expect(screen.getByText("250")).toBeTruthy()
  })
})

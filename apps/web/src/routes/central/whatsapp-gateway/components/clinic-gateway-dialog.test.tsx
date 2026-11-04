import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { ClinicGatewayDialog } from "./clinic-gateway-dialog.tsx"
import type { ClinicGatewayRow } from "./gateway-types.ts"

const ROW: ClinicGatewayRow = {
  tenant_id: 7,
  name: "Klinik Sehat",
  slug: "klinik-sehat",
  session: "klinik-satu",
  daily_message_limit: 100,
  api_key_configured: true,
  webhook_configured: false,
  webhook_token_set: false,
}

function renderDialog() {
  const fetchMock = vi.fn(
    async (_input: unknown, _init?: RequestInit) => ({
      ok: true,
      status: 200,
      json: async () => ({ data: {}, meta: {} }),
    }),
  )
  vi.stubGlobal("fetch", fetchMock)

  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  render(
    <QueryClientProvider client={client}>
      <ClinicGatewayDialog row={ROW} open onOpenChange={() => {}} />
    </QueryClientProvider>,
  )

  return fetchMock
}

describe("ClinicGatewayDialog", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it("mengirim kunci API baru ke endpoint klinik yang tepat", async () => {
    const fetchMock = renderDialog()

    const apiKey = await screen.findByLabelText("Kunci API")
    fireEvent.change(apiKey, { target: { value: "kunci-baru" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      const put = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      )

      expect(put).toBeDefined()
      expect(String(put?.[0])).toContain(
        "/central/whatsapp-gateway/clinics/7",
      )
      expect(JSON.parse(String((put?.[1] as RequestInit).body))).toEqual({
        api_key: "kunci-baru",
        session: "klinik-satu",
        daily_message_limit: 100,
      })
    })
  })

  it("kunci API kosong dikirim sebagai null agar key lama dipertahankan", async () => {
    const fetchMock = renderDialog()

    await screen.findByLabelText("Kunci API")
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      const put = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "PUT",
      )

      expect(
        JSON.parse(String((put?.[1] as RequestInit).body)).api_key,
      ).toBeNull()
    })
  })
})

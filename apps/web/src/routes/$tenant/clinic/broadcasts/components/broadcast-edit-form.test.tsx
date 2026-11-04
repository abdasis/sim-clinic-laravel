import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { BroadcastEditForm } from "./broadcast-edit-form.tsx"
import type { BroadcastDetail, BroadcastTemplate } from "./broadcast-types.ts"

// Formulir memanggil useNavigate; di test tidak ada router yang dipasang.
vi.mock("@tanstack/react-router", () => ({
  useNavigate: () => vi.fn(),
}))


function broadcast(): BroadcastDetail {
  return {
    id: 7,
    title: "Promo November",
    message: "Halo",
    status: "draft",
    audience_label: "Semua pasien",
    audience: "all",
    audience_params: null,
    can_edit: true,
    // Data lama: template tersimpan tanpa daftar params.
    template: { body: "Halo {{1}}" } as BroadcastTemplate,
    recipients_total: 0,
    recipients_sent: 0,
    recipients_pending: 0,
    recipients_failed: 0,
    recipients: [],
  }
}

describe("BroadcastEditForm", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")

    vi.stubGlobal(
      "fetch",
      vi.fn(async (input: unknown) => {
        const url = String(input)


        if (url.includes("/audience-preview")) {
          return {
            ok: true,
            status: 200,
            json: async () => ({
              data: { count: 0, without_phone: 0, opted_out: 0, recipients: [] },
            }),
          }
        }

        return { ok: true, status: 200, json: async () => ({ data: {} }) }
      }),
    )
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it("merender template tanpa params tanpa melempar", async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    expect(() =>
      render(
        <QueryClientProvider client={client}>
          <BroadcastEditForm tenant="klinik-uji" broadcast={broadcast()} />
        </QueryClientProvider>,
      ),
    ).not.toThrow()

    expect(await screen.findByDisplayValue("Halo {{1}}")).toBeTruthy()
    expect(screen.getByLabelText("Isi bagian 1")).toBeTruthy()
  })
})

import { afterEach, beforeEach, describe, expect, it } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { BroadcastDetailHeader } from "./broadcast-detail-header.tsx"
import type { BroadcastDetail } from "./broadcast-types.ts"

const TRANSLATIONS = {
  broadcast: {
    gateway_not_ready: "WhatsApp klinik belum terhubung ke gateway. Buka menu Koneksi WhatsApp lalu sambungkan nomornya dulu.",
    send_all: "Kirim Semua",
    start: "Mulai",
    progress: ":sent dari :total terkirim",
    pause: "Jeda",
    resume: "Lanjutkan",
    cancel_campaign: "Batalkan",
  },
  general: {
    delete: "Hapus",
    loading: "Memuat...",
  },
}

function detail(overrides: Partial<BroadcastDetail> = {}): BroadcastDetail {
  return {
    id: 1,
    title: "Promo November",
    message: "Halo semua",
    status: "ready",
    status_label: "Siap dikirim",
    kind_label: "Promosi",
    audience_label: "Semua pasien",
    audience_params: null,
    can_edit: false,
    recipients_total: 10,
    recipients_sent: 0,
    recipients_pending: 10,
    recipients_failed: 0,
    recipients: [],
    ...overrides,
  }
}

function renderHeader(
  gatewayReady: boolean,
  options: { quotaSpent?: boolean; status?: string } = {},
) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <BroadcastDetailHeader
          tenant="klinik-uji"
          broadcast={detail({ status: options.status ?? "ready" })}
          gatewayReady={gatewayReady}
          quotaSpent={options.quotaSpent ?? false}
          templateInfo={null}
          statusPending={false}
          sendAllPending={false}
          onPause={() => {}}
          onResume={() => {}}
          onCancel={() => {}}
          onSendAll={() => {}}
          onDelete={() => {}}
        />
      </TooltipProvider>
    </QueryClientProvider>,
  )
}

function button(name: string): HTMLButtonElement {
  return screen.getByRole("button", { name }) as HTMLButtonElement
}

describe("BroadcastDetailHeader", () => {
  beforeEach(() => {
  })

  afterEach(() => {
    cleanup()
  })

  it("menampilkan tombol mulai dan jeda saat gateway siap", () => {
    renderHeader(true)

    expect(screen.getByRole("button", { name: "Mulai" })).toBeTruthy()
    expect(screen.getByRole("button", { name: "Jeda" })).toBeTruthy()
    expect(screen.queryByText(TRANSLATIONS.broadcast.gateway_not_ready)).toBeNull()
  })

  it("menonaktifkan tombol mulai dan menjelaskan sebabnya saat gateway belum siap", () => {
    renderHeader(false)

    expect(button("Mulai").disabled).toBe(true)
    // Kunci lama `cloud_not_ready` tidak ada lagi; yang tampil harus versi gateway.
    expect(screen.getByText(TRANSLATIONS.broadcast.gateway_not_ready)).toBeTruthy()
  })

  it("menonaktifkan tombol mulai saat jatah harian habis", () => {
    renderHeader(true, { quotaSpent: true })

    expect(button("Mulai").disabled).toBe(true)
  })

  it("menonaktifkan tombol lanjutkan saat jatah harian habis", () => {
    renderHeader(true, { quotaSpent: true, status: "paused" })

    expect(button("Lanjutkan").disabled).toBe(true)
  })

  it("tetap mengaktifkan tombol kirim dan lanjutkan saat jatah masih tersisa", () => {
    renderHeader(true, { quotaSpent: false })

    expect(button("Mulai").disabled).toBe(false)

    cleanup()
    renderHeader(true, { quotaSpent: false, status: "paused" })

    expect(button("Lanjutkan").disabled).toBe(false)
  })
})

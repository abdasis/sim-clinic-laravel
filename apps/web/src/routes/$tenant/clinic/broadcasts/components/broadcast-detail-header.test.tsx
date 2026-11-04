import { afterEach, beforeEach, describe, expect, it } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"
import { BroadcastDetailHeader } from "./broadcast-detail-header.tsx"
import type { BroadcastDetail } from "./broadcast-types.ts"

const TRANSLATIONS = {
  broadcast: {
    gateway_not_ready: "WhatsApp klinik belum terhubung ke gateway.",
    send_all: "Kirim Semua",
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
    status: "draft",
    status_label: "Draf",
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

function renderHeader(gatewayReady: boolean) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <BroadcastDetailHeader
        tenant="klinik-uji"
        broadcast={detail()}
        gatewayReady={gatewayReady}
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

describe("BroadcastDetailHeader", () => {
  beforeEach(() => {
    setTranslations(TRANSLATIONS)
  })

  afterEach(() => {
    cleanup()
  })

  it("menampilkan tombol kirim saat gateway siap", () => {
    renderHeader(true)

    expect(screen.getByRole("button", { name: "Kirim Semua" })).toBeTruthy()
    expect(screen.queryByText(TRANSLATIONS.broadcast.gateway_not_ready)).toBeNull()
  })

  it("menyembunyikan tombol kirim dan menjelaskan sebabnya saat gateway belum siap", () => {
    renderHeader(false)

    expect(screen.queryByRole("button", { name: "Kirim Semua" })).toBeNull()
    // Kunci lama `cloud_not_ready` tidak ada lagi; yang tampil harus versi gateway.
    expect(screen.getByText(TRANSLATIONS.broadcast.gateway_not_ready)).toBeTruthy()
  })
})

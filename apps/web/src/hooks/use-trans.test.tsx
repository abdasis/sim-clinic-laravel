import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { useTrans } from "./use-trans.ts"
import { setTranslations } from "#/utils/trans.ts"

const GROUPS = { general: { save: "Simpan" } }

function Probe() {
  const { t, ready } = useTrans()

  return (
    <div>
      <span data-testid="ready">{ready ? "siap" : "menunggu"}</span>
      <span data-testid="label">{t("general.save")}</span>
    </div>
  )
}

/** Fetch yang tidak pernah selesai — meniru jaringan lambat. */
function stallingFetch() {
  const mock = vi.fn(() => new Promise(() => {}))
  vi.stubGlobal("fetch", mock)

  return mock
}

function draw() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <Probe />
    </QueryClientProvider>,
  )
}

describe("useTrans", () => {
  beforeEach(() => {
    window.localStorage.clear()
    setTranslations({})
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  /**
   * Inti isu #321: shell admin menahan tampilannya sampai `ready`, jadi
   * menunggu 71KB JSON berarti layar kosong tiap kali halaman dimuat ulang.
   */
  it("siap tanpa menunggu jaringan kalau salinannya sudah ada", async () => {
    window.localStorage.setItem(
      "clinic_translations",
      JSON.stringify({ locale: "default", version: "abc", groups: GROUPS }),
    )
    stallingFetch()

    draw()

    await waitFor(() =>
      expect(screen.getByTestId("ready").textContent).toBe("siap"),
    )
    expect(screen.getByTestId("label").textContent).toBe("Simpan")
  })

  /** Tanpa salinan, perilakunya seperti semula: menunggu jawabannya. */
  it("menunggu jaringan kalau belum punya salinan", async () => {
    stallingFetch()

    draw()

    expect(screen.getByTestId("ready").textContent).toBe("menunggu")
  })

  /** Jawaban dari server menimpa salinan lama, jadi basinya tidak menetap. */
  it("menyimpan salinan baru setelah jawabannya datang", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => ({
        ok: true,
        status: 200,
        json: async () => ({ data: GROUPS, meta: { version: "v2" } }),
      })),
    )

    draw()

    await waitFor(() => {
      const raw = window.localStorage.getItem("clinic_translations")
      expect(raw).toBeTruthy()
      expect(JSON.parse(raw as string).version).toBe("v2")
    })
  })

  /** Salinan milik bahasa lain tidak boleh dipakai. */
  it("mengabaikan salinan dari locale yang berbeda", () => {
    window.localStorage.setItem(
      "clinic_translations",
      JSON.stringify({ locale: "en", version: "abc", groups: GROUPS }),
    )
    stallingFetch()

    draw()

    expect(screen.getByTestId("ready").textContent).toBe("menunggu")
  })
})

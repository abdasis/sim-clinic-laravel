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

  /**
   * Menulis 71KB JSON ke localStorage itu sinkron dan memblokir utas utama.
   * Murah di laptop, terasa di tablet klinik — dan tiap kali halaman dimuat
   * ulang ia dilakukan lagi untuk isi yang sama persis.
   */
  describe("menulis ulang salinan hanya kalau isinya berganti", () => {
    function jawab(version: string, groups = GROUPS) {
      vi.stubGlobal(
        "fetch",
        vi.fn(async () => ({
          ok: true,
          status: 200,
          json: async () => ({ data: groups, meta: { version } }),
        })),
      )
    }

    /**
     * Pengintai tulis, dipasang setelah salinan awalnya terpasang.
     *
     * Yang diintai `Storage.prototype`, bukan `window.localStorage`:
     * metodenya tinggal di prototipe, jadi mengintai instansnya tidak
     * menangkap apa pun — dan tes "tidak menulis" akan lulus tanpa pernah
     * benar-benar memeriksa sesuatu.
     */
    function intaiTulis() {
      return vi.spyOn(Storage.prototype, "setItem")
    }

    it("tidak menulis apa pun saat versinya sama", async () => {
      window.localStorage.setItem(
        "clinic_translations",
        JSON.stringify({ locale: "default", version: "v1", groups: GROUPS }),
      )
      jawab("v1")

      const tulis = intaiTulis()
      draw()

      await waitFor(() =>
        expect(screen.getByTestId("label").textContent).toBe("Simpan"),
      )
      expect(tulis).not.toHaveBeenCalled()
    })

    it("menulis saat versinya berganti", async () => {
      window.localStorage.setItem(
        "clinic_translations",
        JSON.stringify({ locale: "default", version: "v1", groups: GROUPS }),
      )
      jawab("v2", { general: { save: "Simpan perubahan" } })

      const tulis = intaiTulis()
      draw()

      await waitFor(() =>
        expect(JSON.parse(window.localStorage.getItem("clinic_translations") as string).version).toBe("v2"),
      )
      expect(tulis).toHaveBeenCalled()
    })

    /**
     * Server yang tidak memberi penanda versi tidak memberi dasar untuk
     * melewatkan apa pun — lebih baik menulis sia-sia daripada menyimpan
     * salinan basi tanpa pernah tahu.
     */
    it("selalu menulis saat server tidak memberi versi", async () => {
      window.localStorage.setItem(
        "clinic_translations",
        JSON.stringify({ locale: "default", version: "", groups: GROUPS }),
      )
      jawab("")

      const tulis = intaiTulis()
      draw()

      await waitFor(() => expect(tulis).toHaveBeenCalled())
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

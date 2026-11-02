import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"

// Link butuh konteks router penuh; yang diuji di sini keputusan melipat,
// bukan navigasinya, jadi anchor biasa sudah mewakili.
vi.mock("@tanstack/react-router", () => ({
  Link: ({ to, params: _params, children, ...rest }: Record<string, unknown>) => (
    <a href={String(to)} {...(rest as object)}>
      {children as React.ReactNode}
    </a>
  ),
}))

const { ShellBreadcrumb } = await import("./shell-breadcrumb.tsx")

const JENJANG = [
  { label: "Meba Clinic Yogyakarta", to: "/klinik" },
  { label: "Pasien", to: "/klinik/pasien" },
  { label: "Siti Nurhaliza Wijayakusuma", to: "/klinik/pasien/1" },
  { label: "Rekam Medis" },
]

const RO_ASLI = globalThis.ResizeObserver
const RECT_ASLI = HTMLElement.prototype.getBoundingClientRect

/**
 * Lebar wadah yang dilaporkan ke komponen. `null` meniru keadaan sebelum
 * terukur: di server, pada render pertama, dan saat elemennya tersembunyi.
 */
function pasangWadah(lebar: number | null) {
  class Pelapor {
    constructor(private cb: ResizeObserverCallback) {}
    observe() {
      this.cb([], this as unknown as ResizeObserver)
    }
    unobserve() {}
    disconnect() {}
  }

  globalThis.ResizeObserver = Pelapor as unknown as typeof ResizeObserver

  Object.defineProperty(HTMLElement.prototype, "getBoundingClientRect", {
    configurable: true,
    value: () => ({
      width: lebar ?? 0,
      height: 24,
      top: 0,
      left: 0,
      right: lebar ?? 0,
      bottom: 24,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    }),
  })
}

/** Tier layar, dipakai hanya sebagai cadangan sebelum lebarnya terukur. */
function pasangTier(lebarLayar: number) {
  Object.defineProperty(window, "matchMedia", {
    writable: true,
    value: (query: string) => {
      const cocok = /min-width:\s*([\d.]+)rem/.exec(query)
      const ambang = cocok ? parseFloat(cocok[1]) * 16 : 0

      return {
        matches: lebarLayar >= ambang,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        dispatchEvent: () => false,
        onchange: null,
      }
    },
  })
}

const menu = () => screen.queryByLabelText("Ruas sebelumnya")
const ruasTerbaca = () =>
  screen
    .getAllByRole("listitem")
    .map((li) => li.textContent?.replace("More", "").trim() ?? "")
    .filter((teks) => teks.length > 0)

afterEach(() => {
  cleanup()
  globalThis.ResizeObserver = RO_ASLI
  Object.defineProperty(HTMLElement.prototype, "getBoundingClientRect", {
    configurable: true,
    value: RECT_ASLI,
  })
})

/**
 * Yang memutuskan breadcrumb dilipat bukan ukuran layar melainkan ruang yang
 * benar-benar tersisa untuknya.
 *
 * Bedanya bukan teoretis. Di iPad potret (768px) dengan sidebar terbuka,
 * breadcrumb hanya kebagian ~431px sementara jenjang terdalam klinik menuntut
 * ~450px — dan dulu tier "tablet" memutuskan "tampilkan penuh", sehingga
 * seluruh ruasnya menyusut rata sampai "Kasir" pun jadi "Kas..". Di layar yang
 * sama dengan sidebar diciutkan jadi rel, ruangnya ~631px dan jenjang penuh
 * muat tanpa satu pun ruas terpotong. Tier tidak bisa membedakan keduanya.
 */
describe("ShellBreadcrumb melipat menurut ruang yang terukur", () => {
  beforeEach(() => pasangTier(1024))

  it("melipat di iPad potret dengan sidebar terbuka", () => {
    pasangWadah(431)
    render(<ShellBreadcrumb items={JENJANG} />)

    expect(menu()).not.toBeNull()
    // Induk langsung dan halaman aktif: dua itu yang menjawab "saya di mana".
    expect(ruasTerbaca()).toEqual([
      "Siti Nurhaliza Wijayakusuma",
      "Rekam Medis",
    ])
  })

  /** Layar yang sama, sidebar diciutkan jadi rel: jenjangnya muat utuh. */
  it("membentangkan jenjang penuh saat sidebar diciutkan", () => {
    pasangWadah(631)
    render(<ShellBreadcrumb items={JENJANG} />)

    expect(menu()).toBeNull()
    expect(ruasTerbaca()).toEqual(JENJANG.map((r) => r.label))
  })

  it("melipat di ponsel", () => {
    pasangWadah(309)
    render(<ShellBreadcrumb items={JENJANG} />)

    expect(menu()).not.toBeNull()
    expect(ruasTerbaca()).toHaveLength(2)
  })

  /**
   * Sebelum terukur, tier yang menjawab: melipat lalu membentang kembali
   * sesaat setelah halaman tampil lebih mengganggu daripada sebaliknya.
   */
  describe("sebelum lebarnya terukur", () => {
    it("mengikuti tier sempit", () => {
      pasangTier(390)
      pasangWadah(null)
      render(<ShellBreadcrumb items={JENJANG} />)

      expect(menu()).not.toBeNull()
    })

    it("mengikuti tier lebar", () => {
      pasangTier(1440)
      pasangWadah(null)
      render(<ShellBreadcrumb items={JENJANG} />)

      expect(menu()).toBeNull()
    })
  })

  /** Tidak ada yang bisa dilipat dari jenjang dua ruas. */
  it("tidak melipat jenjang pendek sesempit apa pun ruangnya", () => {
    pasangWadah(120)
    render(<ShellBreadcrumb items={JENJANG.slice(-2)} />)

    expect(menu()).toBeNull()
    expect(ruasTerbaca()).toHaveLength(2)
  })
})

/**
 * Prioritas penyusutan. Kalau semua ruas menyusut rata, yang tersisa terbaca
 * justru bukan yang dicari pembaca: nama klinik jadi "m.." dan nomor notanya
 * ikut terpotong di ujung.
 */
describe("prioritas penyusutan ruas", () => {
  beforeEach(() => {
    pasangTier(1024)
    pasangWadah(631)
  })

  it("menahan halaman aktif dan merelakan ruas tengah", () => {
    render(<ShellBreadcrumb items={JENJANG} />)

    const semua = screen.getAllByRole("listitem")
    const aktif = semua[semua.length - 1]
    const tengah = semua[0]

    // Seperempat kecepatan: halaman aktif praktis bertahan utuh.
    expect(aktif.className).toContain("shrink-[0.25]")
    // Ruas tengah mengalah, tapi punya batas bawah supaya tidak habis
    // jadi satu huruf.
    expect(tengah.className).toContain("shrink")
    expect(tengah.className).toContain("min-w-10")
  })

  /** Batas lebarnya tetap berlaku di tablet, bukan dilepas jadi max-w-none. */
  it("membatasi lebar ruas tengah di semua tier", () => {
    render(<ShellBreadcrumb items={JENJANG} />)

    const tautan = screen.getByRole("link", { name: "Meba Clinic Yogyakarta" })

    expect(tautan.className).toContain("max-w-32")
    expect(tautan.className).toContain("sm:max-w-40")
    expect(tautan.className).not.toContain("max-w-none")
  })
})

import { renderHook } from "@testing-library/react"
import { afterEach, describe, expect, it } from "vitest"

import {
  DESKTOP_MIN_WIDTH,
  RAIL_MIN_WIDTH,
  TABLET_MIN_WIDTH,
  hasRail,
  isNarrowTier,
  useIsMobile,
  useLayoutTier,
} from "./use-mobile.ts"

const REAL_MATCH_MEDIA = window.matchMedia

function setViewport(width: number): void {
  Object.defineProperty(window, "innerWidth", {
    value: width,
    configurable: true,
    writable: true,
  })
}

/**
 * Memaksa media query menjawab dari lebar CSS yang diberikan, bukan dari
 * `window.innerWidth`. Dipakai untuk meniru jendela berscrollbar klasik, di
 * mana keduanya memang berbeda.
 */
function setCssWidth(width: number): void {
  window.matchMedia = ((query: string) => {
    const bound = /\(min-width:\s*(\d+(?:\.\d+)?)rem\)/.exec(query)
    const px = bound ? Number(bound[1]) * 16 : 0

    return {
      matches: width >= px,
      media: query,
      onchange: null,
      addEventListener: () => {},
      removeEventListener: () => {},
      addListener: () => {},
      removeListener: () => {},
      dispatchEvent: () => false,
    } as MediaQueryList
  }) as typeof window.matchMedia
}

function tierAt(width: number): string {
  setViewport(width)

  return renderHook(() => useLayoutTier()).result.current
}

afterEach(() => {
  window.matchMedia = REAL_MATCH_MEDIA
  setViewport(1024)
})

/**
 * Ambang tier dipakai shell, kerangka layar, dan kasir sekaligus. Yang dijaga
 * di sini justru lebar persis di perbatasan — di situlah dua kesalahan
 * sebelumnya terjadi: 768px (iPad potret) sempat jatuh ke sisi desktop dan
 * mendapat sidebar 16rem permanen, lalu 600px (Galaxy Tab A potret) jatuh ke
 * sisi ponsel dan kehilangan sidebarnya sama sekali.
 */
describe("useLayoutTier", () => {
  it("memisahkan mobile dari rel ikon tepat di 512", () => {
    expect(tierAt(511)).toBe("mobile")
    expect(tierAt(512)).toBe("rail")
  })

  it("memisahkan rel ikon dari sidebar penuh tepat di 768", () => {
    expect(tierAt(767)).toBe("rail")
    expect(tierAt(768)).toBe("tablet")
  })

  it("memisahkan tablet dari desktop tepat di 1024", () => {
    expect(tierAt(1023)).toBe("tablet")
    expect(tierAt(1024)).toBe("desktop")
  })

  /**
   * Ukuran perangkat yang benar-benar dipakai klinik.
   *
   * Galaxy Tab A potret melaporkan 600px — sebagian model 533px — dan dulu
   * itu jatuh ke tier mobile: sidebarnya jadi laci yang baru muncul setelah
   * tombolnya diketuk. Itu persis keluhan "sidebar tidak muncul".
   */
  it("memberi tablet kecil rel ikon, bukan perlakuan ponsel", () => {
    expect(tierAt(533)).toBe("rail") // Galaxy Tab A 8.0 potret
    expect(tierAt(600)).toBe("rail") // Galaxy Tab A 10.1 potret
    expect(tierAt(768)).toBe("tablet") // iPad potret
    expect(tierAt(820)).toBe("tablet") // iPad Air
    expect(tierAt(960)).toBe("tablet") // Galaxy Tab A lanskap
    expect(tierAt(1024)).toBe("desktop") // iPad lanskap
  })

  /** Ponsel tetap dapat laci: tidak ada yang selebar 512px saat potret. */
  it("tidak memberi rel ikon pada ponsel", () => {
    expect(tierAt(390)).toBe("mobile") // iPhone 14
    expect(tierAt(430)).toBe("mobile") // iPhone 15 Pro Max
  })

  it("tidak pernah melahirkan tier di luar keempatnya", () => {
    for (const width of [0, 1, 320, 511, 512, 767, 768, 1023, 1024, 2560]) {
      expect(["mobile", "rail", "tablet", "desktop"]).toContain(tierAt(width))
    }
  })

  /**
   * Sidebar yang berdiri di samping konten bersembunyi lewat varian `rail:`,
   * jadi tier WAJIB sepakat dengan media query. Saat tier dibaca dari
   * `window.innerWidth` — yang ikut menghitung scrollbar — ada pita ~15px di
   * mana tier bilang "sudah lewat ambang" (jadi lacinya tidak dipasang)
   * padahal CSS masih di bawahnya (jadi relnya pun tersembunyi). Di pita itu
   * tidak ada sidebar sama sekali.
   */
  it("mengikuti media query, bukan innerWidth yang ikut menghitung scrollbar", () => {
    setCssWidth(RAIL_MIN_WIDTH - 15)

    expect(tierAt(RAIL_MIN_WIDTH)).toBe("mobile")
  })

  it("ambangnya diekspor sebagai padanan piksel kueri remnya", () => {
    expect(RAIL_MIN_WIDTH).toBe(512)
    expect(TABLET_MIN_WIDTH).toBe(768)
    expect(DESKTOP_MIN_WIDTH).toBe(1024)
  })
})

describe("hasRail", () => {
  /** Hanya dua tier tengah yang sidebarnya berdiri di samping konten. */
  it("benar untuk tier yang sidebarnya bisa jadi rel ikon", () => {
    expect(hasRail("rail")).toBe(true)
    expect(hasRail("tablet")).toBe(true)
    expect(hasRail("mobile")).toBe(false)
    expect(hasRail("desktop")).toBe(false)
  })
})

describe("isNarrowTier", () => {
  it("mencakup ponsel dan tablet kecil", () => {
    expect(isNarrowTier("mobile")).toBe(true)
    expect(isNarrowTier("rail")).toBe(true)
    expect(isNarrowTier("tablet")).toBe(false)
    expect(isNarrowTier("desktop")).toBe(false)
  })
})

describe("useIsMobile", () => {
  it("hanya benar di tier paling sempit", () => {
    setViewport(511)
    expect(renderHook(() => useIsMobile()).result.current).toBe(true)

    setViewport(512)
    expect(renderHook(() => useIsMobile()).result.current).toBe(false)
  })
})

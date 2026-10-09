import { renderHook } from "@testing-library/react"
import { afterEach, describe, expect, it } from "vitest"

import {
  DESKTOP_MIN_WIDTH,
  TABLET_MIN_WIDTH,
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
 * di sini justru lebar persis di perbatasan — itu yang dulu salah: 768px
 * (iPad portrait) jatuh ke sisi desktop dan mendapat sidebar 16rem permanen.
 */
describe("useLayoutTier", () => {
  it("memisahkan mobile dari tablet tepat di 768", () => {
    expect(tierAt(767)).toBe("mobile")
    expect(tierAt(768)).toBe("tablet")
  })

  it("memisahkan tablet dari desktop tepat di 1024", () => {
    expect(tierAt(1023)).toBe("tablet")
    expect(tierAt(1024)).toBe("desktop")
  })

  /** Ukuran perangkat yang benar-benar dipakai klinik. */
  it("menempatkan tablet di tier tablet, bukan desktop", () => {
    expect(tierAt(768)).toBe("tablet") // iPad portrait
    expect(tierAt(800)).toBe("tablet") // Galaxy Tab A portrait
    expect(tierAt(820)).toBe("tablet") // iPad Air
    expect(tierAt(960)).toBe("tablet") // Galaxy Tab A landscape
    expect(tierAt(1024)).toBe("desktop") // iPad landscape
  })

  it("tidak pernah melahirkan tier di luar ketiganya", () => {
    for (const width of [0, 1, 320, 767, 768, 1023, 1024, 2560]) {
      expect(["mobile", "tablet", "desktop"]).toContain(tierAt(width))
    }
  })

  /**
   * Inti bug "sidebar tidak muncul": sidebar desktop bersembunyi lewat
   * `md:block`, jadi tier WAJIB sepakat dengan media query. Saat tier dibaca
   * dari `window.innerWidth` — yang ikut menghitung scrollbar — ada pita
   * ~15px di mana tier bilang "tablet" (jadi tanpa drawer) padahal CSS masih
   * di bawah `md` (jadi tanpa rel juga). Di pita itu tidak ada sidebar sama
   * sekali.
   */
  it("mengikuti media query, bukan innerWidth yang ikut menghitung scrollbar", () => {
    setCssWidth(TABLET_MIN_WIDTH - 15)

    expect(tierAt(TABLET_MIN_WIDTH)).toBe("mobile")
  })

  it("ambangnya diekspor sebagai padanan piksel kueri remnya", () => {
    expect(TABLET_MIN_WIDTH).toBe(768)
    expect(DESKTOP_MIN_WIDTH).toBe(1024)
  })
})

describe("useIsMobile", () => {
  it("hanya benar di tier paling sempit", () => {
    setViewport(767)
    expect(renderHook(() => useIsMobile()).result.current).toBe(true)

    setViewport(768)
    expect(renderHook(() => useIsMobile()).result.current).toBe(false)
  })
})

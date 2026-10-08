import { describe, expect, it } from "vitest"

import {
  DESKTOP_MIN_WIDTH,
  TABLET_MIN_WIDTH,
  tierForWidth,
} from "./use-mobile.ts"

/**
 * Ambang tier dipakai shell, kerangka layar, dan kasir sekaligus. Yang dijaga
 * di sini justru lebar persis di perbatasan — itu yang dulu salah: 768px
 * (iPad portrait) jatuh ke sisi desktop dan mendapat sidebar 16rem permanen.
 */
describe("tierForWidth", () => {
  it("memisahkan mobile dari tablet tepat di 768", () => {
    expect(tierForWidth(767)).toBe("mobile")
    expect(tierForWidth(768)).toBe("tablet")
  })

  it("memisahkan tablet dari desktop tepat di 1024", () => {
    expect(tierForWidth(1023)).toBe("tablet")
    expect(tierForWidth(1024)).toBe("desktop")
  })

  /** Ukuran perangkat yang benar-benar dipakai klinik. */
  it("menempatkan iPad di tier tablet, bukan desktop", () => {
    expect(tierForWidth(768)).toBe("tablet") // iPad portrait
    expect(tierForWidth(810)).toBe("tablet") // iPad 10.2"
    expect(tierForWidth(820)).toBe("tablet") // iPad Air
    expect(tierForWidth(1024)).toBe("desktop") // iPad landscape
  })

  it("tidak pernah melahirkan tier di luar ketiganya", () => {
    for (const width of [0, 1, 320, 767, 768, 1023, 1024, 2560]) {
      expect(["mobile", "tablet", "desktop"]).toContain(tierForWidth(width))
    }
  })

  it("ambangnya diekspor supaya CSS dan JS tidak berbeda pendapat", () => {
    expect(TABLET_MIN_WIDTH).toBe(768)
    expect(DESKTOP_MIN_WIDTH).toBe(1024)
  })
})

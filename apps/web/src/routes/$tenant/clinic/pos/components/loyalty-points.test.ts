import { describe, expect, it } from "vitest"

import { loyaltyPointsPreview } from "./loyalty-points.ts"

describe("loyaltyPointsPreview", () => {
  it("membulatkan ke bawah, bukan ke angka terdekat", () => {
    expect(loyaltyPointsPreview(19_000)).toBe(1)
    expect(loyaltyPointsPreview(10_000)).toBe(1)
    expect(loyaltyPointsPreview(9_999)).toBe(0)
    expect(loyaltyPointsPreview(105_000)).toBe(10)
  })

  it("tidak pernah negatif", () => {
    expect(loyaltyPointsPreview(-50_000)).toBe(0)
    expect(loyaltyPointsPreview(0)).toBe(0)
  })
})

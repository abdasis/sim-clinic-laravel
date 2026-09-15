import { describe, expect, it } from "vitest"

import type { LineItem } from "../hooks/use-pos-cart.ts"
import { memberDiscountAmount, type MembershipInfo } from "./member-discount.ts"

function line(overrides: Partial<LineItem> = {}): LineItem {
  return {
    key: "service:1",
    kind: "service",
    refId: 1,
    name: "Facial",
    unitPrice: 200_000,
    basePrice: null,
    promoName: null,
    qty: 1,
    stock: null,
    offeredBy: null,
    ...overrides,
  }
}

const gold: MembershipInfo = {
  id: 1,
  name: "Gold",
  discount_type: "percent",
  discount_value: 10,
  stacks_with_promo: false,
}

describe("memberDiscountAmount", () => {
  it("tidak memotong apa pun tanpa keanggotaan", () => {
    expect(memberDiscountAmount([line()], null)).toBe(0)
  })

  it("memotong sesuai persentase tingkatnya", () => {
    expect(memberDiscountAmount([line({ unitPrice: 200_000 })], gold)).toBe(20_000)
  })

  /** Meniru App\Support\MemberDiscount: baris promo dilewati bawaannya. */
  it("melewati baris yang sedang promo kecuali tingkatnya boleh menumpuk", () => {
    const onPromo = line({ unitPrice: 160_000, basePrice: 200_000 })

    expect(memberDiscountAmount([onPromo], gold)).toBe(0)
    expect(
      memberDiscountAmount([onPromo], { ...gold, stacks_with_promo: true }),
    ).toBe(16_000)
  })

  it("mendukung potongan nominal tetap", () => {
    const fixed: MembershipInfo = { ...gold, discount_type: "fixed", discount_value: 30_000 }

    expect(memberDiscountAmount([line({ unitPrice: 200_000 })], fixed)).toBe(30_000)
  })

  /** Potongan tetap yang melebihi keranjang berhenti di gratis, bukan minus. */
  it("tidak pernah melebihi total keranjang yang berhak", () => {
    const fixed: MembershipInfo = { ...gold, discount_type: "fixed", discount_value: 500_000 }

    expect(memberDiscountAmount([line({ unitPrice: 200_000 })], fixed)).toBe(200_000)
  })

  it("menjumlahkan beberapa baris yang berhak", () => {
    const items = [
      line({ key: "service:1", unitPrice: 200_000 }),
      line({ key: "product:2", kind: "product", unitPrice: 100_000, qty: 2 }),
    ]

    // (200.000 + 200.000) * 10% = 40.000
    expect(memberDiscountAmount(items, gold)).toBe(40_000)
  })
})

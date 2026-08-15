import { describe, expect, it } from "vitest"
import { act, renderHook } from "@testing-library/react"

import { usePosCart } from "./use-pos-cart.ts"
import type { CatalogEntry } from "./use-pos-catalog.ts"

const SERUM: CatalogEntry = {
  kind: "product",
  id: 7,
  name: "Serum",
  price: 100_000,
  unit: "botol",
  stock: 2,
  minThreshold: 1,
  stockStatus: "low",
}

const FACIAL: CatalogEntry = {
  kind: "service",
  id: 7,
  name: "Facial",
  price: 250_000,
  unit: null,
  stock: null,
  minThreshold: null,
  stockStatus: "n/a",
}

describe("usePosCart", () => {
  it("menambah entri yang sama menaikkan jumlah, bukan membuat baris kedua", () => {
    const { result } = renderHook(() => usePosCart())

    act(() => {
      result.current.add(SERUM)
      result.current.add(SERUM)
    })

    expect(result.current.items).toHaveLength(1)
    expect(result.current.items[0].qty).toBe(2)
    expect(result.current.total).toBe(200_000)
  })

  it("layanan dan produk ber-id sama tetap dua baris terpisah", () => {
    const { result } = renderHook(() => usePosCart())

    act(() => {
      result.current.add(SERUM)
      result.current.add(FACIAL)
    })

    expect(result.current.items).toHaveLength(2)
    expect(result.current.total).toBe(350_000)
  })

  it("menurunkan jumlah sampai nol mencabut barisnya", () => {
    const { result } = renderHook(() => usePosCart())

    act(() => result.current.add(SERUM))
    act(() => result.current.step(SERUM.kind + ":" + SERUM.id, -1))

    expect(result.current.items).toHaveLength(0)
    expect(result.current.isEmpty).toBe(true)
  })

  it("menandai stok kurang tanpa menghalangi penyusunan keranjang", () => {
    const { result } = renderHook(() => usePosCart())

    act(() => {
      result.current.add(SERUM)
      result.current.add(SERUM)
    })
    expect(result.current.hasStockIssue).toBe(false)

    act(() => result.current.add(SERUM))
    expect(result.current.items[0].qty).toBe(3)
    expect(result.current.hasStockIssue).toBe(true)
  })

  it("layanan tidak pernah dianggap kurang stok", () => {
    const { result } = renderHook(() => usePosCart())

    act(() => result.current.setQty("service:7", 99))
    act(() => result.current.add(FACIAL))
    act(() => result.current.setQty("service:7", 99))

    expect(result.current.hasStockIssue).toBe(false)
  })
})

import { describe, expect, it } from "vitest"
import { act, renderHook } from "@testing-library/react"

import { usePosCart } from "./use-pos-cart.ts"
import type { CatalogEntry } from "./use-pos-catalog.ts"

const SERUM: CatalogEntry = {
  kind: "product",
  id: 7,
  name: "Serum",
  price: 100_000,
  basePrice: null,
  promoName: null,
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
  basePrice: null,
  promoName: null,
  unit: null,
  stock: null,
  minThreshold: null,
  stockStatus: "n/a",
}

/** Layanan yang sedang promo: harga tagih turun, harga asli tetap dibawa. */
const FACIAL_PROMO: CatalogEntry = {
  kind: "service",
  id: 9,
  name: "Facial Glow",
  price: 160_000,
  basePrice: 200_000,
  promoName: "Promo Agustus",
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

  /**
   * Promo hilang begitu barangnya masuk keranjang: kartunya menunjukkan
   * potongan, tapi barisnya tidak membawa apa pun. Kasir membaca keranjang
   * saat menyebutkan tagihan, jadi potongan yang lenyap di sana tidak bisa
   * ditunjukkan ke pasien yang justru datang karenanya.
   */
  it("membawa promo dan harga asli ke dalam keranjang", () => {
    const { result } = renderHook(() => usePosCart())

    act(() => {
      result.current.add(FACIAL_PROMO)
    })

    const line = result.current.items[0]

    expect(line.unitPrice).toBe(160_000)
    expect(line.basePrice).toBe(200_000)
    expect(line.promoName).toBe("Promo Agustus")
  })

  it("menagih harga promo, bukan harga asli", () => {
    const { result } = renderHook(() => usePosCart())

    act(() => {
      result.current.add(FACIAL_PROMO)
      result.current.add(FACIAL_PROMO)
    })

    expect(result.current.total).toBe(320_000)
  })

  /** Baris tanpa promo tidak boleh mengaku punya harga asli yang berbeda. */
  it("tidak mengarang harga asli untuk baris tanpa promo", () => {
    const { result } = renderHook(() => usePosCart())

    act(() => {
      result.current.add(SERUM)
    })

    expect(result.current.items[0].basePrice).toBeNull()
    expect(result.current.items[0].promoName).toBeNull()
  })
})

import { describe, expect, it } from "vitest"

import { applyFilters, stockStatusOf } from "./use-pos-catalog.ts"
import type { CatalogEntry, CatalogFilters } from "./use-pos-catalog.ts"

function entry(over: Partial<CatalogEntry>): CatalogEntry {
  const base: CatalogEntry = {
    kind: "product",
    id: 1,
    name: "Serum",
    price: 100_000,
  basePrice: null,
  promoName: null,
    unit: "botol",
    stock: 10,
    minThreshold: 3,
    stockStatus: "available",
  }
  const merged = { ...base, ...over }

  return { ...merged, stockStatus: stockStatusOf(merged) }
}

const FILTERS: CatalogFilters = {
  search: "",
  type: "all",
  stock: "all",
  sort: "name",
}

describe("stockStatusOf", () => {
  it("layanan tidak punya status stok", () => {
    expect(stockStatusOf({ kind: "service", stock: null, minThreshold: null })).toBe("n/a")
  })

  it("saldo nol atau minus berarti habis", () => {
    expect(stockStatusOf({ kind: "product", stock: 0, minThreshold: 3 })).toBe("out")
    expect(stockStatusOf({ kind: "product", stock: -2, minThreshold: 3 })).toBe("out")
  })

  it("saldo tepat di ambang sudah terhitung rendah", () => {
    expect(stockStatusOf({ kind: "product", stock: 3, minThreshold: 3 })).toBe("low")
    expect(stockStatusOf({ kind: "product", stock: 4, minThreshold: 3 })).toBe("available")
  })

  it("tanpa ambang, apa pun di atas nol dianggap tersedia", () => {
    expect(stockStatusOf({ kind: "product", stock: 1, minThreshold: null })).toBe("available")
  })
})

describe("applyFilters", () => {
  const entries = [
    entry({ id: 1, name: "Serum Vitamin C", stock: 10 }),
    entry({ id: 2, name: "Masker Wajah", stock: 2, minThreshold: 5 }),
    entry({ id: 3, name: "Toner", stock: 0 }),
    entry({ kind: "service", id: 4, name: "Facial Basic", price: 250_000,
  basePrice: null,
  promoName: null, stock: null, minThreshold: null }),
  ]

  it("mencari nama tanpa peduli besar-kecil huruf", () => {
    const found = applyFilters(entries, { ...FILTERS, search: "sErUm" })

    expect(found.map((item) => item.id)).toEqual([1])
  })

  it("menyaring per tipe", () => {
    expect(applyFilters(entries, { ...FILTERS, type: "service" })).toHaveLength(1)
    expect(applyFilters(entries, { ...FILTERS, type: "product" })).toHaveLength(3)
  })

  it("menyembunyikan layanan saat menyaring status stok", () => {
    // Layanan tidak punya stok, jadi memaksanya masuk kategori mana pun
    // akan menyesatkan kasir.
    const available = applyFilters(entries, { ...FILTERS, stock: "available" })

    expect(available.map((item) => item.id)).toEqual([1])
    expect(applyFilters(entries, { ...FILTERS, stock: "low" }).map((i) => i.id)).toEqual([2])
    expect(applyFilters(entries, { ...FILTERS, stock: "out" }).map((i) => i.id)).toEqual([3])
  })

  it("mengurutkan berdasarkan nama dan harga", () => {
    expect(applyFilters(entries, FILTERS).map((item) => item.name)).toEqual([
      "Facial Basic",
      "Masker Wajah",
      "Serum Vitamin C",
      "Toner",
    ])
    expect(applyFilters(entries, { ...FILTERS, sort: "price_desc" })[0].id).toBe(4)
  })

  it("tidak mengubah larik masukan", () => {
    const original = [...entries]
    applyFilters(entries, { ...FILTERS, sort: "price_asc" })

    expect(entries).toEqual(original)
  })
})

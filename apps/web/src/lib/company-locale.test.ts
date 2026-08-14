import { describe, expect, it } from "vitest"

import {
  DEFAULT_LOCALE,
  normalizeLocale,
  pickTranslatable,
} from "./company-locale.ts"

describe("normalizeLocale", () => {
  it("menerima bahasa yang dikenal", () => {
    expect(normalizeLocale("id")).toBe("id")
    expect(normalizeLocale("en")).toBe("en")
  })

  it("menolak nilai asing dari URL", () => {
    // ?lang= diisi pengunjung, jadi tidak boleh dipercaya apa adanya.
    expect(normalizeLocale("fr")).toBe(DEFAULT_LOCALE)
    expect(normalizeLocale("")).toBe(DEFAULT_LOCALE)
    expect(normalizeLocale(undefined)).toBe(DEFAULT_LOCALE)
    expect(normalizeLocale(null)).toBe(DEFAULT_LOCALE)
    expect(normalizeLocale(42)).toBe(DEFAULT_LOCALE)
  })
})

describe("pickTranslatable", () => {
  it("mengambil bahasa yang diminta", () => {
    const value = { id: "Halo", en: "Hello" }

    expect(pickTranslatable(value, "id")).toBe("Halo")
    expect(pickTranslatable(value, "en")).toBe("Hello")
  })

  it("jatuh ke bahasa Indonesia saat versi lain belum ditulis", () => {
    expect(pickTranslatable({ id: "Hanya Indonesia" }, "en")).toBe(
      "Hanya Indonesia",
    )
  })

  it("mengembalikan undefined saat tidak ada versi mana pun", () => {
    expect(pickTranslatable({}, "en")).toBeUndefined()
    expect(pickTranslatable(null, "id")).toBeUndefined()
    expect(pickTranslatable(undefined, "id")).toBeUndefined()
  })

  it("meneruskan nilai yang sudah berupa teks biasa", () => {
    expect(pickTranslatable("sudah teks", "en")).toBe("sudah teks")
  })

  it("bekerja untuk dokumen rich text, bukan hanya string", () => {
    const doc = { type: "doc", content: [] }

    expect(pickTranslatable({ id: doc }, "en")).toBe(doc)
  })
})

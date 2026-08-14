import { act, renderHook } from "@testing-library/react"
import { describe, expect, it } from "vitest"

import { useTranslatableField } from "./use-translatable-field.ts"

describe("useTranslatableField", () => {
  it("membaca nilai sesuai tab bahasa yang dibuka", () => {
    const { result } = renderHook(() => useTranslatableField())
    const value = { id: "Halo", en: "Hello" }

    expect(result.current.read(value)).toBe("Halo")

    act(() => result.current.setLocale("en"))

    expect(result.current.read(value)).toBe("Hello")
  })

  it("menulis hanya bahasa yang sedang dibuka", () => {
    const { result } = renderHook(() => useTranslatableField())

    act(() => result.current.setLocale("en"))

    expect(result.current.write({ id: "Halo" }, "Hello")).toEqual({
      id: "Halo",
      en: "Hello",
    })
  })

  it("mengosongkan field menghapus bahasa itu, bukan menyimpan string kosong", () => {
    const { result } = renderHook(() => useTranslatableField())

    // Peta dengan nilai kosong akan lolos fallback dan menampilkan teks
    // kosong di landing, bukan versi Indonesia-nya.
    expect(result.current.write({ id: "Halo", en: "Hello" }, "")).toEqual({
      en: "Hello",
    })
    expect(result.current.write({ id: "Halo" }, null)).toEqual({})
  })

  it("menandai bahasa mana saja yang sudah terisi", () => {
    const { result } = renderHook(() => useTranslatableField())

    expect(result.current.filledLocales({ id: "Halo", en: "Hello" })).toEqual([
      "id",
      "en",
    ])
    expect(result.current.filledLocales({ id: "Halo", en: "" })).toEqual(["id"])
    expect(result.current.filledLocales(null)).toEqual([])
  })
})

import { describe, expect, it } from "vitest"

import {
  countByType,
  pairPhotos,
  photoRejection,
  photoSrc,
  type PhotoRow,
} from "./photo-types.ts"

function photo(id: number, type: string): PhotoRow {
  return { id, type, url: `https://klinik.test/${id}.jpg` }
}

describe("pairPhotos", () => {
  it("memasangkan foto sebelum dan sesudah menurut urutannya", () => {
    const pairs = pairPhotos([
      photo(1, "before"),
      photo(2, "after"),
      photo(3, "before"),
      photo(4, "after"),
    ])

    expect(pairs).toHaveLength(2)
    expect(pairs[0].before?.id).toBe(1)
    expect(pairs[0].after?.id).toBe(2)
    expect(pairs[1].before?.id).toBe(3)
    expect(pairs[1].after?.id).toBe(4)
  })

  /**
   * Urutan datangnya foto tidak menentukan pasangannya: staf kerap
   * mengunggah semua foto sesudah lebih dulu karena itu yang terakhir
   * dipotret, dan pasangannya harus tetap terbaca benar.
   */
  it("tidak terpengaruh urutan sebelum/sesudah pada daftar masukan", () => {
    const pairs = pairPhotos([
      photo(9, "after"),
      photo(10, "after"),
      photo(11, "before"),
    ])

    expect(pairs).toHaveLength(2)
    expect(pairs[0].before?.id).toBe(11)
    expect(pairs[0].after?.id).toBe(9)
    expect(pairs[1].before).toBeUndefined()
    expect(pairs[1].after?.id).toBe(10)
  })

  it("tetap menampilkan sisi yang sendirian sebagai pasangan setengah", () => {
    const pairs = pairPhotos([photo(1, "before"), photo(2, "before")])

    expect(pairs).toHaveLength(2)
    expect(pairs.every((pair) => pair.after === undefined)).toBe(true)
  })

  it("kosong saat tidak ada foto sama sekali", () => {
    expect(pairPhotos()).toEqual([])
    expect(pairPhotos([])).toEqual([])
  })
})

describe("countByType", () => {
  it("menghitung tiap jenis secara terpisah", () => {
    expect(
      countByType([photo(1, "before"), photo(2, "before"), photo(3, "after")]),
    ).toEqual({ before: 2, after: 1 })
  })
})

describe("photoSrc", () => {
  it("memakai url bila ada, dan jatuh ke path bila tidak", () => {
    expect(photoSrc({ id: 1, type: "before", url: "/a.jpg" })).toBe("/a.jpg")
    expect(photoSrc({ id: 1, type: "before", path: "b.jpg" })).toBe("b.jpg")
    expect(photoSrc({ id: 1, type: "before" })).toBe("")
  })
})

describe("photoRejection", () => {
  function file(type: string, size: number): File {
    const blob = new File(["x"], "foto.jpg", { type })
    Object.defineProperty(blob, "size", { value: size })

    return blob
  }

  it("meloloskan jpg dan png di bawah 2 MB", () => {
    expect(photoRejection(file("image/jpeg", 1_000_000))).toBeNull()
    expect(photoRejection(file("image/png", 2 * 1024 * 1024))).toBeNull()
  })

  it("menolak jenis berkas selain jpg/png", () => {
    expect(photoRejection(file("application/pdf", 1000))).toBe("type")
  })

  it("menolak berkas yang melewati 2 MB", () => {
    expect(photoRejection(file("image/jpeg", 2 * 1024 * 1024 + 1))).toBe("size")
  })
})

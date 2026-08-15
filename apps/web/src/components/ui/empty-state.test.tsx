import { describe, expect, it } from "vitest"
import { act, fireEvent, render } from "@testing-library/react"

import { EmptyState } from "./empty-state.tsx"

/**
 * Yang diuji di sini bukan gambarnya, melainkan rantai variannya: root `Empty`
 * menyalakan "hover", dan motion harus menurunkannya lewat `EmptyMedia` dan
 * `<svg>` yang keduanya elemen biasa. Kalau rantai itu putus, ilustrasinya
 * diam dan tidak ada yang memberi tahu.
 */
describe("EmptyState", () => {
  it("merender judul, keterangan, dan ilustrasi", () => {
    const { container, getByText } = render(
      <EmptyState
        illustration="patients"
        title="Belum ada pasien"
        description="Tambahkan pasien pertama"
      />,
    )

    expect(getByText("Belum ada pasien")).toBeTruthy()
    expect(getByText("Tambahkan pasien pertama")).toBeTruthy()
    expect(container.querySelector("svg")).toBeTruthy()
  })

  it("menurunkan varian hover sampai ke bagian gambar yang bergerak", async () => {
    const { container } = render(
      <EmptyState illustration="patients" title="Belum ada pasien" />,
    )

    const root = container.querySelector('[data-slot="empty"]') as HTMLElement
    const moving = container.querySelector("svg > g") as SVGGElement

    expect(root).toBeTruthy()
    expect(moving).toBeTruthy()
    expect(moving.style.transform ?? "").not.toContain("translateY")

    await act(async () => {
      fireEvent.pointerEnter(root)
      fireEvent.mouseEnter(root)
      await new Promise((resolve) => setTimeout(resolve, 120))
    })

    // Varian "float" menggeser gambar ke atas; adanya transform membuktikan
    // varian dari root benar-benar sampai ke elemen ini.
    expect(moving.style.transform).toContain("translateY")
  })
})

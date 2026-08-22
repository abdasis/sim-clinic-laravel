import { describe, expect, it, vi } from "vitest"
import { render } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { BeforeAfterGallery } from "./before-after-gallery.tsx"
import type { PhotoRow } from "./photo-types.ts"

/**
 * Yang diuji di sini bukan tampilannya, melainkan pemasangannya: kolom kiri
 * dan kanan harus berisi foto yang benar, dan sisi yang belum ada foto tetap
 * menyediakan tempat untuk mengunggahnya. Kalau pemasangannya meleset, rekam
 * medis menampilkan "sesudah" milik tindakan lain tanpa gejala apa pun.
 */
function renderGallery(ui: React.ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

const photos: PhotoRow[] = [
  { id: 1, type: "before", url: "/sebelum-1.jpg" },
  { id: 2, type: "after", url: "/sesudah-1.jpg" },
  { id: 3, type: "before", url: "/sebelum-2.jpg" },
]

describe("BeforeAfterGallery", () => {
  it("menaruh foto sebelum dan sesudah pada baris yang sama", () => {
    const { container } = renderGallery(<BeforeAfterGallery photos={photos} />)

    const sources = Array.from(container.querySelectorAll("img")).map(
      (img) => img.getAttribute("src"),
    )

    expect(sources).toEqual(["/sebelum-1.jpg", "/sesudah-1.jpg", "/sebelum-2.jpg"])
  })

  it("menyediakan tempat unggah pada sisi yang fotonya belum ada", () => {
    const onPick = vi.fn()
    const { container } = renderGallery(
      <BeforeAfterGallery photos={photos} onPick={onPick} />,
    )

    // Dua pasangan, tapi hanya tiga foto: satu petak "sesudah" kosong.
    const inputs = container.querySelectorAll('input[type="file"]')
    expect(inputs).toHaveLength(1)

    const addSlots = Array.from(container.querySelectorAll("button")).filter(
      (button) => button.textContent?.includes("photo_add_after"),
    )
    expect(addSlots.length).toBeGreaterThan(0)
  })

  it("tanpa onPick, petak kosongnya tidak bisa diklik", () => {
    const { container } = renderGallery(<BeforeAfterGallery photos={photos} />)

    expect(container.querySelector('input[type="file"]')).toBeNull()
  })

  it("meneruskan foto yang dipilih ke onDelete", () => {
    const onDelete = vi.fn()
    const { container } = renderGallery(
      <BeforeAfterGallery photos={photos} onDelete={onDelete} />,
    )

    const buttons = Array.from(container.querySelectorAll("button")).filter(
      (button) => button.getAttribute("aria-label") === "medical_record.photo_delete",
    )

    expect(buttons).toHaveLength(3)
    buttons[1].click()
    expect(onDelete).toHaveBeenCalledWith(photos[1])
  })

  it("menampilkan keadaan kosong saat belum ada foto sama sekali", () => {
    const { container, getByText } = renderGallery(
      <BeforeAfterGallery photos={[]} />,
    )

    expect(getByText("medical_record.no_photos")).toBeTruthy()
    expect(container.querySelectorAll("img")).toHaveLength(0)
  })
})

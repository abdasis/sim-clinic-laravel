import { readFileSync } from "node:fs"
import { afterEach, describe, expect, it } from "vitest"
import { cleanup, render } from "@testing-library/react"

import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "./dialog.tsx"

const AKAR = process.cwd()

afterEach(cleanup)

function tampil(layout?: "default" | "form") {
  return render(
    <Dialog open>
      <DialogContent layout={layout}>
        <DialogHeader>
          <DialogTitle>Tambah promo</DialogTitle>
        </DialogHeader>
        <DialogBody>
          <input aria-label="nama" />
        </DialogBody>
        <DialogFooter>
          <button type="submit">Simpan</button>
        </DialogFooter>
      </DialogContent>
    </Dialog>,
  )
}

const konten = () =>
  document.querySelector('[data-slot="dialog-content"]') as HTMLElement
const isi = () => document.querySelector('[data-slot="dialog-body"]') as HTMLElement

/**
 * Formulir tujuh field di dialog tanpa batas tinggi.
 *
 * Bentuk bawaan `DialogContent` adalah `h-fit` tanpa batas atas: dialognya
 * tumbuh setinggi isinya. Di layar 390x844 formulir booking (tujuh field)
 * tumbuh melewati layar, dan karena dialognya `fixed` dan dipusatkan, tombol
 * simpannya bukan tergulir ke bawah — ia keluar dari layar dan tidak bisa
 * dicapai sama sekali. Formulirnya bisa diisi, tapi tidak bisa dikirim.
 */
describe("dialog berisi formulir", () => {
  it("membatasi tingginya setinggi layar", () => {
    tampil("form")

    expect(konten().className).toContain("max-h-[calc(100dvh-2rem)]")
  })

  /**
   * `dvh`, bukan `vh`. Papan ketik ponsel mengecilkan viewport dinamis tapi
   * tidak mengubah `vh`, jadi dengan `vh` field yang sedang diisi justru
   * tertutup papan ketiknya sendiri.
   */
  it("tidak pernah memakai satuan vh", () => {
    tampil("form")

    expect(konten().className).not.toMatch(/\bh-\[\d+vh\]|max-h-\[[^]]*[^d]vh/)
  })

  it("memenuhi layar di bawah 768px", () => {
    tampil("form")

    const kelas = konten().className

    expect(kelas).toContain("max-md:h-dvh")
    expect(kelas).toContain("max-md:max-w-none")
    expect(kelas).toContain("max-md:rounded-none")
  })

  /**
   * Yang menggulir isi formulirnya, bukan dialognya. Menggulir seluruh dialog
   * ikut menggulir judul dan tombol simpannya keluar pandangan — yang justru
   * dua hal yang harus selalu terlihat.
   */
  it("memindahkan gulirnya ke isi formulir, bukan ke dialognya", () => {
    tampil("form")

    expect(konten().className).toContain("overflow-hidden")
    expect(isi().className).toContain("overflow-y-auto")
    expect(isi().className).toContain("flex-1")
    // Tanpa min-h-0, baris lentur tetap melebar mengikuti isinya dan
    // `overflow-y-auto` tidak pernah menggulir apa pun.
    expect(isi().className).toContain("min-h-0")
  })

  /** Kolom lentur, bukan grid: jumlah anak tiap dialog berbeda. */
  it("menyusun dialognya sebagai kolom lentur", () => {
    tampil("form")

    expect(konten().className).toContain("flex")
    expect(konten().className).toContain("flex-col")
    expect(konten().className).not.toMatch(/\bgrid\b/)
  })

  it("menandai bentuknya supaya bisa diperiksa dari luar", () => {
    tampil("form")

    expect(konten().getAttribute("data-layout")).toBe("form")
  })
})

/**
 * Dialog konfirmasi tidak boleh ikut berubah: memenuhi layar hanya untuk
 * bertanya "hapus?" terasa jauh lebih berat daripada pertanyaannya.
 */
describe("dialog biasa", () => {
  it("tetap setinggi isinya dan tidak memenuhi layar", () => {
    tampil()

    const kelas = konten().className

    expect(kelas).toContain("h-fit")
    expect(kelas).toContain("grid")
    expect(kelas).not.toContain("max-md:h-dvh")
    expect(kelas).not.toContain("overflow-hidden")
  })

  it("adalah bentuk bawaannya", () => {
    tampil()

    expect(konten().getAttribute("data-layout")).toBe("default")
  })
})

/**
 * Yang dijaga di sini bukan satu komponen melainkan sebuah aturan: tidak ada
 * dialog berisi formulir yang boleh kembali memotong isinya tanpa area gulir.
 * Pemeriksaan className saja tidak menangkapnya — yang perlu diperiksa daftar
 * pemakainya.
 */
describe("semua dialog berisi formulir memakai bentuk form", () => {
  const BERKAS = [
    "promos/components/promo-form-dialog",
    "bookings/components/booking-form-dialog",
    "expenses/components/commission-rule-dialog",
    "expenses/components/expense-form-dialog",
    "staff/components/staff-form-modal",
    "categories/components/category-form-dialog",
    "units/components/unit-form-dialog",
    "bookings/components/reminder-settings-dialog",
    "broadcasts/components/broadcast-settings-dialog",
    "broadcasts/components/auto-reminder-dialog",
  ]

  it.each(BERKAS)("%s", (relatif) => {
    const sumber = readFileSync(
      `${AKAR}/src/routes/$tenant/clinic/${relatif}.tsx`,
      "utf8",
    )

    expect(sumber).toContain('layout="form"')
    expect(sumber).toContain("<DialogBody")
    // Batas tinggi yang dirakit tangan di tiap dialog adalah bentuk lama:
    // angkanya berbeda-beda, dan dua batas yang bertumpuk (90dvh di luar,
    // 62dvh di dalam) membuat ruang isinya lebih sempit dari yang terbaca.
    expect(sumber).not.toMatch(/max-h-\[\d+dvh\]/)
  })
})

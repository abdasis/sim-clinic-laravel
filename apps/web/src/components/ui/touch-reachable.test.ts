import { readFileSync } from "node:fs"
import { createRequire } from "node:module"
import { describe, expect, it } from "vitest"
import { compile } from "tailwindcss"

/**
 * Kontrol yang di desktop sengaja menepi sampai kursor mendekat.
 *
 * Tablet dan ponsel tidak punya kursor. Kontrol yang hanya muncul saat hover
 * di sana bukan cuma samar — ia tidak bisa dicapai sama sekali, dan tidak ada
 * pesan galat apa pun yang memberi tahu. Yang paling mahal ditemukan di audit
 * ini: tombol hapus foto rekam medis, yang di tablet klinik tidak pernah
 * tampil sehingga foto yang salah unggah tidak bisa dibuang.
 *
 * jsdom tidak menghitung media query, dan `opacity` tidak terbaca dari
 * className. Jadi yang dijaga di sini dua hal yang bisa dibuktikan tanpa
 * mesin render: bahwa kelasnya ada pada elemen yang benar, dan bahwa varian
 * `touch:` memang berkembang jadi `@media (hover: none)` — sebab kelas
 * `touch:*` yang variannya belum terdaftar lolos tanpa suara dan tidak
 * menghasilkan CSS apa pun.
 */
// cwd, bukan import.meta.url: berkas ini mengimpor tailwindcss, dan modul
// yang menarik paket Node dimuat vitest sebagai modul web — di sana
// import.meta.url bukan URL berskema file dan fileURLToPath menolaknya.
const akar = process.cwd()

const baca = (relatif: string) => readFileSync(akar + relatif, "utf8")

/**
 * Satu untai kelas yang memuat penanda ini.
 *
 * Penandanya boleh berada di tengah untaian — dicari batas kutipnya ke
 * belakang, bukan dipotong dari posisi penanda, supaya kelas sebelum penanda
 * ikut terbaca.
 */
function untaianKelas(sumber: string, penanda: string): string {
  const posisi = sumber.indexOf(penanda)

  expect(posisi, `penanda "${penanda}" tidak ditemukan`).toBeGreaterThan(-1)

  const buka = sumber.lastIndexOf('"', posisi)
  const tutup = sumber.indexOf('"', posisi + penanda.length)

  expect(buka, `kutip pembuka sebelum "${penanda}" tidak ditemukan`).toBeGreaterThan(-1)
  expect(tutup, `kutip penutup sesudah "${penanda}" tidak ditemukan`).toBeGreaterThan(-1)

  return sumber.slice(buka + 1, tutup)
}

describe("varian touch terdaftar di styles.css", () => {
  /**
   * Bukti bahwa `touch:` bukan kelas mati. Tailwind membuang varian yang
   * tidak dikenal tanpa peringatan, jadi salah ketik di sini berarti seluruh
   * perbaikan di bawah tidak pernah sampai ke browser.
   */
  it("berkembang jadi @media (hover: none)", async () => {
    const require_ = createRequire(import.meta.url)
    const styles = baca("/src/styles.css")

    // Hanya baris varian yang diambil: styles.css penuh memuat font Google
    // dan berkas @import lain yang tidak perlu diunduh demi satu pemeriksaan.
    const barisVarian = styles
      .split("\n")
      .filter((baris) => baris.startsWith("@custom-variant"))
      .join("\n")

    expect(barisVarian).toContain("@custom-variant touch")

    const hasil = await compile(`@import "tailwindcss";\n${barisVarian}\n`, {
      base: akar,
      loadStylesheet: async (id: string, base: string) => {
        const path = id.startsWith("tailwindcss")
          ? require_.resolve("tailwindcss/index.css")
          : id

        return { base, path, content: readFileSync(path, "utf8") }
      },
    })

    const css = hasil.build(["touch:opacity-100", "touch:w-11"])

    expect(css).toMatch(/hover:\s*none/)
    expect(css).toContain("opacity: 100%")
  })
})

describe("tombol hapus foto rekam medis", () => {
  it.each(["/src/components/medical-photos/photo-slot.tsx", "/src/components/medical-photos/photo-uploader.tsx"])(
    "%s tampil di layar tanpa hover",
    (berkas) => {
      const kelas = untaianKelas(baca(berkas), "absolute top-1.5 right-1.5")

      // Tetap menepi di desktop — itu yang menjaga fotonya tidak tertutup.
      expect(kelas).toContain("group-hover:opacity-100")
      // Tapi di layar sentuh ia tidak boleh bergantung pada hover.
      expect(kelas).toContain("touch:opacity-100")
      // 24px terlalu kecil untuk jari; bidangnya dilebarkan jadi 44px.
      expect(kelas).toContain("touch:after:-inset-2.5")
    },
  )
})

describe("kontrol sidebar di layar sentuh", () => {
  const sidebar = baca("/src/components/ui/sidebar.tsx")

  /**
   * `md:` berlaku sejak 768px, jadi tablet ikut kehilangan aksi baris
   * padahal tidak punya hover untuk memunculkannya kembali. Yang boleh
   * kehilangannya hanya penunjuk presisi.
   */
  it("tidak menyembunyikan aksi baris dari perangkat sentuh", () => {
    expect(sidebar).not.toMatch(/(?<!pointer-fine:)md:opacity-0/)
    expect(sidebar).toContain("pointer-fine:md:opacity-0")
  })

  it("menaikkan tombol buka/tutup ke 44px di layar sentuh", () => {
    expect(sidebar).toContain("touch:size-11")
  })

  describe("rel", () => {
    // Penandanya dari dalam untaian kelas relnya sendiri; `data-sidebar="rail"`
    // tidak bisa dipakai karena kutipnya sendiri yang jadi batas pencarian.
    const kelas = untaianKelas(sidebar, "inset-y-0 z-20 hidden w-6")

    it("melebarkan bidang ketuknya di layar sentuh", () => {
      expect(kelas).toContain("touch:w-11")
    })

    /**
     * Tailwind v4 mengompilasi `-translate-x-1/2` jadi properti `translate`
     * yang berdiri sendiri — baru ada sejak Chrome 104. Di tablet klinik yang
     * lebih tua deklarasinya dibuang dan relnya tertinggal dari tempatnya,
     * persis seperti dialog yang sudah diperbaiki sebelumnya.
     */
    it("tidak bergantung pada translate untuk penempatannya", () => {
      expect(kelas).not.toContain("translate-x-1/2")
    })

    /** Relnya muncul di tier yang sama dengan sidebar permanennya. */
    it("muncul bersama sidebar permanen, bukan di ambang lain", () => {
      expect(kelas).toContain("rail:flex")
      expect(kelas).not.toContain("sm:flex")
    })
  })
})

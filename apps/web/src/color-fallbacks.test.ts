import { readFileSync } from "node:fs"
import { fileURLToPath } from "node:url"
import { describe, expect, it } from "vitest"

/**
 * Warna cadangan untuk browser tanpa `oklch()`.
 *
 * Tailwind v4 menulis seluruh warnanya dengan `oklch()`, dan browser yang
 * belum mengenalnya membuang deklarasi itu sebagai tidak sah — bukan
 * menggantinya dengan apa pun. Tata letaknya tetap benar sementara warnanya
 * hilang seluruhnya: dialog tembus pandang menumpuk di atas tabel, sidebar
 * tanpa latar. Itu yang terjadi di tablet klinik.
 *
 * Yang dijaga di sini bukan nilai warnanya, melainkan kelengkapannya: tiap
 * token yang lahir sebagai oklch harus punya padanan di blok cadangan. Tanpa
 * ini, satu warna baru yang ditambahkan tanpa membangkitkan ulang berkasnya
 * akan hilang diam-diam — dan hanya terlihat di perangkat yang tidak dipakai
 * siapa pun untuk mengembangkan.
 */
const baca = (nama: string) =>
  readFileSync(fileURLToPath(new URL(nama, import.meta.url)), "utf8")

/**
 * Buang blok `@supports` yang sudah menguji oklch.
 *
 * Isinya memang hanya untuk browser yang mendukungnya — di situ token seperti
 * `--link` cuma diperhalus, sementara nilai dasarnya sudah ada di luar blok.
 * Menghitungnya sebagai kekurangan akan menuntut cadangan untuk sesuatu yang
 * tidak pernah berlaku.
 */
function tanpaBlokOklch(css: string): string {
  let hasil = ""
  let i = 0

  while (i < css.length) {
    const mulai = css.indexOf("@supports", i)

    if (mulai === -1) {
      hasil += css.slice(i)
      break
    }

    const buka = css.indexOf("{", mulai)
    const kondisi = css.slice(mulai, buka)

    if (buka === -1 || !kondisi.includes("oklch")) {
      hasil += css.slice(i, mulai + "@supports".length)
      i = mulai + "@supports".length
      continue
    }

    hasil += css.slice(i, mulai)

    let dalam = 1
    let j = buka + 1
    while (j < css.length && dalam > 0) {
      if (css[j] === "{") dalam++
      else if (css[j] === "}") dalam--
      j++
    }
    i = j
  }

  return hasil
}

/** Nama token yang nilainya memakai oklch() dan benar-benar butuh cadangan. */
function tokenOklch(css: string): Set<string> {
  const bersih = tanpaBlokOklch(css.replace(/\/\*[\s\S]*?\*\//g, ""))
  const nama = new Set<string>()

  for (const m of bersih.matchAll(/(--[\w-]+)\s*:\s*[^;}]*oklch\([^;}]*/g)) {
    nama.add(m[1])
  }

  return nama
}

function tokenCadangan(css: string): Set<string> {
  const nama = new Set<string>()

  for (const m of css.matchAll(/(--[\w-]+)\s*:/g)) {
    nama.add(m[1])
  }

  return nama
}

describe("color-fallbacks.css", () => {
  const sumber = baca("./styles.css") + baca("./accent-themes.css")
  const cadangan = baca("./color-fallbacks.css")

  it("menutup tiap token warna yang ditulis dengan oklch", () => {
    const perlu = tokenOklch(sumber)
    const punya = tokenCadangan(cadangan)
    const bolong = [...perlu].filter((nama) => !punya.has(nama)).sort()

    expect(perlu.size).toBeGreaterThan(20)
    expect(
      bolong,
      `Jalankan: python3 scripts/generate-color-fallbacks.py`,
    ).toEqual([])
  })

  it("hanya berlaku di browser tanpa oklch", () => {
    expect(cadangan).toContain("@supports not (color: oklch(0 0 0))")
  })

  /**
   * Isi bloknya harus bebas oklch — kalau tidak, cadangan itu sendiri ikut
   * gugur di browser yang jadi alasan keberadaannya. Kondisi `@supports`-nya
   * sendiri tidak dihitung: ia memang harus menyebut oklch.
   */
  it("tidak menyisakan satu pun oklch di dalam isinya", () => {
    const isi = cadangan.slice(cadangan.indexOf("{", cadangan.indexOf("@supports")))

    expect(isi).not.toContain("oklch(")
  })

  /** Latar dialog wajib pekat; itu inti keluhan "dialognya tembus pandang". */
  it("memberi permukaan dialog latar yang pekat", () => {
    expect(cadangan).toMatch(/--popover:\s*#[0-9a-f]{6}/i)
    expect(cadangan).toMatch(/--background:\s*#[0-9a-f]{6}/i)
    expect(cadangan).toMatch(/--sidebar:\s*#[0-9a-f]{6}/i)
  })
})

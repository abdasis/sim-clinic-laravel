import { readFileSync } from "node:fs"
import { fileURLToPath } from "node:url"
import { describe, expect, it } from "vitest"

/**
 * Dialog harus dipusatkan oleh kotaknya, bukan oleh `translate`.
 *
 * Tailwind v4 mengompilasi `-translate-x-1/2` jadi properti `translate` yang
 * berdiri sendiri — baru ada sejak Chrome 104. Di browser yang lebih tua
 * deklarasinya dibuang, dan sudut kiri-atas dialog tertinggal tepat di titik
 * tengah layar lalu menjulur keluar ke kanan bawah. Terukur di tablet klinik:
 * pada viewport 800x1280, kiri=400 kanan=-112 atas=640 bawah=-252.
 *
 * jsdom tidak menghitung tata letak, jadi yang dijaga di sini caranya, bukan
 * hasilnya: selama pemusatannya tidak bergantung pada transform, ia bekerja di
 * mesin mana pun.
 */
const baca = (nama: string) =>
  readFileSync(fileURLToPath(new URL(nama, import.meta.url)), "utf8")

/** Kelas pada elemen yang dipusatkan, bukan pada pembungkus atau anaknya. */
function kelasKonten(sumber: string, slot: string): string {
  const mulai = sumber.indexOf(`data-slot="${slot}"`)

  expect(mulai, `slot ${slot} tidak ditemukan`).toBeGreaterThan(-1)

  const potong = sumber.slice(mulai, mulai + 1600)
  const kutipan = potong.match(/"[^"]*fixed[^"]*"/)

  return kutipan?.[0] ?? ""
}

/**
 * Seluruh untai kelas di sekitar slot, termasuk cabang bersyaratnya.
 *
 * `kelasKonten` hanya mengambil untai pertama yang memuat `fixed`. Sejak
 * dialog punya dua bentuk — biasa dan `form` — tingginya ditentukan di cabang
 * `cn()` yang terpisah, dan untai pertama saja tidak lagi memuatnya.
 */
function semuaKelas(sumber: string, slot: string): string {
  const mulai = sumber.indexOf(`data-slot="${slot}"`)

  expect(mulai, `slot ${slot} tidak ditemukan`).toBeGreaterThan(-1)

  return sumber.slice(mulai, mulai + 2400)
}

describe.each([
  ["dialog", "./dialog.tsx", "dialog-content"],
  ["alert dialog", "./alert-dialog.tsx", "alert-dialog-content"],
])("pemusatan %s", (_nama, berkas, slot) => {
  const kelas = kelasKonten(baca(berkas), slot)

  it("tidak bergantung pada translate", () => {
    expect(kelas).not.toContain("translate-x-1/2")
    expect(kelas).not.toContain("translate-y-1/2")
  })

  /** inset di keempat sisi + margin auto = terpusat tanpa transform apa pun. */
  it("dipusatkan lewat inset dan margin auto", () => {
    expect(kelas).toContain("inset-0")
    expect(kelas).toContain("m-auto")
  })

  /**
   * `margin: auto` hanya memusatkan tegak bila tingginya tertentu. Tanpa itu,
   * elemen dengan inset atas-bawah 0 justru meregang penuh layar.
   *
   * Tingginya boleh datang dari mana saja: `h-fit` pada bentuk biasa, atau
   * batas tinggi pada bentuk `form` yang memindahkan gulirnya ke dalam. Yang
   * tidak boleh cuma satu: tidak ada penentu tinggi sama sekali.
   */
  it("memberi tinggi tertentu supaya pemusatan tegaknya berlaku", () => {
    const sekitar = semuaKelas(baca(berkas), slot)

    expect(sekitar).toMatch(/\bh-fit\b|\bh-dvh\b|\bmax-h-\[/)
  })
})

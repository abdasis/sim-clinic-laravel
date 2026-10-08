import { describe, expect, it } from "vitest"

import { readSidebarCookie } from "./sidebar.tsx"

/**
 * Cookie `sidebar_state` sudah lama ditulis tiap kali sidebar dibuka-tutup,
 * tapi tidak pernah ada yang membacanya — sidebar yang diciutkan terbuka lagi
 * di tiap muat ulang. Yang dijaga di sini pembacaannya, termasuk bentuk yang
 * tidak dikenal: cookie rusak tidak boleh menyembunyikan navigasi.
 */
describe("readSidebarCookie", () => {
  it("membaca keadaan yang tersimpan", () => {
    expect(readSidebarCookie("sidebar_state=true")).toBe(true)
    expect(readSidebarCookie("sidebar_state=false")).toBe(false)
  })

  it("menemukannya di antara cookie lain", () => {
    expect(readSidebarCookie("clinic_token=abc; sidebar_state=false; x=1")).toBe(
      false,
    )
  })

  /** Belum pernah disetel: biarkan tier yang memutuskan, bukan cookie. */
  it("menjawab null saat cookienya tidak ada", () => {
    expect(readSidebarCookie("")).toBeNull()
    expect(readSidebarCookie("clinic_token=abc")).toBeNull()
  })

  it("menolak nilai yang tidak dikenal, bukan menganggapnya tertutup", () => {
    expect(readSidebarCookie("sidebar_state=")).toBeNull()
    expect(readSidebarCookie("sidebar_state=1")).toBeNull()
    expect(readSidebarCookie("sidebar_state=yes")).toBeNull()
  })

  /** Nama lain yang kebetulan berakhiran sama tidak boleh ikut terbaca. */
  it("tidak tertukar dengan cookie bernama mirip", () => {
    expect(readSidebarCookie("my_sidebar_state=false")).toBeNull()
  })
})

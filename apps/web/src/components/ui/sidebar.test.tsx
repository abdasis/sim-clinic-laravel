import { cleanup, render } from "@testing-library/react"
import { afterEach, describe, expect, it } from "vitest"

import {
  Sidebar,
  SidebarContent,
  SidebarProvider,
  readSidebarCookie,
} from "./sidebar.tsx"

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

/** Lebar viewport menentukan tier; jsdom default 1024 = desktop. */
function setViewport(width: number) {
  Object.defineProperty(window, "innerWidth", {
    configurable: true,
    writable: true,
    value: width,
  })
}

function renderSidebar() {
  render(
    <SidebarProvider>
      <Sidebar variant="inset" collapsible="icon">
        <SidebarContent />
      </Sidebar>
    </SidebarProvider>,
  )

  return document.querySelector('[data-slot="sidebar"]')
}

/**
 * Sidebar yang berdiri di samping konten. Di tier mobile tidak ada: yang
 * dipasang laci, dan isinya baru lahir ke DOM saat lacinya dibuka.
 */
function standingSidebar() {
  const sidebar = document.querySelector('[data-slot="sidebar"]')

  return sidebar?.getAttribute("data-mobile") === "true" ? null : sidebar
}

/**
 * "Sidebar tidak muncul di ukuran layar Galaxy Tab A" — tablet 800px pernah
 * dibuat mulai ciut supaya kontennya lega, dan yang tersisa rel ikon tanpa
 * nama menu. Ukuran layar tidak boleh ikut memutuskan: hanya pilihan pengguna
 * yang tersimpan di cookie.
 */
describe("keadaan awal sidebar", () => {
  afterEach(() => {
    cleanup()
    document.cookie = "sidebar_state=; path=/; max-age=0"
    setViewport(1024)
  })

  it("terbuka di tablet, bukan ciut jadi rel ikon", () => {
    setViewport(800)

    expect(renderSidebar()?.getAttribute("data-state")).toBe("expanded")
  })

  /**
   * Galaxy Tab A potret (600px). Dulu ini jatuh ke tier mobile dan sidebarnya
   * jadi laci — hilang sampai tombolnya diketuk. Sekarang relnya permanen:
   * ciut, tapi berdiri di samping konten, bukan laci.
   */
  it("jadi rel ikon permanen di tablet kecil, bukan laci", () => {
    setViewport(600)

    renderSidebar()
    const sidebar = standingSidebar()

    expect(sidebar).not.toBeNull()
    expect(sidebar?.getAttribute("data-state")).toBe("collapsed")
    expect(sidebar?.getAttribute("data-collapsible")).toBe("icon")
  })

  /**
   * Ponsel tetap laci: 390px tidak punya ruang untuk rel sekalipun. Lacinya
   * tertutup, jadi tidak ada sidebar yang berdiri maupun isinya di DOM.
   */
  it("tetap laci di ponsel", () => {
    setViewport(390)
    renderSidebar()

    expect(standingSidebar()).toBeNull()
  })

  /** Yang merentangkannya di tablet kecil tetap menemukannya terentang. */
  it("menghormati pilihan merentangkan di tablet kecil", () => {
    setViewport(600)
    document.cookie = "sidebar_state=true; path=/"

    expect(renderSidebar()?.getAttribute("data-state")).toBe("expanded")
  })

  it("terbuka juga di desktop", () => {
    setViewport(1440)

    expect(renderSidebar()?.getAttribute("data-state")).toBe("expanded")
  })

  /** Yang pernah menutupnya sendiri tetap menemukannya tertutup. */
  it("menghormati pilihan pengguna yang tersimpan", () => {
    setViewport(800)
    document.cookie = "sidebar_state=false; path=/"

    const sidebar = renderSidebar()

    expect(sidebar?.getAttribute("data-state")).toBe("collapsed")
    // Ciut berarti rel ikon, bukan hilang sama sekali.
    expect(sidebar?.getAttribute("data-collapsible")).toBe("icon")
  })
})

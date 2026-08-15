import fs from "node:fs"
import path from "node:path"
import { beforeEach, describe, expect, it } from "vitest"

import { applyAppearanceToDom } from "./use-appearance.ts"
import { normalizeAppearance, DEFAULT_APPEARANCE } from "#/types/appearance.ts"
import type { Appearance } from "#/types/appearance.ts"

/** Ambil skrip anti-kedip apa adanya dari __root.tsx, bukan salinannya. */
function readInitScript(): string {
  const source = fs.readFileSync(
    path.resolve(__dirname, "../routes/__root.tsx"),
    "utf8",
  )
  const match = /const THEME_INIT_SCRIPT = `([\s\S]*?)`\n/.exec(source)
  if (!match) throw new Error("THEME_INIT_SCRIPT tidak ditemukan di __root.tsx")

  return match[1]
}

function domState() {
  const root = document.documentElement

  return {
    classes: [...root.classList].filter((c) => c === "light" || c === "dark").sort(),
    theme: root.getAttribute("data-theme"),
    accent: root.getAttribute("data-accent"),
    colorScheme: root.style.colorScheme,
  }
}

function resetDom() {
  const root = document.documentElement
  root.className = ""
  root.removeAttribute("data-theme")
  root.removeAttribute("data-accent")
  root.style.colorScheme = ""
}

/** jsdom tidak punya matchMedia; sediakan yang bisa dikunci hasilnya. */
function stubPrefersDark(dark: boolean) {
  window.matchMedia = ((query: string) => ({
    matches: dark && query.includes("dark"),
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })) as unknown as typeof window.matchMedia
}

const SCRIPT = readInitScript()

describe("normalizeAppearance", () => {
  it("mengembalikan nilai asing ke bawaan", () => {
    expect(normalizeAppearance({ accent: "chartreuse", mode: "sepia" })).toEqual(
      DEFAULT_APPEARANCE,
    )
    expect(normalizeAppearance(null)).toEqual(DEFAULT_APPEARANCE)
  })

  it("mempertahankan nilai yang sah", () => {
    expect(
      normalizeAppearance({ accent: "gold", mode: "dark", sidebar_variant: "floating" }),
    ).toEqual({ accent: "gold", mode: "dark", sidebar_variant: "floating" })
  })
})

/**
 * Inti keluhan yang mau dihindari issue ini adalah kedipan warna. Kedipan itu
 * muncul kalau skrip pre-paint dan penerapan dari React menghasilkan DOM yang
 * berbeda. Keduanya ditulis terpisah, jadi kesamaannya diuji, bukan diyakini.
 */
describe("skrip anti-kedip sepakat dengan applyAppearanceToDom", () => {
  beforeEach(() => {
    resetDom()
    window.localStorage.clear()
  })

  const cases: Appearance[] = [
    { accent: "teal", mode: "auto", sidebar_variant: "inset" },
    { accent: "gold", mode: "dark", sidebar_variant: "floating" },
    { accent: "blue", mode: "light", sidebar_variant: "sidebar" },
    { accent: "rose", mode: "auto", sidebar_variant: "inset" },
  ]

  for (const prefersDark of [false, true]) {
    for (const appearance of cases) {
      it(`${appearance.accent}/${appearance.mode} (perangkat gelap: ${prefersDark})`, () => {
        stubPrefersDark(prefersDark)
        window.localStorage.setItem(
          "clinic_user",
          JSON.stringify({ id: 1, appearance }),
        )

        resetDom()
        // eslint-disable-next-line no-new-func
        new Function(SCRIPT)()
        const fromScript = domState()

        resetDom()
        applyAppearanceToDom(appearance)
        const fromReact = domState()

        expect(fromScript).toEqual(fromReact)
        expect(fromScript.accent).toBe(appearance.accent)
      })
    }
  }

  it("pengunjung yang belum login tetap memakai kunci theme lama", () => {
    stubPrefersDark(false)
    window.localStorage.setItem("theme", "dark")

    // eslint-disable-next-line no-new-func
    new Function(SCRIPT)()

    expect(domState()).toEqual({
      classes: ["dark"],
      theme: "dark",
      accent: "teal",
      colorScheme: "dark",
    })
  })
})

import { describe, expect, it } from "vitest"
import { render, screen } from "@testing-library/react"

import { useTrans } from "./use-trans.ts"

function Probe() {
  const { t, ready, isLoading } = useTrans()

  return (
    <div>
      <span data-testid="ready">{ready ? "siap" : "menunggu"}</span>
      <span data-testid="loading">{isLoading ? "memuat" : "diam"}</span>
      <span data-testid="label">{t("general.save")}</span>
      <span data-testid="missing">{t("general.tidak_ada")}</span>
    </div>
  )
}

describe("useTrans", () => {
  it("langsung siap dengan kamus Indonesia yang ikut bundel", () => {
    render(<Probe />)

    expect(screen.getByTestId("ready").textContent).toBe("siap")
    expect(screen.getByTestId("loading").textContent).toBe("diam")
    expect(screen.getByTestId("label").textContent).toBe("Simpan")
  })

  it("mengembalikan key apa adanya kalau terjemahannya tidak ada", () => {
    render(<Probe />)

    expect(screen.getByTestId("missing").textContent).toBe(
      "general.tidak_ada",
    )
  })
})

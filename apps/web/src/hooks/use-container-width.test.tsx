import { act, render } from "@testing-library/react"
import { afterEach, describe, expect, it, vi } from "vitest"

import { useContainerWidth } from "./use-container-width.ts"

const REAL_RESIZE_OBSERVER = globalThis.ResizeObserver

/** jsdom tidak melukis apa pun, jadi lebarnya dipasang langsung. */
function setMeasuredWidth(width: number): void {
  vi.spyOn(HTMLElement.prototype, "getBoundingClientRect").mockReturnValue({
    width,
    height: 0,
    top: 0,
    left: 0,
    right: width,
    bottom: 0,
    x: 0,
    y: 0,
    toJSON: () => ({}),
  } as DOMRect)
}

function Probe({ onWidth }: { onWidth: (width: number | null) => void }) {
  const [ref, width] = useContainerWidth<HTMLDivElement>()

  onWidth(width)

  return <div ref={ref} />
}

/** Lebar terakhir yang dilihat komponennya. */
function renderProbe(): () => number | null {
  let last: number | null = null

  render(<Probe onWidth={(width) => void (last = width)} />)

  return () => last
}

afterEach(() => {
  vi.restoreAllMocks()
  globalThis.ResizeObserver = REAL_RESIZE_OBSERVER
})

describe("useContainerWidth", () => {
  it("melaporkan lebar wadahnya yang terukur", () => {
    setMeasuredWidth(512)

    expect(renderProbe()()).toBe(512)
  })

  /**
   * Lebar 0 berarti belum terlukis atau sedang tersembunyi. Kalau itu
   * diperlakukan sebagai lebar sungguhan, tiap wadah tersembunyi dianggap
   * "paling sempit" dan pemanggilnya memilih bentuk sempit untuk layar lebar.
   */
  it("menjawab null saat belum terukur, bukan nol", () => {
    setMeasuredWidth(0)

    expect(renderProbe()()).toBeNull()
  })

  it("ikut berubah saat wadahnya berubah ukuran", () => {
    const callbacks: ResizeObserverCallback[] = []

    globalThis.ResizeObserver = class {
      constructor(callback: ResizeObserverCallback) {
        callbacks.push(callback)
      }
      observe() {}
      unobserve() {}
      disconnect() {}
    } as unknown as typeof ResizeObserver

    setMeasuredWidth(710)
    const width = renderProbe()
    expect(width()).toBe(710)

    // Sidebar dibuka: wadahnya menyempit tanpa layarnya ikut berubah.
    setMeasuredWidth(510)
    act(() => {
      callbacks.forEach((callback) =>
        callback([] as unknown as ResizeObserverEntry[], {} as ResizeObserver),
      )
    })

    expect(width()).toBe(510)
  })
})

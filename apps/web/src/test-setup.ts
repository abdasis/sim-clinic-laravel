/**
 * Tambalan lingkungan test.
 *
 * jsdom tidak punya ResizeObserver, sementara primitif Radix yang mengukur
 * dirinya sendiri (Switch, Select, Tooltip) memanggilnya saat mount. Tanpa
 * ini, komponen apa pun yang memuat salah satunya gagal dengan
 * "ResizeObserver is not defined" — galat yang sama sekali tidak berhubungan
 * dengan apa yang sedang diuji.
 */
class ResizeObserverStub implements ResizeObserver {
  observe(): void {}

  unobserve(): void {}

  disconnect(): void {}
}

globalThis.ResizeObserver ??= ResizeObserverStub

/**
 * jsdom juga tidak punya `matchMedia`, sementara hook tier layout
 * (`useLayoutTier`) memanggilnya untuk memisahkan mobile/tablet/desktop.
 * Tanpa ini, komponen apa pun yang memakai tier — shell, breadcrumb, tabel —
 * gagal dengan "matchMedia is not a function".
 *
 * Tambalan ini benar-benar menjawab kuerinya dari `window.innerWidth`, bukan
 * mengembalikan false begitu saja: tes yang mengatur lebar viewport jadi bisa
 * menguji perilaku tiap tier, bukan cuma jalur desktop. Satuan `rem` ikut
 * dimengerti karena ambang tier ditulis dalam rem, sama seperti Tailwind.
 */
const ROOT_FONT_SIZE = 16

globalThis.matchMedia ??= ((query: string): MediaQueryList => {
  const bound = /\((min|max)-width:\s*(\d+(?:\.\d+)?)(px|rem)\)/.exec(query)

  const matches = () => {
    if (!bound) return false

    const [, side, value, unit] = bound
    const px = Number(value) * (unit === "rem" ? ROOT_FONT_SIZE : 1)

    return side === "min" ? window.innerWidth >= px : window.innerWidth <= px
  }

  // `resize` dipakai sebagai pengganti perubahan kueri: tes mengubah
  // innerWidth lalu menyalakannya, dan pendengar mana pun ikut terpanggil.
  const listeners = new Set<(event: MediaQueryListEvent) => void>()

  window.addEventListener("resize", () => {
    const event = { matches: matches(), media: query } as MediaQueryListEvent
    listeners.forEach((listener) => listener(event))
  })

  return {
    get matches() {
      return matches()
    },
    media: query,
    onchange: null,
    addEventListener: (_: string, listener: (event: MediaQueryListEvent) => void) =>
      void listeners.add(listener),
    removeEventListener: (_: string, listener: (event: MediaQueryListEvent) => void) =>
      void listeners.delete(listener),
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
  } as MediaQueryList
}) as typeof window.matchMedia

import * as React from "react"

/**
 * Tiga tier layout admin, dari satu sumber ambang.
 *
 * Sebelumnya hanya ada dua mode — mobile dan desktop, dipisah di 768px —
 * sehingga iPad portrait (768-820px) diperlakukan persis seperti layar lebar:
 * sidebar 16rem permanen menyisakan konten ~496px untuk tabel berkolom enam.
 * Kasir sudah menyiasatinya dengan ambang sendiri (1024px), jadi POS punya
 * "tier tablet" sementara halaman lain tidak.
 *
 * Yang ditaruh di sini ambangnya saja. Apa yang dilakukan tiap tier adalah
 * keputusan shell, bukan keputusan hook.
 */
export type LayoutTier = "mobile" | "tablet" | "desktop"

/**
 * Ambangnya ditulis dalam rem, bukan px, karena itu persis yang dipakai
 * `md:`/`lg:` Tailwind v4 (48rem/64rem) — dan sidebar menyembunyikan dirinya
 * lewat `md:block`, bukan lewat tier ini.
 *
 * Kalau JS mengukur sendiri dengan `window.innerWidth`, keduanya bisa berbeda
 * pendapat: `innerWidth` ikut menghitung lebar scrollbar klasik (~15px),
 * sementara media query tidak. Di jendela 783px JS membaca 783 ("tablet",
 * jadi bukan drawer) padahal CSS membaca 768-15=768... dan tepat di bawahnya
 * `md:block` mati sehingga sidebar desktop ikut tersembunyi. Hasilnya pita
 * sempit tanpa sidebar sama sekali: tidak ada rel, tidak ada drawer. Karena
 * itu tier dibaca dari media query yang sama dengan CSS-nya.
 */
const TABLET_QUERY = "(min-width: 48rem)"
const DESKTOP_QUERY = "(min-width: 64rem)"

/** Padanan piksel ambang di atas pada ukuran font root bawaan (16px). */
export const TABLET_MIN_WIDTH = 768
export const DESKTOP_MIN_WIDTH = 1024

/**
 * Tier yang sedang berlaku.
 *
 * Nilai awalnya `desktop` di server dan pada render pertama: kerangka layar
 * (ShellSkeleton) memakai tier yang sama, jadi yang tampil sebelum ukuran
 * terbaca adalah bentuk desktop — bukan bentuk mobile yang lalu melompat.
 */
export function useLayoutTier(): LayoutTier {
  const [tier, setTier] = React.useState<LayoutTier>("desktop")

  React.useEffect(() => {
    const tablet = window.matchMedia(TABLET_QUERY)
    const desktop = window.matchMedia(DESKTOP_QUERY)

    const read = () =>
      setTier(desktop.matches ? "desktop" : tablet.matches ? "tablet" : "mobile")

    tablet.addEventListener("change", read)
    desktop.addEventListener("change", read)
    read()

    return () => {
      tablet.removeEventListener("change", read)
      desktop.removeEventListener("change", read)
    }
  }, [])

  return tier
}

/**
 * Turunan dari tier, bukan ambang kedua: mobile adalah tier paling sempit.
 *
 * Dipertahankan sebagai nama tersendiri karena itu nama yang dipakai pola
 * shadcn, dan komponen pihak ketiga yang disalin ke sini mencarinya.
 */
export function useIsMobile(): boolean {
  return useLayoutTier() === "mobile"
}
